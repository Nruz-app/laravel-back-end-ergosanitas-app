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

}
