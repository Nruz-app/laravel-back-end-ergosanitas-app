<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Bioimpedancia extends Model
{
    use HasFactory;
    protected $table = 'bioimpedancia';
    protected $guarded = [];

    public static function SP_bioempdacia_rut($rut_paciente) {
        return DB::select('CALL SP_bioimpedacia_rut(?)', [$rut_paciente]);
    }

}
