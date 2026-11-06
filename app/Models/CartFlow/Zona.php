<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;

/** SPEC 04 — Zona de domicilio con sus dos cargos (servicio y laboratorio). */
class Zona extends Model
{
    protected $table = 'cf_zonas';

    protected $fillable = ['zona', 'cargo_servicio', 'cargo_laboratorio'];

    protected $casts = [
        'zona' => 'integer',
        'cargo_servicio' => 'integer',
        'cargo_laboratorio' => 'integer',
    ];

    protected $hidden = ['created_at', 'updated_at'];
}
