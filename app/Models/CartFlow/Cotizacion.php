<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;

/** SPEC 04 — Solicitud de cotizacion (operativos, empresas, comunas fuera de RM). */
class Cotizacion extends Model
{
    protected $table = 'cf_cotizaciones';

    protected $guarded = ['id'];

    protected $casts = [
        'personas' => 'integer',
        'fecha_tentativa' => 'date:Y-m-d',
    ];
}
