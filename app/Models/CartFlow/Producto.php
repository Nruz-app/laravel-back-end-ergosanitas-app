<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * SPEC 04 — Producto del catalogo (servicio, examen, perfil, pack, evento, operativo...).
 *
 * costo_domilab y nota_interna son datos internos (costos y margenes): $hidden los saca de
 * toda serializacion para que nunca lleguen al cliente.
 */
class Producto extends Model
{
    protected $table = 'cf_productos';

    public const TIPOS = [
        'servicio_domicilio', 'examen_lab', 'perfil_lab', 'pack_lab',
        'servicio_evento', 'operativo', 'a_cotizar', 'toma_muestra',
    ];

    /** Tipos que nunca entran al carro: van al formulario de cotizacion. */
    public const TIPOS_COTIZACION = ['operativo', 'a_cotizar'];

    /** Tipos que suman al subtotal de laboratorio. */
    public const TIPOS_LABORATORIO = ['examen_lab', 'perfil_lab', 'pack_lab', 'toma_muestra'];

    protected $guarded = ['id'];

    protected $hidden = ['costo_domilab', 'nota_interna', 'created_at', 'updated_at', 'pivot'];

    protected $casts = [
        'costo_domilab' => 'integer',
        'precio' => 'integer',
        'precio_te_cuida' => 'integer',
        'precio_familiar' => 'integer',
        'precio_adulto_mayor' => 'integer',
        'edad_min_beneficio' => 'integer',
        'publicable' => 'boolean',
        'visible_tienda' => 'boolean',
        'es_laboratorio' => 'boolean',
        'incluye_toma_muestra' => 'boolean',
        'requiere_orden_medica' => 'boolean',
        'requiere_ayuno' => 'boolean',
        'horas_ayuno' => 'integer',
        'solo_ruta_manana' => 'boolean',
        'solo_domicilio' => 'boolean',
        'solo_operativo' => 'boolean',
        'edad_min' => 'integer',
        'edad_max' => 'integer',
        'aplica_plan' => 'boolean',
        'proteger_luz' => 'boolean',
        'muestra_orina' => 'boolean',
        'muestra_deposicion' => 'boolean',
    ];

    protected $appends = ['solo_cotizacion'];

    public function componentes(): BelongsToMany
    {
        return $this->belongsToMany(Producto::class, 'cf_producto_componentes', 'pack_id', 'componente_id')
            ->withPivot('orden')
            ->orderBy('cf_producto_componentes.orden');
    }

    public function scopeVisibles(Builder $query): Builder
    {
        return $query->where('visible_tienda', true);
    }

    /** No se agrega al carro: se pide por el formulario de cotizacion. */
    public function getSoloCotizacionAttribute(): bool
    {
        return in_array($this->tipo, self::TIPOS_COTIZACION, true) || (bool) $this->solo_operativo;
    }

    public function esLaboratorio(): bool
    {
        return in_array($this->tipo, self::TIPOS_LABORATORIO, true);
    }
}
