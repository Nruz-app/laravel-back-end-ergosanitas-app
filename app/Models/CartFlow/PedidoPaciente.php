<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** SPEC 04 — Paciente del pedido con su preparacion consolidada. */
class PedidoPaciente extends Model
{
    protected $table = 'cf_pedido_pacientes';

    protected $guarded = ['id'];

    protected $hidden = ['pedido_id', 'created_at', 'updated_at'];

    protected $casts = [
        'fecha_nac' => 'date:Y-m-d',
        'consentimiento' => 'boolean',
        'consentimiento_apoderado' => 'boolean',
        'preparacion' => 'array',
    ];

    public function lineas(): HasMany
    {
        return $this->hasMany(PedidoLinea::class, 'pedido_paciente_id')->orderBy('id');
    }

    public function ordenesMedicas(): HasMany
    {
        return $this->hasMany(PedidoOrdenMedica::class, 'pedido_paciente_id');
    }
}
