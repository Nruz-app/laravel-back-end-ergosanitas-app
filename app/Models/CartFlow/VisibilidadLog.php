<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;

/** SPEC 04 — Quien y cuando cambio visible_tienda de un producto. */
class VisibilidadLog extends Model
{
    protected $table = 'cf_visibilidad_log';

    protected $fillable = ['producto_id', 'user_email', 'anterior', 'nuevo'];

    protected $casts = ['anterior' => 'boolean', 'nuevo' => 'boolean'];
}
