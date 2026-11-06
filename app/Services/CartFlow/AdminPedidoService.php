<?php

namespace App\Services\CartFlow;

use App\Models\CartFlow\Cotizacion;
use App\Models\CartFlow\Pago;
use App\Models\CartFlow\Pedido;
use App\Models\CartFlow\PedidoOrdenMedica;
use App\Models\CartFlow\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

/**
 * SPEC 05 — Control del carro para el Administrador: resumen, pedidos y pagos, gestión de
 * estados con historial, enlace firmado a la orden médica, cotizaciones y catálogo completo.
 *
 * Nada se borra: un pedido se anula con motivo y cada cambio queda en cf_pedido_historial.
 */
class AdminPedidoService
{
    public const POR_PAGINA = 20;

    public const ACCIONES = ['confirmar_pago', 'confirmar', 'atender', 'anular'];

    public const ESTADOS_COTIZACION = ['nueva', 'contactada', 'cerrada'];

    /** Minutos que dura el enlace a una orden médica. */
    public const MINUTOS_ENLACE = 5;

    public function resumen(): array
    {
        $hoy = Carbon::today();
        $inicioMes = $hoy->copy()->startOfMonth();

        $porEstado = Pedido::query()->selectRaw('estado, COUNT(*) as n')->groupBy('estado')->pluck('n', 'estado');

        $pendientes = Pedido::where('estado', 'pendiente_pago')
            ->selectRaw('medio_pago, COUNT(*) as n, COALESCE(SUM(total), 0) as monto')
            ->groupBy('medio_pago')
            ->get()
            ->mapWithKeys(fn ($f) => [$f->medio_pago => ['cantidad' => (int) $f->n, 'monto' => (int) $f->monto]]);

        $pagosMes = Pago::where('estado', 'aprobado')->where('updated_at', '>=', $inicioMes);

        return [
            'pedidos_hoy' => Pedido::where('created_at', '>=', $hoy)->count(),
            'pendientes_pago' => [
                'cantidad' => (int) $pendientes->sum('cantidad'),
                'monto' => (int) $pendientes->sum('monto'),
                'por_medio' => $pendientes,
            ],
            'pagados_mes' => [
                'cantidad' => (clone $pagosMes)->distinct('pedido_id')->count('pedido_id'),
                'monto' => (int) (clone $pagosMes)->sum('monto'),
            ],
            'por_estado' => collect(Pedido::ESTADOS)->mapWithKeys(fn ($e) => [$e => (int) ($porEstado[$e] ?? 0)]),
            'proximas_visitas' => Pedido::whereNotIn('estado', ['anulado', 'atendido'])
                ->whereBetween('fecha', [$hoy->toDateString(), $hoy->copy()->addDays(7)->toDateString()])
                ->count(),
            'cotizaciones_nuevas' => Cotizacion::where('estado', 'nueva')->count(),
        ];
    }

    /** @return array{items: array, total: int, pagina: int, por_pagina: int, ultima_pagina: int} */
    public function listar(array $filtros): array
    {
        $campoFecha = ($filtros['por'] ?? 'creacion') === 'visita' ? 'fecha' : 'created_at';

        $query = Pedido::query()
            ->withCount('pacientes')
            ->when($filtros['estado'] ?? null, fn (Builder $q, $v) => $q->where('estado', $v))
            ->when($filtros['estado_pago'] ?? null, fn (Builder $q, $v) => $q->where('estado_pago', $v))
            ->when($filtros['medio_pago'] ?? null, fn (Builder $q, $v) => $q->where('medio_pago', $v))
            ->when($filtros['desde'] ?? null, fn (Builder $q, $v) => $q->where($campoFecha, '>=', $campoFecha === 'fecha' ? $v : Carbon::parse($v)->startOfDay()))
            ->when($filtros['hasta'] ?? null, fn (Builder $q, $v) => $q->where($campoFecha, '<=', $campoFecha === 'fecha' ? $v : Carbon::parse($v)->endOfDay()))
            ->when(trim((string) ($filtros['q'] ?? '')), function (Builder $q, $texto) {
                $like = '%'.mb_strtolower($texto).'%';
                $rut = '%'.strtoupper(str_replace('.', '', $texto)).'%';

                $q->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(numero) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(contacto_nombre) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(contacto_email) LIKE ?', [$like])
                    ->orWhere('contacto_telefono', 'like', '%'.$texto.'%')
                    ->orWhereHas('pacientes', fn (Builder $p) => $p
                        ->where('rut', 'like', $rut)
                        ->orWhereRaw('LOWER(nombre) LIKE ?', [$like])));
            })
            ->orderByDesc('id');

