<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JuegoNivel extends Model
{
    use HasFactory;

    protected $table = 'juego_niveles';

    protected $fillable = [
        'tipo',
        'slug',
        'nombre',
        'valor_min',
        'valor_max',
        'color_fondo',
        'color_texto',
        'orden',
        'activo',
    ];
}
