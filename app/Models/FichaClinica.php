<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FichaClinica extends Model
{
    use HasFactory;
    //protected $table = 'ficha_clinica';

    public static function SP_ficha_clinica($param1) {
        return DB::select('CALL SP_ficha_clinica(?)', [$param1]);
    }
}
