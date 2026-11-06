<?php

namespace App\Services\CartFlow;

use App\Models\CartFlow\Producto;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * SPEC 04 — Algoritmo del total del carro (§3 de ERGO_SANITAS_SERVICIOS_Y_CARRO.md, Parte B).
 *
 * Es una funcion pura: no consulta la base de datos. Recibe el carro ya cargado (productos como
 * modelos sin guardar sirven igual) y devuelve desglose, lineas por paciente, preparacion
 * consolidada, bloqueos y avisos. Asi los 10 ejemplos de la especificacion se prueban en un test
 * unitario, y el front nunca calcula precios.
 *
 * Entrada:
 *   comuna      => ['a_cotizar' => bool, 'zona' => ?int, 'cargo_servicio' => int, 'cargo_laboratorio' => int] | null
 *   plan        => 'ninguno' | 'te_cuida' | 'familiar'
 *   fecha       => fecha de referencia para la edad (la de la cita, o hoy)
 *   pacientes   => [['id' => ?int, 'nombre' => ?string, 'fecha_nac' => ?string, 'sexo' => ?string,
 *                    'lineas' => [['id' => ?int, 'cantidad' => int, 'producto' => Producto]]]]
 *   automaticos => ['SKU' => Producto] productos que el carro agrega solo (toma de muestra, TRUTROL)
 */
class CalculadoraCarroService
{
    /** Valores por defecto; cf_reglas los reemplaza clave por clave. */
    public const REGLAS_POR_DEFECTO = [
        'toma_muestra' => ['sku' => 'LAB-307011-TM', 'precio' => 1460],
        'ticket_minimo' => 20000,
        'redondeo_multiplo' => 1000,
        'horas_ayuno_defecto' => 8,
        'laboratorio_solo_manana' => true,
        // No las usa la calculadora; viven aqui para tener un solo lugar de valores por defecto.
        'agenda_anticipacion_dias' => 1,
        'agenda_dias_max' => 30,
        'carrito_dias_vigencia' => 7,
    ];

    public const MENSAJE_TICKET_MINIMO = 'El monto mínimo de una visita es $20.000. Te ofrecemos un operativo: solicita una cotización.';

