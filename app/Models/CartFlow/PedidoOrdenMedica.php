<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;

/** SPEC 04 — Orden medica subida en el checkout. Vive en el disco privado "local"; el path no se publica. */
class PedidoOrdenMedica extends Model
{
    protected $table = 'cf_pedido_ordenes_medicas';

    protected $guarded = ['id'];

    protected $hidden = ['path', 'pedido_paciente_id', 'updated_at'];
}
