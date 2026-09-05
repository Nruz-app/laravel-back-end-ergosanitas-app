<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatClubSessions extends Model
{
    use HasFactory;

    protected $table = 'chat_club_sessions';

    protected $fillable = [
        'session_id',
        'club_email',
        'search_actual',
    ];
}
