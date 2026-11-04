<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;

/** SPEC 04 — Precio de un operativo por tramo de cantidad de pacientes (referencia para cotizar). */
class OperativoTramo extends Model
{
    protected $table = 'cf_operativo_tramos';

    protected $guarded = ['id'];
}
