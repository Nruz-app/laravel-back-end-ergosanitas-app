<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** SPEC 04 — Paciente dentro de un carro. "Quitar" es soft delete: nunca se borra la fila. */
class CarritoPaciente extends Model
{
    use SoftDeletes;

    protected $table = 'cf_carrito_pacientes';

    protected $fillable = [
        'carrito_id', 'orden', 'nombre', 'rut', 'fecha_nac', 'sexo', 'prevision', 'telefono', 'email',
        'consentimiento', 'apoderado_nombre', 'apoderado_rut', 'consentimiento_apoderado',
    ];

    protected $casts = [
        'fecha_nac' => 'date:Y-m-d',
        'consentimiento' => 'boolean',
        'consentimiento_apoderado' => 'boolean',
    ];

    protected $hidden = ['carrito_id', 'created_at', 'updated_at', 'deleted_at'];

    public function carrito(): BelongsTo
    {
        return $this->belongsTo(Carrito::class, 'carrito_id');
    }

    public function lineas(): HasMany
    {
        return $this->hasMany(CarritoLinea::class, 'carrito_paciente_id')->orderBy('id');
    }
}
