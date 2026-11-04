<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * SPEC 04 — Pedido: copia inmutable del carro al hacer checkout. Nunca se borra: se anula.
 * token_consulta no se serializa; el cliente lo recibe una sola vez, al crearlo.
 */
class Pedido extends Model
{
    protected $table = 'cf_pedidos';

    public const ESTADOS = ['pendiente_pago', 'pagado', 'confirmado', 'atendido', 'anulado'];

    public const MEDIOS_PAGO = ['webpay', 'transferencia', 'efectivo'];

    protected $guarded = ['id'];

    protected $hidden = ['token_consulta', 'carrito_id', 'updated_at'];

    protected $casts = [
        // fecha sin cast: se guarda y se lee como "AAAA-MM-DD" (con cast "date" SQLite guardaria la hora).
        'zona' => 'integer',
        'subtotal_lab' => 'integer',
        'subtotal_servicios' => 'integer',
        'toma_muestra' => 'integer',
        'insumos' => 'integer',
        'cargo_domicilio' => 'integer',
        'descuento_plan' => 'integer',
        'total_sin_redondeo' => 'integer',
        'ajuste_redondeo' => 'integer',
        'total' => 'integer',
        'requiere_manana' => 'boolean',
    ];

    public function pacientes(): HasMany
    {
        return $this->hasMany(PedidoPaciente::class, 'pedido_id')->orderBy('orden');
    }

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'pedido_id')->orderBy('id');
    }
}
