<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

/**
 * Imagen del Home comercial del front (SPEC 03): promocion, alianza o foto de galeria.
 *
 * Solo guarda el nombre del archivo; el archivo vive en public/HomeImagenes/. La URL publica
 * se arma al serializar con config('services.home_imagenes.url'), para que un cambio de dominio
 * sea un cambio de .env y no un UPDATE masivo.
 */
class HomeImagen extends Model
{
    protected $table = 'home_imagenes';

    /** Valores validos de `seccion`, en el orden en que se listan. */
    public const SECCIONES = ['promocion', 'alianza', 'galeria'];

    /** Carpeta de public/ donde viven los archivos. */
    public const CARPETA = 'HomeImagenes';

    protected $fillable = [
        'seccion',
        'archivo',
        'alt',
        'caption',
        'activo',
        'orden',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /** La respuesta lleva `src` (URL absoluta) en lugar de `archivo` (nombre en disco). */
    protected $appends = ['src'];

    protected $hidden = ['archivo', 'created_at', 'updated_at'];

    protected function src(): Attribute
    {
        return Attribute::get(
            fn () => config('services.home_imagenes.url').'/'.rawurlencode($this->archivo)
        );
    }

    /** Ruta absoluta del archivo en disco. */
    public function rutaArchivo(): string
    {
        return public_path(self::CARPETA.DIRECTORY_SEPARATOR.$this->archivo);
    }
}
