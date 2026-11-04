<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** SPEC 04 — Comuna. Sin zona (zona_id null) o con a_cotizar = true el carro no cobra en linea. */
class Comuna extends Model
{
    protected $table = 'cf_comunas';

    protected $fillable = ['comuna', 'zona_id', 'km_ida_vuelta', 'a_cotizar', 'comuna_lejana_chequeo_cv', 'activo'];

    protected $casts = [
        'zona_id' => 'integer',
        'km_ida_vuelta' => 'integer',
        'a_cotizar' => 'boolean',
        'comuna_lejana_chequeo_cv' => 'boolean',
        'activo' => 'boolean',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    public function zona(): BelongsTo
    {
        return $this->belongsTo(Zona::class, 'zona_id');
    }

    /** Fuera de RM, sin zona o marcada a cotizar: solo se ofrece "Solicitar cotizacion". */
    public function esACotizar(): bool
    {
        return $this->a_cotizar || $this->zona_id === null;
    }
}
