<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** SPEC 04 — Carro anonimo identificado por uuid. Una sola direccion y comuna; plan para todo el carro. */
class Carrito extends Model
{
    protected $table = 'cf_carritos';

    public const PLANES = ['ninguno', 'te_cuida', 'familiar'];

    protected $fillable = ['uuid', 'comuna_id', 'direccion', 'referencia', 'plan', 'estado', 'expira_en'];

    protected $casts = [
        'comuna_id' => 'integer',
        'expira_en' => 'datetime',
    ];

    public function comuna(): BelongsTo
    {
        return $this->belongsTo(Comuna::class, 'comuna_id');
    }

    public function pacientes(): HasMany
    {
        return $this->hasMany(CarritoPaciente::class, 'carrito_id')->orderBy('orden')->orderBy('id');
    }

    public function estaAbierto(): bool
    {
        return $this->estado === 'abierto' && ($this->expira_en === null || $this->expira_en->isFuture());
    }
}