    public function calcular(array $datos, array $reglas = []): array
    {
        $reglas = array_replace(self::REGLAS_POR_DEFECTO, $reglas);
        $plan = $datos['plan'] ?? 'ninguno';
        $fecha = $datos['fecha'] ?? Carbon::today();
        $automaticos = $datos['automaticos'] ?? [];
        $comuna = $datos['comuna'] ?? null;

        $bloqueos = [];
        $avisos = [];

        $aCotizar = $comuna === null || ! empty($comuna['a_cotizar']) || empty($comuna['zona']);

        if ($comuna === null) {
            $bloqueos[] = $this->bloqueo('sin_comuna', 'Selecciona la comuna de la visita.');
        } elseif ($aCotizar) {
            $bloqueos[] = $this->bloqueo('a_cotizar', 'Esta comuna está fuera de nuestra zona de despacho: solicita una cotización.');
        }

        $subtotalLab = 0;
        $subtotalServicios = 0;
        $tomaMuestra = 0;
        $insumos = 0;
        $descuentoPlan = 0;
        $hayLab = false;
        $hayServicio = false;
        $requiereManana = false;
        $requiereOrden = false;
        $cantidadLineas = 0;

        $pacientesSalida = [];

        foreach ($datos['pacientes'] ?? [] as $paciente) {
            $edad = $this->edad($paciente['fecha_nac'] ?? null, $fecha);
            $sexo = isset($paciente['sexo']) ? strtoupper((string) $paciente['sexo']) : null;

            $lineasSalida = [];
            $tieneExamenSuelto = false;
            $tieneKit = false;
            $agregados = [];
            $ayuno = false;
            $horasAyuno = 0;
            $ordenPaciente = false;
            $indicaciones = [];

            foreach ($paciente['lineas'] ?? [] as $linea) {
                /** @var Producto $producto */
                $producto = $linea['producto'];
                $cantidad = max(1, (int) ($linea['cantidad'] ?? 1));
                $cantidadLineas++;

                $bloqueosLinea = $this->validarLinea($producto, $edad, $sexo);

                foreach ($bloqueosLinea as $b) {
                    $bloqueos[] = $b + ['paciente_id' => $paciente['id'] ?? null, 'linea_id' => $linea['id'] ?? null];
                }

                [$precioUnitario, $descuentoUnitario] = $this->precioUnitario($producto, $plan, $edad);
                $subtotal = $precioUnitario * $cantidad;

                if ($producto->esLaboratorio()) {
                    $hayLab = true;
                    $subtotalLab += $subtotal;

                    if ($producto->tipo === 'examen_lab') {
                        $tieneExamenSuelto = true;
                    }
                } else {
                    if ($producto->tipo === 'servicio_domicilio') {
                        $hayServicio = true;
                    }
                    $subtotalServicios += $subtotal;
                    $descuentoPlan += $descuentoUnitario * $cantidad;
                }

                if ($producto->incluye_toma_muestra) {
                    $tieneKit = true;
                }

                if ($producto->agrega_automatico) {
                    $agregados[$producto->agrega_automatico] = true;
                }

                // Preparacion: rige la indicacion mas exigente.
                if ($producto->requiere_ayuno) {
                    $ayuno = true;
                    $horasAyuno = max($horasAyuno, $producto->horas_ayuno ?: (int) $reglas['horas_ayuno_defecto']);
                }

                if ($producto->requiere_orden_medica) {
                    $ordenPaciente = true;
                }

                if ($producto->solo_ruta_manana) {
                    $requiereManana = true;
                }

                $texto = trim((string) $producto->preparacion);

                // Las indicaciones de ayuno se reemplazan por una sola: "Ayuno de N horas".
                if ($texto !== '' && ! ($producto->requiere_ayuno && stripos($texto, 'ayuno') !== false)) {
                    $indicaciones[$texto] = true;
                }

                $lineasSalida[] = [
                    'id' => $linea['id'] ?? null,
                    'sku' => $producto->sku,
                    'nombre' => $producto->nombre,
                    'tipo' => $producto->tipo,
                    'cantidad' => $cantidad,
                    'precio_lista' => (int) $producto->precio,
                    'precio_unitario' => $precioUnitario,
                    'subtotal' => $subtotal,
                    'automatica' => false,
                    'requiere_orden_medica' => (bool) $producto->requiere_orden_medica,
                    'bloqueada' => $bloqueosLinea !== [],
                ];
            }

            // Toma de muestra: una por paciente si hay examenes sueltos y ningun perfil/pack la incluye.
            if ($tieneExamenSuelto && ! $tieneKit) {
                $toma = $automaticos[$reglas['toma_muestra']['sku']] ?? null;
                $precioToma = (int) ($reglas['toma_muestra']['precio'] ?? ($toma?->precio ?? 0));
                $tomaMuestra += $precioToma;
                $lineasSalida[] = $this->lineaAutomatica(
                    $reglas['toma_muestra']['sku'],
                    $toma?->nombre ?? 'Toma de muestra venosa',
                    'toma_muestra',
                    $precioToma
                );
            }

            // Insumos automaticos (TRUTROL): uno por paciente aunque pida varias pruebas, aunque este oculto.
            foreach (array_keys($agregados) as $sku) {
                $insumo = $automaticos[$sku] ?? null;

                if (! $insumo) {
                    $avisos[] = ['codigo' => 'insumo_no_encontrado', 'mensaje' => "No se encontró el insumo {$sku} del catálogo."];

                    continue;
                }

                $insumos += (int) $insumo->precio;
                $lineasSalida[] = $this->lineaAutomatica($sku, $insumo->nombre, $insumo->tipo, (int) $insumo->precio);
            }

            if ($ayuno) {
                $requiereManana = true;
            }

            if ($ordenPaciente) {
                $requiereOrden = true;
            }

            $preparacion = array_keys($indicaciones);

            if ($ayuno) {
                array_unshift($preparacion, "Ayuno de {$horasAyuno} horas");
            }

            $pacientesSalida[] = [
                'id' => $paciente['id'] ?? null,
                'nombre' => $paciente['nombre'] ?? null,
                'edad' => $edad,
                'lineas' => $lineasSalida,
                'preparacion' => [
                    'ayuno' => $ayuno,
                    'horas_ayuno' => $ayuno ? $horasAyuno : 0,
                    'requiere_orden_medica' => $ordenPaciente,
                    'indicaciones' => $preparacion,
                ],
            ];
        }

        if ($hayLab && $reglas['laboratorio_solo_manana']) {
            $requiereManana = true;
        }

        // Domicilio: una vez por direccion; visita mixta = el mayor; servicio_evento no suma.
        $cargoDomicilio = 0;

        if (! $aCotizar && $comuna !== null) {
            $cargoLab = (int) $comuna['cargo_laboratorio'];
            $cargoServ = (int) $comuna['cargo_servicio'];

            if ($hayLab && $hayServicio) {
                $cargoDomicilio = max($cargoLab, $cargoServ);
            } elseif ($hayLab) {
                $cargoDomicilio = $cargoLab;
            } elseif ($hayServicio) {
                $cargoDomicilio = $cargoServ;
            }
        }

        $totalSinRedondeo = $subtotalLab + $tomaMuestra + $insumos + $subtotalServicios + $cargoDomicilio;
        $multiplo = max(1, (int) $reglas['redondeo_multiplo']);
        $total = (int) (ceil($totalSinRedondeo / $multiplo) * $multiplo);

        if ($cantidadLineas === 0) {
            $bloqueos[] = $this->bloqueo('carro_vacio', 'Agrega al menos un servicio o examen.');
        } elseif (! $aCotizar && $total < (int) $reglas['ticket_minimo']) {
            $bloqueos[] = $this->bloqueo('ticket_minimo', self::MENSAJE_TICKET_MINIMO);
        }

        return [
            'desglose' => [
                'subtotal_lab' => $subtotalLab,
                'subtotal_servicios' => $subtotalServicios,
                'toma_muestra' => $tomaMuestra,
                'insumos' => $insumos,
                'cargo_domicilio' => $cargoDomicilio,
                'descuento_plan' => $descuentoPlan,
                'total_sin_redondeo' => $totalSinRedondeo,
                'ajuste_redondeo' => $total - $totalSinRedondeo,
                'total' => $total,
            ],
            'pacientes' => $pacientesSalida,
            'requiere_manana' => $requiereManana,
            'requiere_orden_medica' => $requiereOrden,
            'a_cotizar' => $aCotizar,
            'bloqueos' => $bloqueos,
            'avisos' => $avisos,
        ];
    }

