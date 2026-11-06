<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;

/** SPEC 04 — Linea del pedido con el precio copiado al momento del checkout. */
class PedidoLinea extends Model
{
    protected $table = 'cf_pedido_lineas';

    protected $guarded = ['id'];

    protected $hidden = ['pedido_paciente_id', 'created_at', 'updated_at'];

    protected $casts = [
        'precio_unitario' => 'integer',
        'cantidad' => 'integer',
        'subtotal' => 'integer',
        'automatica' => 'boolean',
    ];
}
