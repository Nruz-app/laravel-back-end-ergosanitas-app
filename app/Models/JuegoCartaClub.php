<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class JuegoCartaClub extends Model
{
    use HasFactory;
    // Sin $table: este modelo solo envuelve al procedimiento, no mapea una tabla.

    /**
     * Devuelve una fila con la columna resultado_json: el array de cartas del club.
     * $club en null quita el filtro de club (lo usa el detalle por rut).
     */
    public static function SP_juego_cartas_club($search, $club)
    {
        return DB::select('CALL SP_juego_cartas_club(?, ?)', [$search, $club]);
    }
}
