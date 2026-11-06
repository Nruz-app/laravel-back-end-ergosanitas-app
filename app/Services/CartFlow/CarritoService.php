<?php

namespace App\Services\CartFlow;

use App\Models\CartFlow\Carrito;
use App\Models\CartFlow\CarritoLinea;
use App\Models\CartFlow\CarritoPaciente;
use App\Models\CartFlow\Comuna;
use App\Models\CartFlow\Producto;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * SPEC 04 — Carro anonimo: crear, editar direccion/plan, pacientes y lineas, y calcularlo.
 *
 * Quitar un paciente o una linea es soft delete (especificacion legal §1: nunca se borran filas).
 * Al leer el carro, las lineas de productos que el dueno oculto se quitan con un aviso (§7.3).
 */
class CarritoService
{
    /** Tipos que tienen sentido con cantidad > 1 (laboratorio va siempre de a uno). */
    private const TIPOS_CON_CANTIDAD = ['servicio_domicilio', 'servicio_evento'];

    public function __construct(
        private CalculadoraCarroService $calculadora,
        private CatalogoService $catalogo,
    ) {}

    public function crear(array $datos): Carrito
    {
        $reglas = $this->reglas();

        return DB::transaction(function () use ($datos, $reglas) {
            $carrito = Carrito::create([
                'uuid' => (string) Str::uuid(),
                'comuna_id' => $datos['comuna_id'] ?? null,
                'direccion' => $datos['direccion'] ?? null,
                'referencia' => $datos['referencia'] ?? null,
                'plan' => $datos['plan'] ?? 'ninguno',
                'estado' => 'abierto',
                'expira_en' => now()->addDays((int) $reglas['carrito_dias_vigencia']),
            ]);

            // El carro nace con un paciente: las lineas siempre cuelgan de uno.
            $carrito->pacientes()->create(['orden' => 1]);

            return $carrito;
        });
    }

    /** Carro por uuid (cualquier estado) o null. */
    public function buscar(string $uuid): ?Carrito
    {
        return Carrito::where('uuid', $uuid)->first();
    }

    /** Carro abierto o excepcion 404/409. */
    public function abierto(string $uuid): Carrito
    {
        $carrito = $this->buscar($uuid);

        if (! $carrito) {
            throw CartFlowException::noEncontrado('Carro no encontrado');
        }

        if ($carrito->estado === 'abierto' && ! $carrito->estaAbierto()) {
            $carrito->update(['estado' => 'expirado']);
        }

        if ($carrito->estado !== 'abierto') {
            throw CartFlowException::conflicto('Este carro ya no está disponible: inicia uno nuevo.', 'carrito_'.$carrito->estado);
        }

        return $carrito;
    }

    public function actualizar(Carrito $carrito, array $datos): Carrito
    {
        $carrito->fill(array_intersect_key($datos, array_flip(['comuna_id', 'direccion', 'referencia', 'plan'])))->save();

        return $carrito;
    }

    public function agregarPaciente(Carrito $carrito, array $datos): CarritoPaciente
    {
        $orden = (int) $carrito->pacientes()->max('orden') + 1;

        return $carrito->pacientes()->create($this->datosPaciente($datos) + ['orden' => $orden]);
    }

    public function actualizarPaciente(CarritoPaciente $paciente, array $datos): CarritoPaciente
    {
        $paciente->fill($this->datosPaciente($datos))->save();

        return $paciente;
    }

    /** Soft delete del paciente y sus lineas. El carro siempre conserva al menos un paciente. */
    public function quitarPaciente(Carrito $carrito, CarritoPaciente $paciente): void
    {
        if ($carrito->pacientes()->count() <= 1) {
            throw CartFlowException::validacion('El carro debe tener al menos un paciente.', ['paciente' => ['minimo_uno']]);
        }

        DB::transaction(function () use ($paciente) {
            $paciente->lineas()->delete();
            $paciente->delete();
        });
    }

    public function paciente(Carrito $carrito, int $id): CarritoPaciente
    {
        $paciente = $carrito->pacientes()->whereKey($id)->first();

        if (! $paciente) {
            throw CartFlowException::noEncontrado('Paciente no encontrado en este carro');
        }

        return $paciente;
    }

    public function linea(Carrito $carrito, int $id): CarritoLinea
    {
        $linea = CarritoLinea::whereKey($id)
            ->whereHas('paciente', fn ($q) => $q->where('carrito_id', $carrito->id))
            ->first();

        if (! $linea) {
            throw CartFlowException::noEncontrado('Línea no encontrada en este carro');
        }

        return $linea;
    }

    /**
     * Agrega un producto a un paciente. Rechaza ocultos, de cotizacion y la toma de muestra suelta.
     * Si el producto ya esta: los servicios suman cantidad; el laboratorio no se duplica.
     */
    public function agregarLinea(Carrito $carrito, int $pacienteId, string $sku, int $cantidad = 1): CarritoLinea
    {
        $paciente = $this->paciente($carrito, $pacienteId);
        $producto = Producto::where('sku', $sku)->first();

        if (! $producto || ! $producto->visible_tienda || $producto->tipo === 'toma_muestra') {
            throw CartFlowException::noEncontrado('Este producto no está disponible');
        }

        if ($producto->solo_cotizacion) {
            throw CartFlowException::validacion(
                "{$producto->nombre} se cotiza aparte: usa el formulario de cotización.",
                ['sku' => ['solo_cotizacion']]
            );
        }

        $existente = $paciente->lineas()->where('producto_id', $producto->id)->first();

        if ($existente) {
            if (in_array($producto->tipo, self::TIPOS_CON_CANTIDAD, true)) {
                $existente->update(['cantidad' => min(20, $existente->cantidad + $cantidad)]);
            }

            return $existente;
        }

        return $paciente->lineas()->create([
            'producto_id' => $producto->id,
            'cantidad' => in_array($producto->tipo, self::TIPOS_CON_CANTIDAD, true) ? $cantidad : 1,
        ]);
    }

