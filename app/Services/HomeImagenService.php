<?php

namespace App\Services;

use App\Models\HomeImagen;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Imagenes del Home comercial (SPEC 03): listado, alta con archivo, edicion, activar/ocultar
 * y borrado de fila + archivo. Los archivos van a public/HomeImagenes/ con $file->move(),
 * el mismo patron del logo (UserController::FileUpload), no al disco de storage.
 */
class HomeImagenService
{
    /**
     * Imagenes ordenadas por seccion (promocion, alianza, galeria), orden e id.
     * Con $soloActivas solo salen las visibles en el Home publico.
     */
    public function listar(bool $soloActivas): Collection
    {
        return HomeImagen::query()
            ->when($soloActivas, fn ($query) => $query->where('activo', true))
            ->orderByRaw("FIELD(seccion, 'promocion', 'alianza', 'galeria')")
            ->orderBy('orden')
            ->orderBy('id')
            ->get();
    }

    /**
     * D3 de la spec: las escrituras exigen un user_email de perfil Administrador.
     * No es autenticacion (el email viaja en el request), pero cierra la puerta a un
     * borrado anonimo.
     */
    public function esAdministrador(?string $userEmail): bool
    {
        if (! $userEmail) {
            return false;
        }

        return DB::table('users_metadata as um')
            ->join('perfiles as p', 'p.id', '=', 'um.perfiles_id')
            ->where('um.user_email', $userEmail)
            ->where('p.nombre', 'Administrador')
            ->exists();
    }

    /**
     * Guarda el archivo en public/HomeImagenes/ y crea la fila.
     * Si no viene orden, va al final de su seccion (MAX(orden) + 1).
     */
    public function crear(UploadedFile $file, array $datos): HomeImagen
    {
        $orden = $datos['orden'] ?? null;

        if ($orden === null) {
            $orden = (int) HomeImagen::where('seccion', $datos['seccion'])->max('orden') + 1;
        }

        $archivo = $this->nombreArchivo($file, $datos['seccion'], $datos['caption'] ?? null, $datos['alt']);

        $file->move(public_path(HomeImagen::CARPETA), $archivo);

        try {
            return HomeImagen::create([
                'seccion' => $datos['seccion'],
                'archivo' => $archivo,
                'alt' => $datos['alt'],
                'caption' => $datos['caption'] ?? null,
                'activo' => true,
                'orden' => $orden,
            ]);
        } catch (\Throwable $e) {
            // Sin fila, el archivo recien movido quedaria huerfano.
            File::delete(public_path(HomeImagen::CARPETA.DIRECTORY_SEPARATOR.$archivo));
            throw $e;
        }
    }

    /** Edita seccion, alt, caption y orden. El archivo no se cambia (fuera de alcance). */
    public function actualizar(HomeImagen $imagen, array $datos): HomeImagen
    {
        $imagen->fill([
            'seccion' => $datos['seccion'],
            'alt' => $datos['alt'],
            'caption' => $datos['caption'] ?? null,
            'orden' => $datos['orden'] ?? $imagen->orden,
        ])->save();

        return $imagen;
    }

    public function cambiarActivo(HomeImagen $imagen, bool $activo): HomeImagen
    {
        $imagen->activo = $activo;
        $imagen->save();

        return $imagen;
    }

    /**
     * Borra primero la fila y despues el archivo. Si el archivo ya no existe o no se puede
     * borrar, se deja un warning y la operacion se da por buena: un huerfano no rompe nada.
     */
    public function borrar(HomeImagen $imagen): void
    {
        $ruta = $imagen->rutaArchivo();

        $imagen->delete();

        if (! File::exists($ruta)) {
            Log::warning('HomeImagen: el archivo a borrar no existe', ['ruta' => $ruta]);

            return;
        }

        if (! File::delete($ruta)) {
            Log::warning('HomeImagen: no se pudo borrar el archivo', ['ruta' => $ruta]);
        }
    }

    /**
     * {seccion}-{slug de caption o alt}-{uniqid}.{ext}. Nunca se usa el nombre del cliente;
     * la extension sale del mime real (guessExtension), no de la que declara el cliente.
     */
    private function nombreArchivo(UploadedFile $file, string $seccion, ?string $caption, string $alt): string
    {
        $base = Str::limit(Str::slug($caption ?: $alt), 60, '') ?: 'imagen';
        $extension = $file->guessExtension() ?: strtolower($file->getClientOriginalExtension());

        return $seccion.'-'.$base.'-'.uniqid().'.'.$extension;
    }
}
