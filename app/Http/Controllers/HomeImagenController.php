<?php

namespace App\Http\Controllers;

use App\Models\HomeImagen;
use App\Services\HomeImagenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Mantenedor de imagenes del Home (SPEC 03). Sobre {success, message, data} /
 * {success: false, message, error}, el de JuegoCartasController.
 *
 * La validacion usa Validator::make y no $request->validate(): el front no envia
 * "Accept: application/json", y sin ese header un fallo de validate() responde con un
 * redirect en vez de un 422 con este sobre.
 */
class HomeImagenController extends Controller
{
    protected $homeImagenService;

    public function __construct(HomeImagenService $homeImagenService)
    {
        $this->homeImagenService = $homeImagenService;
    }

    /**
     * Publico: solo las activas, para el Home comercial.
     */
    public function Index()
    {
        return $this->listar(true);
    }

    /**
     * Mantenedor: todas, activas e inactivas.
     */
    public function Admin()
    {
        return $this->listar(false);
    }

    /**
     * Sube una imagen (multipart). 201 con la imagen creada.
     */
    public function Store(Request $request)
    {
        try {
            if ($denegado = $this->exigirAdministrador($request)) {
                return $denegado;
            }

            $validador = Validator::make($request->all(), [
                'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
                ...$this->reglasMetadatos(),
            ], $this->mensajes());

            if ($validador->fails()) {
                return $this->errorValidacion($validador->errors()->all());
            }

            $imagen = $this->homeImagenService->crear($request->file('file'), $validador->validated());

            return response()->json([
                'success' => true,
                'message' => 'Imagen agregada correctamente',
                'data' => $imagen->fresh(),
            ], 201);
        } catch (\Exception $e) {
            return $this->errorServidor('Error agregando la imagen', $e);
        }
    }

    /**
     * Edita seccion, alt, caption y orden. El archivo no cambia.
     */
    public function Update(Request $request, int $id)
    {
        try {
            if ($denegado = $this->exigirAdministrador($request)) {
                return $denegado;
            }

            $imagen = HomeImagen::find($id);

            if (! $imagen) {
                return $this->noEncontrada();
            }

            $validador = Validator::make($request->all(), $this->reglasMetadatos(), $this->mensajes());

            if ($validador->fails()) {
                return $this->errorValidacion($validador->errors()->all());
            }

            $imagen = $this->homeImagenService->actualizar($imagen, $validador->validated());

            return response()->json([
                'success' => true,
                'message' => 'Imagen actualizada correctamente',
                'data' => $imagen,
            ]);
        } catch (\Exception $e) {
            return $this->errorServidor('Error actualizando la imagen', $e);
        }
    }

    /**
     * Activa u oculta. Es PUT y no PATCH porque el ApiAdapter del front no tiene patch (D7).
     */
    public function UpdateActivo(Request $request, int $id)
    {
        try {
            if ($denegado = $this->exigirAdministrador($request)) {
                return $denegado;
            }

            $imagen = HomeImagen::find($id);

            if (! $imagen) {
                return $this->noEncontrada();
            }

            $validador = Validator::make($request->all(), [
                'activo' => ['required', 'boolean'],
            ], $this->mensajes());

            if ($validador->fails()) {
                return $this->errorValidacion($validador->errors()->all());
            }

            $imagen = $this->homeImagenService->cambiarActivo($imagen, $request->boolean('activo'));

            return response()->json([
                'success' => true,
                'message' => $imagen->activo ? 'Imagen visible en el Home' : 'Imagen oculta del Home',
                'data' => $imagen,
            ]);
        } catch (\Exception $e) {
            return $this->errorServidor('Error cambiando la visibilidad de la imagen', $e);
        }
    }

    /**
     * Borra la fila y el archivo. user_email viaja por query string.
     */
    public function Destroy(Request $request, int $id)
    {
        try {
            if ($denegado = $this->exigirAdministrador($request)) {
                return $denegado;
            }

            $imagen = HomeImagen::find($id);

            if (! $imagen) {
                return $this->noEncontrada();
            }

            $this->homeImagenService->borrar($imagen);

            return response()->json([
                'success' => true,
                'message' => 'Imagen eliminada correctamente',
                'data' => null,
            ]);
        } catch (\Exception $e) {
            return $this->errorServidor('Error eliminando la imagen', $e);
        }
    }

    private function listar(bool $soloActivas): JsonResponse
    {
        try {
            $imagenes = $this->homeImagenService->listar($soloActivas);

            return response()->json([
                'success' => true,
                'message' => 'Imagenes obtenidas correctamente',
                'data' => $imagenes,
            ]);
        } catch (\Exception $e) {
            return $this->errorServidor('Error obteniendo las imagenes', $e);
        }
    }

    /**
     * D3: 403 si el user_email (body o query) no es de un Administrador.
     */
    private function exigirAdministrador(Request $request): ?JsonResponse
    {
        $userEmail = $request->input('user_email', $request->query('user_email'));

        if ($this->homeImagenService->esAdministrador(is_string($userEmail) ? $userEmail : null)) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Solo un Administrador puede modificar las imagenes del Home',
            'error' => 'forbidden',
        ], 403);
    }

    /** Reglas comunes de alta y edicion. */
    private function reglasMetadatos(): array
    {
        return [
            'seccion' => ['required', Rule::in(HomeImagen::SECCIONES)],
            'alt' => ['required', 'string', 'max:255'],
            'caption' => ['nullable', 'string', 'max:150'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ];
    }

    private function mensajes(): array
    {
        return [
            'file.required' => 'Debe adjuntar una imagen',
            'file.mimes' => 'La imagen debe ser JPG, PNG o WEBP',
            'file.max' => 'La imagen no puede superar los 5 MB',
            'seccion.required' => 'La seccion es obligatoria',
            'seccion.in' => 'La seccion debe ser promocion, alianza o galeria',
            'alt.required' => 'El texto alternativo es obligatorio',
            'alt.max' => 'El texto alternativo no puede superar los 255 caracteres',
            'caption.max' => 'El titulo no puede superar los 150 caracteres',
            'orden.integer' => 'El orden debe ser un numero entero',
            'orden.min' => 'El orden no puede ser negativo',
            'activo.required' => 'Debe indicar si la imagen esta activa',
            'activo.boolean' => 'El campo activo debe ser verdadero o falso',
        ];
    }

    private function errorValidacion(array $errores): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $errores[0] ?? 'Datos invalidos',
            'error' => $errores,
        ], 422);
    }

    private function noEncontrada(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'La imagen no existe',
            'error' => 'not_found',
        ], 404);
    }

    private function errorServidor(string $mensaje, \Exception $e): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $mensaje,
            'error' => $e->getMessage(),
        ], 500);
    }
}
