<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PagoMensual extends Model
{
    use HasFactory;
    protected $table = 'pago_mensual';

    public static function AgendaMensual($param1) {
        return DB::select('CALL SP_agenda_mensual(?)', [$param1]);
    }
    public static function SP_estadistica_monto_mdc() {
        return DB::select('CALL SP_estadistica_monto_mdc()');
    }

    public static function SP_update_pago_mensual($periodo) {
        return DB::select('CALL SP_update_pago_mensual(?)', [$periodo]);
    }
    public static function SP_chequeos_prompt($search) {
        return DB::select('CALL SP_chequeos_prompt(?)', [$search]);
    }
}
