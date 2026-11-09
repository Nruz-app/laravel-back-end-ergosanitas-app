<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SPEC 06 — Turno del chat comercial «Ergosanito».
 *
 * El mensaje del visitante se guarda ya enmascarado y el del asistente sin el marcador [DERIVAR].
 */
class ChatComercialHistory extends Model
{
    use HasFactory;

    protected $table = 'chat_comercial_history';

    protected $fillable = [
        'session_id',
        'role',
        'message',
        'derivar',
        'contexto_hash',
        'tokens_prompt',
        'tokens_cacheados',
        'tokens_respuesta',
    ];

    protected $casts = [
        'derivar' => 'boolean',
        'tokens_prompt' => 'integer',
        'tokens_cacheados' => 'integer',
        'tokens_respuesta' => 'integer',
    ];
}
