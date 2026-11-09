<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * SPEC 06 — Hilo del chat comercial «Ergosanito» de un visitante anónimo del Home.
 *
 * La memoria es por session_id (UUID que el front guarda en localStorage), nunca por IP.
 */
class ChatComercialSessions extends Model
{
    use HasFactory;

    protected $table = 'chat_comercial_sessions';

    protected $fillable = [
        'session_id',
        'mensajes',
        'ultimo_mensaje_at',
    ];

    protected $casts = [
        'mensajes' => 'integer',
        'ultimo_mensaje_at' => 'datetime',
    ];
}
