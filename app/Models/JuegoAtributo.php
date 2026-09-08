<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JuegoAtributo extends Model
{
    use HasFactory;

    protected $table = 'juego_atributos';

    protected $fillable = [
        'slug',
        'nombre',
        'icono',
        'descripcion',
        'orden',
        'activo',
    ];
}
