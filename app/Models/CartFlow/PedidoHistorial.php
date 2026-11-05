<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;

/** SPEC 05 — Quién cambió el estado o el estado de pago de un pedido, cuándo y por qué. */
class PedidoHistorial extends Model
{
    protected $table = 'cf_pedido_historial';

    protected $fillable = ['pedido_id', 'user_email', 'campo', 'anterior', 'nuevo', 'motivo'];

    protected $hidden = ['pedido_id', 'updated_at'];
}