    public function actualizarLinea(CarritoLinea $linea, int $cantidad): CarritoLinea
    {
        $tipo = $linea->producto?->tipo;
        $linea->update(['cantidad' => in_array($tipo, self::TIPOS_CON_CANTIDAD, true) ? $cantidad : 1]);

        return $linea;
    }

    public function quitarLinea(CarritoLinea $linea): void
    {
        $linea->delete();
    }

    /**
     * Calcula el carro. Quita (soft delete) las lineas cuyo producto se oculto y lo informa en avisos.
     */
    public function calcular(Carrito $carrito, ?CarbonInterface $fecha = null): array
    {
        $carrito->load(['comuna.zona', 'pacientes.lineas.producto']);
        $reglas = $this->reglas();
        $avisos = [];

        $pacientes = [];

        foreach ($carrito->pacientes as $paciente) {
            $lineas = [];

            foreach ($paciente->lineas as $linea) {
                $producto = $linea->producto;

                if (! $producto || ! $producto->visible_tienda) {
                    if ($carrito->estado === 'abierto') {
                        $linea->delete();
                    }

                    $avisos[] = [
                        'codigo' => 'producto_no_disponible',
                        'mensaje' => ($producto?->nombre ?? 'Un producto').' ya no está disponible y se quitó del carro.',
                    ];

                    continue;
                }

                $lineas[] = ['id' => $linea->id, 'cantidad' => $linea->cantidad, 'producto' => $producto];
            }

            $pacientes[] = [
                'id' => $paciente->id,
                'nombre' => $paciente->nombre,
                'fecha_nac' => $paciente->fecha_nac?->format('Y-m-d'),
                'sexo' => $paciente->sexo,
                'lineas' => $lineas,
            ];
        }

        $comuna = $carrito->comuna;

        $resultado = $this->calculadora->calcular([
            'comuna' => $comuna ? [
                'a_cotizar' => $comuna->esACotizar(),
                'zona' => $comuna->zona?->zona,
                'cargo_servicio' => (int) $comuna->zona?->cargo_servicio,
                'cargo_laboratorio' => (int) $comuna->zona?->cargo_laboratorio,
            ] : null,
            'plan' => $carrito->plan,
            'fecha' => $fecha ?? Carbon::today(),
            'pacientes' => $pacientes,
            'automaticos' => $this->catalogo->automaticos($reglas),
        ], $reglas);

        $resultado['avisos'] = array_merge($avisos, $resultado['avisos']);

        return $resultado;
    }

    /** Forma publica del carro (§3.2 de la spec). */
    public function serializar(Carrito $carrito, ?CarbonInterface $fecha = null): array
    {
        $calculo = $this->calcular($carrito, $fecha);
        $porId = collect($calculo['pacientes'])->keyBy('id');

        return [
            'uuid' => $carrito->uuid,
            'estado' => $carrito->estado,
            'expira_en' => $carrito->expira_en?->toIso8601String(),
            'comuna' => $this->catalogo->comunaPublica($carrito->comuna),
            'direccion' => $carrito->direccion,
            'referencia' => $carrito->referencia,
            'plan' => $carrito->plan,
            'pacientes' => $carrito->pacientes->map(function (CarritoPaciente $p) use ($porId) {
                $calc = $porId->get($p->id, ['lineas' => [], 'preparacion' => null, 'edad' => null]);

                return [
                    'id' => $p->id,
                    'orden' => $p->orden,
                    'nombre' => $p->nombre,
                    'rut' => $p->rut,
                    'fecha_nac' => $p->fecha_nac?->format('Y-m-d'),
                    'edad' => $calc['edad'],
                    'sexo' => $p->sexo,
                    'prevision' => $p->prevision,
                    'telefono' => $p->telefono,
                    'email' => $p->email,
                    'consentimiento' => $p->consentimiento,
                    'apoderado_nombre' => $p->apoderado_nombre,
                    'apoderado_rut' => $p->apoderado_rut,
                    'consentimiento_apoderado' => $p->consentimiento_apoderado,
                    'lineas' => $calc['lineas'],
                    'preparacion' => $calc['preparacion'],
                ];
            })->values()->all(),
            'desglose' => $calculo['desglose'],
            'requiere_manana' => $calculo['requiere_manana'],
            'requiere_orden_medica' => $calculo['requiere_orden_medica'],
            'a_cotizar' => $calculo['a_cotizar'],
            'bloqueos' => $calculo['bloqueos'],
            'avisos' => $calculo['avisos'],
        ];
    }

    /** cf_reglas mezcladas con los valores por defecto de la calculadora. */
    public function reglas(): array
    {
        return array_replace(CalculadoraCarroService::REGLAS_POR_DEFECTO, $this->catalogo->reglas());
    }

    public function comunaExiste(?int $id): bool
    {
        return $id !== null && Comuna::whereKey($id)->where('activo', true)->exists();
    }

    private function datosPaciente(array $datos): array
    {
        $campos = [
            'nombre', 'rut', 'fecha_nac', 'sexo', 'prevision', 'telefono', 'email',
            'consentimiento', 'apoderado_nombre', 'apoderado_rut', 'consentimiento_apoderado',
        ];

        $salida = array_intersect_key($datos, array_flip($campos));

        if (isset($salida['rut'])) {
            $salida['rut'] = strtoupper(str_replace('.', '', trim($salida['rut'])));
        }

        if (isset($salida['sexo'])) {
            $salida['sexo'] = strtoupper($salida['sexo']);
        }

        return $salida;
    }
}