        $pagina = $query->paginate(self::POR_PAGINA, ['*'], 'page', max(1, (int) ($filtros['page'] ?? 1)));

        return [
            'items' => collect($pagina->items())->map(fn (Pedido $p) => [
                'numero' => $p->numero,
                'creado' => $p->created_at?->toIso8601String(),
                'fecha' => $p->fecha,
                'bloque' => $p->bloque,
                'comuna' => $p->comuna,
                'contacto_nombre' => $p->contacto_nombre,
                'contacto_telefono' => $p->contacto_telefono,
                'total' => $p->total,
                'medio_pago' => $p->medio_pago,
                'estado' => $p->estado,
                'estado_pago' => $p->estado_pago,
                'pacientes' => $p->pacientes_count,
            ])->all(),
            'total' => $pagina->total(),
            'pagina' => $pagina->currentPage(),
            'por_pagina' => $pagina->perPage(),
            'ultima_pagina' => $pagina->lastPage(),
        ];
    }

    public function buscar(string $numero): Pedido
    {
        $pedido = Pedido::where('numero', $numero)->first();

        if (! $pedido) {
            throw CartFlowException::noEncontrado('Pedido no encontrado');
        }

        return $pedido;
    }

    /** Detalle completo para el Administrador: incluye RUT y fecha de nacimiento; pagos sin token ni payload. */
    public function detalle(Pedido $pedido): array
    {
        $pedido->load(['pacientes.lineas', 'pacientes.ordenesMedicas', 'pagos', 'historial']);

        return [
            'numero' => $pedido->numero,
            'creado' => $pedido->created_at?->toIso8601String(),
            'estado' => $pedido->estado,
            'estado_pago' => $pedido->estado_pago,
            'medio_pago' => $pedido->medio_pago,
            'anulado_motivo' => $pedido->anulado_motivo,
            'atendido_en' => $pedido->atendido_en?->toIso8601String(),
            'acciones' => $this->accionesPermitidas($pedido),
            'contacto' => [
                'nombre' => $pedido->contacto_nombre,
                'email' => $pedido->contacto_email,
                'telefono' => $pedido->contacto_telefono,
            ],
            'visita' => [
                'comuna' => $pedido->comuna,
                'zona' => $pedido->zona,
                'direccion' => $pedido->direccion,
                'referencia' => $pedido->referencia,
                'fecha' => $pedido->fecha,
                'bloque' => $pedido->bloque,
                'requiere_manana' => $pedido->requiere_manana,
            ],
            'plan' => $pedido->plan,
            'observaciones' => $pedido->observaciones,
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
            'pacientes' => $pedido->pacientes->map(fn ($p) => [
                'orden' => $p->orden,
                'nombre' => $p->nombre,
                'rut' => $p->rut,
                'fecha_nac' => $p->fecha_nac?->format('Y-m-d'),
                'sexo' => $p->sexo,
                'prevision' => $p->prevision,
                'telefono' => $p->telefono,
                'email' => $p->email,
                'apoderado_nombre' => $p->apoderado_nombre,
                'apoderado_rut' => $p->apoderado_rut,
                'preparacion' => $p->preparacion,
                'lineas' => $p->lineas->map(fn ($l) => [
                    'sku' => $l->sku,
                    'nombre' => $l->nombre,
                    'tipo' => $l->tipo,
                    'cantidad' => $l->cantidad,
                    'precio_unitario' => $l->precio_unitario,
                    'subtotal' => $l->subtotal,
                    'automatica' => $l->automatica,
                ])->values()->all(),
                'ordenes_medicas' => $p->ordenesMedicas->map(fn ($o) => [
                    'id' => $o->id,
                    'nombre_original' => $o->nombre_original,
                    'mime' => $o->mime,
                    'subida' => $o->created_at?->toIso8601String(),
                ])->values()->all(),
            ])->values()->all(),
            'pagos' => $pedido->pagos->map(fn (Pago $p) => [
                'id' => $p->id,
                'medio' => $p->medio,
                'monto' => $p->monto,
                'estado' => $p->estado,
                'buy_order' => $p->buy_order,
                'status' => $p->status,
                'response_code' => $p->response_code,
                'authorization_code' => $p->authorization_code,
                'creado' => $p->created_at?->toIso8601String(),
                'actualizado' => $p->updated_at?->toIso8601String(),
            ])->values()->all(),
            'historial' => $pedido->historial->map(fn ($h) => [
                'campo' => $h->campo,
                'anterior' => $h->anterior,
                'nuevo' => $h->nuevo,
                'motivo' => $h->motivo,
                'user_email' => $h->user_email,
                'fecha' => $h->created_at?->toIso8601String(),
            ])->values()->all(),
        ];
    }

    /** Acciones que el estado actual permite (las usa el front para mostrar solo los botones válidos). */
    public function accionesPermitidas(Pedido $p): array
    {
        $acciones = [];
        $manual = in_array($p->medio_pago, ['transferencia', 'efectivo'], true);

        if ($p->estado === 'pendiente_pago' && $manual) {
            $acciones[] = 'confirmar_pago';
        }

        if ($p->estado === 'pagado') {
            $acciones[] = 'confirmar';
        }

        if ($p->estado === 'confirmado' || ($p->medio_pago === 'efectivo' && in_array($p->estado, ['pendiente_pago', 'pagado'], true))) {
            $acciones[] = 'atender';
        }

        if (! in_array($p->estado, ['atendido', 'anulado'], true)) {
            $acciones[] = 'anular';
        }

        return $acciones;
    }

    public function cambiarEstado(Pedido $pedido, string $accion, string $userEmail, ?string $motivo): Pedido
    {
        if (! in_array($accion, $this->accionesPermitidas($pedido), true)) {
            throw CartFlowException::conflicto("No se puede «{$accion}» un pedido en estado {$pedido->estado} ({$pedido->medio_pago}).", 'transicion_invalida');
        }

        if ($accion === 'anular' && trim((string) $motivo) === '') {
            throw CartFlowException::validacion('Indica el motivo de la anulación.', ['motivo' => ['requerido']]);
        }

        return DB::transaction(function () use ($pedido, $accion, $userEmail, $motivo) {
            $pedido = Pedido::whereKey($pedido->id)->lockForUpdate()->first();

            match ($accion) {
                'confirmar_pago' => $this->marcarPagado($pedido, $userEmail, $motivo),
                'confirmar' => $this->cambiar($pedido, 'estado', 'confirmado', $userEmail, $motivo),
                'atender' => $this->atender($pedido, $userEmail, $motivo),
                'anular' => $this->anular($pedido, $userEmail, (string) $motivo),
            };

            return $pedido->fresh();
        });
    }

    private function marcarPagado(Pedido $pedido, string $userEmail, ?string $motivo): void
    {
        $pago = $pedido->pagos()->where('medio', $pedido->medio_pago)->latest('id')->first()
            ?? new Pago(['pedido_id' => $pedido->id, 'medio' => $pedido->medio_pago, 'monto' => $pedido->total]);

        $pago->fill(['estado' => 'aprobado', 'status' => 'MANUAL', 'monto' => $pedido->total])->save();

        $this->cambiar($pedido, 'estado_pago', 'pagado', $userEmail, $motivo);
        $this->cambiar($pedido, 'estado', 'pagado', $userEmail, $motivo);
    }

    private function atender(Pedido $pedido, string $userEmail, ?string $motivo): void
    {
        // Efectivo: el pago se recibe en la visita.
        if ($pedido->estado_pago !== 'pagado' && $pedido->medio_pago === 'efectivo') {
            $this->marcarPagado($pedido, $userEmail, 'Pago en efectivo recibido en la atención');
        }

        $pedido->update(['atendido_en' => now()]);
        $this->cambiar($pedido, 'estado', 'atendido', $userEmail, $motivo);
    }

    private function anular(Pedido $pedido, string $userEmail, string $motivo): void
    {
        $pedido->update(['anulado_motivo' => mb_substr(trim($motivo), 0, 500)]);
        $this->cambiar($pedido, 'estado', 'anulado', $userEmail, $motivo);
    }

    private function cambiar(Pedido $pedido, string $campo, string $nuevo, string $userEmail, ?string $motivo): void
    {
        $anterior = $pedido->{$campo};

        if ($anterior === $nuevo) {
            return;
        }

        $pedido->update([$campo => $nuevo]);

        $pedido->historial()->create([
            'user_email' => $userEmail,
            'campo' => $campo,
            'anterior' => $anterior,
            'nuevo' => $nuevo,
            'motivo' => $motivo !== null ? mb_substr(trim($motivo), 0, 500) : null,
        ]);
    }

    /** @return array{url: string, expira: string} */
    public function enlaceOrden(int $ordenId): array
    {
        $orden = PedidoOrdenMedica::find($ordenId);

        if (! $orden) {
            throw CartFlowException::noEncontrado('Orden médica no encontrada');
        }

        $expira = now()->addMinutes(self::MINUTOS_ENLACE);

        return [
            'url' => URL::temporarySignedRoute('CartFlowOrdenDescarga', $expira, ['id' => $orden->id]),
            'expira' => $expira->toIso8601String(),
        ];
    }

    public function orden(int $ordenId): ?PedidoOrdenMedica
    {
        return PedidoOrdenMedica::find($ordenId);
    }

    public function listarCotizaciones(?string $estado, ?string $q): array
    {
        return Cotizacion::query()
            ->when($estado, fn (Builder $b) => $b->where('estado', $estado))
            ->when(trim((string) $q), function (Builder $b, $texto) {
                $like = '%'.mb_strtolower($texto).'%';
                $b->where(fn (Builder $w) => $w
                    ->whereRaw('LOWER(organizacion) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(comuna) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(servicio) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(contacto_nombre) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(contacto_email) LIKE ?', [$like]));
            })
            ->orderByDesc('id')
            ->limit(500)
            ->get()
            ->all();
    }

    public function cambiarEstadoCotizacion(int $id, string $estado): Cotizacion
    {
        $cotizacion = Cotizacion::find($id);

        if (! $cotizacion) {
            throw CartFlowException::noEncontrado('Cotización no encontrada');
        }

        $cotizacion->update(['estado' => $estado]);

        return $cotizacion;
    }

    /** Todo el catálogo, también lo oculto, para el interruptor del mantenedor. Sin costos ($hidden). */
    public function listarProductos(?string $q, ?string $categoria, ?bool $visible): array
    {
        return Producto::query()
            ->when($categoria, fn (Builder $b) => $b->where('categoria', $categoria))
            ->when($visible !== null, fn (Builder $b) => $b->where('visible_tienda', $visible))
            ->when(trim((string) $q), fn (Builder $b, $texto) => $b->where(fn (Builder $w) => $w
                ->whereRaw('LOWER(nombre) LIKE ?', ['%'.mb_strtolower($texto).'%'])
                ->orWhere('sku', 'like', '%'.strtoupper($texto).'%')))
            ->orderBy('categoria')
            ->orderBy('nombre')
            ->get(['id', 'sku', 'tipo', 'categoria', 'nombre', 'precio', 'publicable', 'visible_tienda', 'motivo_no_publicable', 'observacion'])
            ->map(fn (Producto $p) => $p->makeHidden('solo_cotizacion'))
            ->all();
    }
}
