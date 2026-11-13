<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SPEC 07 — Análisis de IA de una bioimpedancia, guardado para no volver a llamar a OpenAI
 * en cada descarga del certificado.
 */
class BioimpedanciaAnalisisIa extends Model
{
    protected $table = 'bioimpedancia_analisis_ia';

    protected $fillable = [
        'bioimpedancia_id',
        'rut',
        'payload_hash',
        'modelo',
        'analisis',
    ];

    protected $casts = [
        'analisis' => 'array',
    ];
}
