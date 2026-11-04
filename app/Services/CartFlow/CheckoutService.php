<?php

namespace App\Services\CartFlow;

use App\Models\CartFlow\BloqueAgenda;
use App\Models\CartFlow\Carrito;
use App\Models\CartFlow\CarritoPaciente;
use App\Models\CartFlow\Pago;
use App\Models\CartFlow\Pedido;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * SPEC 04 — Convierte un carro abierto en pedido.
 *
 * 1. Recalcula en el servidor con la fecha de la cita (la edad cuenta a esa fecha).
 * 2. Valida bloqueos, datos y consentimientos de cada paciente y la orden medica.
 * 3. Valida el bloque (dia, zona, franja) y toma el cupo dentro de una transaccion con lockForUpdate.
 * 4. Copia el carro al pedido (snapshot inmutable) y marca el carro como convertido.
 */
class CheckoutService
{
    /** Disco privado: la orden medica es dato clinico y nunca va a public/. */
    public const DISCO_ORDENES = 'local';

    public const CARPETA_ORDENES = 'cart-flow/ordenes';

    public function __construct(
        private CarritoService $carritos,
        private AgendaService $agenda,
    ) {}

    /**
     * @param  array<int, UploadedFile>  $ordenes  orden medica por id de paciente del carro
     * @return array{pedido: Pedido, token_consulta: string}
     */
    public function checkout(Carrito $carrito, array $datos, array $ordenes = []): array
    {
        $fecha = Carbon::parse($datos['fecha'])->startOfDay();
        $reglas = $this->carritos->reglas();

        if (! empty($datos['direccion'])) {
            $carrito->direccion = $datos['direccion'];
        }

        if (array_key_exists('referencia', $datos)) {
            $carrito->referencia = $datos['referencia'];
        }

        $carrito->save();

        if (! $carrito->direccion) {
            throw CartFlowException::validacion('Indica la dirección de la visita.', ['direccion' => ['requerida']]);
        }

        $calculo = $this->carritos->calcular($carrito, $fecha);

        // Si se quito un producto oculto, el total cambio: el cliente debe revisar antes de pagar.
        if (collect($calculo['avisos'])->contains('codigo', 'producto_no_disponible')) {
            throw CartFlowException::conflicto('Un producto de tu carro ya no está disponible y se quitó. Revisa el total antes de pagar.', $calculo['avisos']);
        }

        if ($calculo['bloqueos'] !== []) {
            throw CartFlowException::validacion('El carro tiene observaciones que impiden pagar.', $calculo['bloqueos']);
        }

        // Pacientes con lineas: los que quedaron vacios no pasan al pedido.
        $calculados = collect($calculo['pacientes'])->filter(fn ($p) => $p['lineas'] !== [])->keyBy('id');
        $pacientes = $carrito->pacientes->filter(fn (CarritoPaciente $p) => $calculados->has($p->id));

        $errores = [];

        foreach ($pacientes as $p) {
            $errores = array_merge($errores, $this->validarPaciente($p, $calculados[$p->id], $ordenes[$p->id] ?? null));
        }

        if ($errores !== []) {
            throw CartFlowException::validacion('Faltan datos de los pacientes.', $errores);
        }

        [, , $minimo, $maximo] = $this->agenda->rango($reglas);

        if ($fecha->lt($minimo) || $fecha->gt($maximo)) {
            throw CartFlowException::validacion('La fecha elegida no está disponible.', ['fecha' => ['fuera_de_rango']]);
        }

        $guardados = [];

        try {
            $resultado = DB::transaction(function () use ($carrito, $datos, $fecha, $calculo, $calculados, $pacientes, $ordenes, &$guardados) {
                $bloque = BloqueAgenda::whereKey($datos['bloque_id'])->lockForUpdate()->first();
                $zonaId = $carrito->comuna?->zona_id;

                if (! $bloque || ! $bloque->activo
                    || $bloque->dia_semana !== $fecha->isoWeekday()
                    || ($bloque->zona_id !== null && $bloque->zona_id !== $zonaId)
                    || ($calculo['requiere_manana'] && $bloque->franja !== 'manana')) {
                    throw CartFlowException::validacion('El bloque horario no corresponde a la fecha o a la preparación del carro.', ['bloque_id' => ['no_valido']]);
                }

                if ($this->agenda->usados($bloque->id, $fecha->toDateString()) >= $bloque->cupo) {
                    throw CartFlowException::conflicto('Ese bloque se acaba de llenar: elige otro horario.', 'bloque_lleno');
                }

                $token = (string) Str::uuid();
                $desglose = $calculo['desglose'];

                $pedido = Pedido::create([
                    'numero' => 'TMP-'.Str::random(12),
                    'token_consulta' => $token,
                    'carrito_id' => $carrito->id,
                    'comuna' => $carrito->comuna->comuna,
                    'zona' => $carrito->comuna->zona?->zona,
                    'direccion' => $carrito->direccion,
                    'referencia' => $carrito->referencia,
                    'plan' => $carrito->plan,
                    'subtotal_lab' => $desglose['subtotal_lab'],
                    'subtotal_servicios' => $desglose['subtotal_servicios'],
                    'toma_muestra' => $desglose['toma_muestra'],
                    'insumos' => $desglose['insumos'],
                    'cargo_domicilio' => $desglose['cargo_domicilio'],
                    'descuento_plan' => $desglose['descuento_plan'],
                    'total_sin_redondeo' => $desglose['total_sin_redondeo'],
                    'ajuste_redondeo' => $desglose['ajuste_redondeo'],
                    'total' => $desglose['total'],
                    'fecha' => $fecha->toDateString(),
                    'bloque_id' => $bloque->id,
                    'bloque' => $bloque->etiqueta(),
                    'requiere_manana' => $calculo['requiere_manana'],
                    'estado' => 'pendiente_pago',
                    'medio_pago' => $datos['medio_pago'],
                    'estado_pago' => 'pendiente',
                    'contacto_nombre' => $datos['contacto_nombre'],
                    'contacto_email' => $datos['contacto_email'],
                    'contacto_telefono' => $datos['contacto_telefono'],
                    'observaciones' => $datos['observaciones'] ?? null,
                ]);

                $pedido->update(['numero' => sprintf('ES-%d-%06d', now()->year, $pedido->id)]);

                $orden = 1;

                foreach ($pacientes as $p) {
                    $calc = $calculados[$p->id];

                    $pedidoPaciente = $pedido->pacientes()->create([
                        'orden' => $orden++,
                        'nombre' => $p->nombre,
                        'rut' => $p->rut,
                        'fecha_nac' => $p->fecha_nac?->format('Y-m-d'),
                        'sexo' => $p->sexo,
                        'prevision' => $p->prevision,
                        'telefono' => $p->telefono,
                        'email' => $p->email,
                        'consentimiento' => $p->consentimiento,
                        'apoderado_nombre' => $p->apoderado_nombre,
                        'apoderado_rut' => $p->apoderado_rut,
                        'consentimiento_apoderado' => $p->consentimiento_apoderado,
                        'preparacion' => $calc['preparacion'],
                    ]);

                    foreach ($calc['lineas'] as $l) {
                        $pedidoPaciente->lineas()->create([
                            'sku' => $l['sku'],
                            'nombre' => $l['nombre'],
                            'tipo' => $l['tipo'],
                            'precio_unitario' => $l['precio_unitario'],
                            'cantidad' => $l['cantidad'],
                            'subtotal' => $l['subtotal'],
                            'automatica' => $l['automatica'],
                        ]);
                    }

                    if (isset($ordenes[$p->id]) && $calc['preparacion']['requiere_orden_medica']) {
                        /** @var UploadedFile $archivo */
                        $archivo = $ordenes[$p->id];
                        $path = $archivo->storeAs(
                            self::CARPETA_ORDENES.'/'.$pedido->numero,
                            'paciente-'.$pedidoPaciente->id.'-'.Str::random(8).'.'.$archivo->extension(),
                            self::DISCO_ORDENES
                        );
                        $guardados[] = $path;

                        $pedidoPaciente->ordenesMedicas()->create([
                            'path' => $path,
                            'mime' => (string) $archivo->getMimeType(),
                            'nombre_original' => mb_substr($archivo->getClientOriginalName(), 0, 255),
                        ]);
                    }
                }

                if ($datos['medio_pago'] !== 'webpay') {
                    // Transferencia o efectivo: queda registrado el pago esperado; Gerencia lo confirma.
                    Pago::create([
                        'pedido_id' => $pedido->id,
                        'medio' => $datos['medio_pago'],
                        'monto' => $pedido->total,
                        'estado' => 'iniciado',
                    ]);
                }

                $carrito->update(['estado' => 'convertido']);

                return ['pedido' => $pedido, 'token_consulta' => $token];
            });
        } catch (\Throwable $e) {
            // Sin pedido, los archivos recien guardados quedarian huerfanos.
            foreach ($guardados as $path) {
                Storage::disk(self::DISCO_ORDENES)->delete($path);
            }

            throw $e;
        }

        return $resultado;
    }

