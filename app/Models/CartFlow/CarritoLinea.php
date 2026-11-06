<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** SPEC 04 — Linea del carro (producto + cantidad) de un paciente. "Quitar" es soft delete. */
class CarritoLinea extends Model
{
    use SoftDeletes;

    protected $table = 'cf_carrito_lineas';

    protected $fillable = ['carrito_paciente_id', 'producto_id', 'cantidad'];

    protected $casts = ['cantidad' => 'integer'];

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(CarritoPaciente::class, 'carrito_paciente_id');
    }

    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class, 'producto_id');
    }
}
