<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatClubHistory extends Model
{
    use HasFactory;

    protected $table = 'chat_club_history';

    protected $fillable = [
        'session_id',
        'club_email',
        'role',
        'message',
        'context_json',
    ];
}
