<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ChequeoCardiovascular extends Model
{
    use HasFactory;

    protected $table = 'chequeo_cardiovascular';

    public static function SP_estadistica_IMC($param1) {
        return DB::select('CALL SP_estadistica_IMC(?)', [$param1]);
    }

    public static function SP_estado_general($param1) {
        return DB::select('CALL SP_estado_general(?)', [$param1]);
    }


    public static function sp_estadistica_presion($param1) {
        return DB::select('CALL sp_estadistica_presion(?)', [$param1]);
    }

    public static function SP_estadistica_hemoglucotest($param1) {
        return DB::select('CALL SP_estadistica_hemoglucotest(?)', [$param1]);
    }

    public static function SP_estadistica_saturacion($param1) {
        return DB::select('CALL SP_estadistica_saturacion(?)', [$param1]);
    }
}
