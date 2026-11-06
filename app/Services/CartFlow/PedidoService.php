<?php

namespace App\Services\CartFlow;

use App\Models\CartFlow\Cotizacion;
use App\Models\CartFlow\Pedido;

/**
 * SPEC 04 — Consulta publica de pedidos (con token_consulta) y solicitudes de cotizacion.
 */
class PedidoService
{
    /** Pedido por numero y token; sin el token correcto no existe (evita enumerar pedidos). */
    public function buscar(string $numero, ?string $token): Pedido
    {
        $pedido = $token
            ? Pedido::where('numero', $numero)->where('token_consulta', $token)->first()
            : null;

        if (! $pedido) {
            throw CartFlowException::noEncontrado('Pedido no encontrado');
        }

        return $pedido;
    }

    public function serializar(Pedido $pedido): array
    {
        $pedido->load(['pacientes.lineas', 'pacientes.ordenesMedicas', 'pagos']);

        return [
            'numero' => $pedido->numero,
            'estado' => $pedido->estado,
            'medio_pago' => $pedido->medio_pago,
            'estado_pago' => $pedido->estado_pago,
            'comuna' => $pedido->comuna,
            'zona' => $pedido->zona,
            'direccion' => $pedido->direccion,
            'referencia' => $pedido->referencia,
            'plan' => $pedido->plan,
            'agenda' => [
                'fecha' => $pedido->fecha,
                'bloque' => $pedido->bloque,
                'requiere_manana' => $pedido->requiere_manana,
            ],
            'desglose' => [
                'subtotal_lab' => $pedido->subtotal_lab,
                'subtotal_servicios' => $pedido->subtotal_servicios,
                'toma_muestra' => $pedido->toma_muestra,
                'insumos' => $pedido->insumos,
                'cargo_domicilio' => $pedido->cargo_domicilio,
                'descuento_plan' => $pedido->descuento_plan,
                'total_sin_redondeo' => $pedido->total_sin_redondeo,
                'ajuste_redondeo' => $pedido->ajuste_redondeo,
                'total' => $pedido->total,
            ],
            // Solo nombre y preparacion: el RUT y la fecha de nacimiento no vuelven al navegador.
            'pacientes' => $pedido->pacientes->map(fn ($p) => [
                'orden' => $p->orden,
                'nombre' => $p->nombre,
                'preparacion' => $p->preparacion,
                'orden_medica_adjunta' => $p->ordenesMedicas->isNotEmpty(),
                'lineas' => $p->lineas->map(fn ($l) => [
                    'sku' => $l->sku,
                    'nombre' => $l->nombre,
                    'tipo' => $l->tipo,
                    'cantidad' => $l->cantidad,
                    'precio_unitario' => $l->precio_unitario,
                    'subtotal' => $l->subtotal,
                    'automatica' => $l->automatica,
                ])->values()->all(),
            ])->values()->all(),
            'pagos' => $pedido->pagos->map(fn ($p) => [
                'medio' => $p->medio,
                'monto' => $p->monto,
                'estado' => $p->estado,
                'fecha' => $p->created_at?->toIso8601String(),
            ])->values()->all(),
            'creado' => $pedido->created_at?->toIso8601String(),
        ];
    }

    public function cotizar(array $datos): Cotizacion
    {
        return Cotizacion::create($datos + ['estado' => 'nueva']);
    }
}
