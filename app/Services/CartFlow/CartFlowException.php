<?php

namespace App\Services\CartFlow;

/**
 * SPEC 04 — Error de negocio del carro con su codigo HTTP y el `error` del sobre.
 * Los controladores la traducen a {success:false, message, error} sin pasar por el 500.
 */
class CartFlowException extends \RuntimeException
{
    public function __construct(string $mensaje, public readonly int $status = 422, public readonly mixed $error = null)
    {
        parent::__construct($mensaje);
    }

    public static function noEncontrado(string $mensaje = 'Recurso no encontrado'): self
    {
        return new self($mensaje, 404, 'not_found');
    }

    public static function conflicto(string $mensaje, mixed $error = 'conflict'): self
    {
        return new self($mensaje, 409, $error);
    }

    public static function validacion(string $mensaje, mixed $error): self
    {
        return new self($mensaje, 422, $error);
    }
}
