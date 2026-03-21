<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class IncidentesDeportivos extends Model
{
    use HasFactory;

    protected $table = 'incidentes_deportivos';


    public static function sp_estadistica_liga($param1) {
        return DB::select('CALL SP_estadistica_liga(?)', [$param1]);
    }
    public static function sp_estadistica_categoria($param1) {
        return DB::select('CALL SP_estadistica_categoria(?)', [$param1]);
    }

    public static function sp_estadistica_lesiones($param1) {
        return DB::select('CALL SP_estadistica_lesiones(?)', [$param1]);
    }

    public static function sp_estadistica_parte_cuerpo($param1) {
        return DB::select('CALL SP_estadistica_parte_cuerpo(?)', [$param1]);
    }

     public static function sp_estadistica_lesiones_fechas($param1) {
        return DB::select('CALL SP_estadistica_lesiones_fechas(?)', [$param1]);
    }

}
