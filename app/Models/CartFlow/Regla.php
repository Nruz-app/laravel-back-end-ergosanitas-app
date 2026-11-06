<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;

/**
 * SPEC 04 — Regla de negocio del carro (clave + valor JSON). Gerencia cambia montos aqui sin deploy.
 * Los valores por defecto viven en CalculadoraCarroService::REGLAS_POR_DEFECTO.
 */
class Regla extends Model
{
    protected $table = 'cf_reglas';

    protected $fillable = ['clave', 'valor', 'descripcion'];

    protected $casts = ['valor' => 'json'];
}
