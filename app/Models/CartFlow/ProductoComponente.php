<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;

/** SPEC 04 — Componente (examen) de un perfil o pack. */
class ProductoComponente extends Model
{
    protected $table = 'cf_producto_componentes';

    protected $fillable = ['pack_id', 'componente_id', 'orden'];
}
