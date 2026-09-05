<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ChequeoClubPrompt extends Model
{
    use HasFactory;
    //protected $table = 'chequeo_club_prompt';

    public static function SP_chequeos_club_prompt($search, $club)
    {
        return DB::select('CALL SP_chequeos_club_prompt(?, ?)', [$search, $club]);
    }
}
