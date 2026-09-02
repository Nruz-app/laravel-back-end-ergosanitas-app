<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatSessions extends Model
{
    use HasFactory;
    protected $table = 'chat_sessions';

    protected $fillable = [
        'session_id',
        'patient_identifier',
    ];
}
