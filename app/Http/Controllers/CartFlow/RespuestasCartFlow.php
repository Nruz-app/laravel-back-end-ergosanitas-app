<?php

namespace App\Http\Controllers\CartFlow;

use App\Services\CartFlow\CartFlowException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * SPEC 04 — Sobre {success, message, data} / {success:false, message, error} del carro.
 *
 * Validacion con Validator::make y no $request->validate(): el front no envia
 * "Accept: application/json" y validate() responderia con un redirect (mismo criterio que la Spec 03).
 * bootstrap/app.php no tiene handler global, asi que cada accion pasa por responder().
 */
trait RespuestasCartFlow
{
    /** Ejecuta la accion y traduce CartFlowException y errores inesperados al sobre. */
    protected function responder(string $errorMensaje, callable $accion): JsonResponse
    {
        try {
            return $accion();
        } catch (CartFlowException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'error' => $e->error,
            ], $e->status);
        } catch (\Throwable $e) {
            Log::error('cart-flow: '.$errorMensaje, ['error' => $e->getMessage(), 'file' => $e->getFile().':'.$e->getLine()]);

            return response()->json([
                'success' => false,
                'message' => $errorMensaje,
                'error' => 'server_error',
            ], 500);
        }
    }

    protected function ok(mixed $data, string $mensaje = 'OK', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $mensaje, 'data' => $data], $status);
    }

    /** Valida o lanza 422 con el array de errores como `error`. */
    protected function validar(array $datos, array $reglas): array
    {
        $validador = Validator::make($datos, $reglas, [
            'required' => 'El campo :attribute es obligatorio.',
            'email' => 'El campo :attribute debe ser un correo válido.',
            'in' => 'El valor de :attribute no es válido.',
            'date' => 'El campo :attribute debe ser una fecha.',
            'integer' => 'El campo :attribute debe ser un número entero.',
            'max' => 'El campo :attribute es demasiado largo.',
            'mimes' => 'El archivo :attribute debe ser PDF, JPG o PNG.',
            'exists' => 'El valor de :attribute no existe.',
            'regex' => 'El formato de :attribute no es válido.',
        ]);

        if ($validador->fails()) {
            throw CartFlowException::validacion('Error de validación', $validador->errors()->all());
        }

        return $validador->validated();
    }
}
