<?php

namespace App\Models\CartFlow;

use Illuminate\Database\Eloquent\Model;

/** SPEC 04 — Bloque horario semanal con cupo de visitas. zona_id null = aplica a todas las zonas. */
class BloqueAgenda extends Model
{
    protected $table = 'cf_bloques_agenda';

    protected $fillable = ['zona_id', 'dia_semana', 'hora_inicio', 'hora_fin', 'franja', 'cupo', 'activo'];

    protected $casts = [
        'zona_id' => 'integer',
        'dia_semana' => 'integer',
        'cupo' => 'integer',
        'activo' => 'boolean',
    ];

    protected $hidden = ['created_at', 'updated_at'];

    /** "07:30-10:00" */
    public function etiqueta(): string
    {
        return substr((string) $this->hora_inicio, 0, 5).'-'.substr((string) $this->hora_fin, 0, 5);
    }
}
