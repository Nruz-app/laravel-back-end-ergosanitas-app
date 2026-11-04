<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SPEC 04 — Intento de pago de un pedido (Webpay, transferencia o efectivo). */
class Pago extends Model
{
    protected $table = 'cf_pagos';

    protected $guarded = ['id'];

    protected $hidden = ['payload', 'token', 'pedido_id'];

    protected $casts = [
        'monto' => 'integer',
        'response_code' => 'integer',
        'payload' => 'array',
    ];

    public function pedido(): BelongsTo
    {
        return $this->belongsTo(Pedido::class, 'pedido_id');
    }
}