    /**
     * Precio efectivo de una unidad y el descuento de plan que se le aplico.
     * Laboratorio: siempre precio de lista (los planes nunca aplican al laboratorio).
     * Servicios: precio del plan si existe; beneficio adulto mayor si la edad alcanza. Gana el menor.
     *
     * @return array{0:int,1:int}
     */
    public function precioUnitario(Producto $producto, string $plan, ?int $edad): array
    {
        $lista = (int) $producto->precio;

        if ($producto->esLaboratorio()) {
            return [$lista, 0];
        }

        $conPlan = $lista;

        if ($plan === 'te_cuida' && $producto->precio_te_cuida !== null) {
            $conPlan = (int) $producto->precio_te_cuida;
        } elseif ($plan === 'familiar' && $producto->precio_familiar !== null) {
            $conPlan = (int) $producto->precio_familiar;
        }

        $precio = $conPlan;

        if ($producto->precio_adulto_mayor !== null && $edad !== null
            && $edad >= (int) ($producto->edad_min_beneficio ?? 60)) {
            $precio = min($precio, (int) $producto->precio_adulto_mayor);
        }

        // Solo se informa como descuento de plan lo que bajo el plan.
        $descuento = $precio === $conPlan ? $lista - $conPlan : 0;

        return [$precio, $descuento];
    }

    /** Edad y sexo del paciente, tipo de producto y visibilidad. */
    public function validarLinea(Producto $producto, ?int $edad, ?string $sexo): array
    {
        $bloqueos = [];

        if ($producto->solo_cotizacion) {
            $bloqueos[] = $this->bloqueo('solo_cotizacion', "{$producto->nombre} se cotiza aparte: usa el formulario de cotización.");
        }

        if (! $producto->visible_tienda) {
            $bloqueos[] = $this->bloqueo('no_disponible', "{$producto->nombre} ya no está disponible.");
        }

        if ($edad !== null && $producto->edad_min !== null && $edad < $producto->edad_min) {
            $bloqueos[] = $this->bloqueo('edad_min', "{$producto->nombre} es desde los {$producto->edad_min} años.");
        }

        if ($edad !== null && $producto->edad_max !== null && $edad > $producto->edad_max) {
            $bloqueos[] = $this->bloqueo('edad_max', "{$producto->nombre} es hasta los {$producto->edad_max} años.");
        }

        if ($sexo && $producto->sexo && strtoupper($producto->sexo) !== $sexo) {
            $bloqueos[] = $this->bloqueo('sexo', "{$producto->nombre} no corresponde al sexo registrado del paciente.");
        }

        return $bloqueos;
    }

    public function edad(?string $fechaNac, CarbonInterface $referencia): ?int
    {
        if (! $fechaNac) {
            return null;
        }

        try {
            // Carbon 3: diffInYears es con signo y decimal.
            return max(0, (int) floor(Carbon::parse($fechaNac)->startOfDay()->diffInYears($referencia)));
        } catch (\Throwable) {
            return null;
        }
    }

    private function lineaAutomatica(string $sku, string $nombre, string $tipo, int $precio): array
    {
        return [
            'id' => null,
            'sku' => $sku,
            'nombre' => $nombre,
            'tipo' => $tipo,
            'cantidad' => 1,
            'precio_lista' => $precio,
            'precio_unitario' => $precio,
            'subtotal' => $precio,
            'automatica' => true,
            'requiere_orden_medica' => false,
            'bloqueada' => false,
        ];
    }

    private function bloqueo(string $codigo, string $mensaje): array
    {
        return ['codigo' => $codigo, 'mensaje' => $mensaje];
    }
}