    /** Errores de datos de un paciente, con la clave "pacientes.{id}.campo". */
    private function validarPaciente(CarritoPaciente $p, array $calc, ?UploadedFile $orden): array
    {
        $errores = [];
        $clave = "pacientes.{$p->id}";
        $nombre = $p->nombre ?: "Paciente {$p->orden}";

        foreach (['nombre' => 'el nombre', 'rut' => 'el RUT', 'fecha_nac' => 'la fecha de nacimiento'] as $campo => $texto) {
            if (empty($p->{$campo})) {
                $errores["{$clave}.{$campo}"] = "Falta {$texto} de {$nombre}.";
            }
        }

        if ($p->rut && ! preg_match('/^\d{7,8}-[0-9K]$/', $p->rut)) {
            $errores["{$clave}.rut"] = "El RUT de {$nombre} no tiene un formato válido (12345678-9).";
        }

        if (! $p->consentimiento) {
            $errores["{$clave}.consentimiento"] = "Falta el consentimiento de {$nombre}.";
        }

        if ($calc['edad'] !== null && $calc['edad'] < 18
            && (! $p->apoderado_nombre || ! $p->apoderado_rut || ! $p->consentimiento_apoderado)) {
            $errores["{$clave}.apoderado"] = "{$nombre} es menor de edad: faltan los datos y el consentimiento del apoderado.";
        }

        if ($calc['preparacion']['requiere_orden_medica'] && ! $orden) {
            $errores["{$clave}.orden_medica"] = "Adjunta la orden médica de {$nombre}.";
        }

        return $errores;
    }
}
