<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * SPEC 07 — Registro de una consulta o descarga de certificados por RUT.
 *
 * Solo inserción: sin updated_at y sin borrado.
 */
class CertificadoDescarga extends Model
{
    protected $table = 'certificado_descargas';

    public const UPDATED_AT = null;

    public const TIPO_CONSULTA = 'consulta';

    public const TIPO_CERTIFICADO_ECG = 'certificado_ecg';

    public const TIPO_CERTIFICADO_BIO = 'certificado_bio';

    protected $fillable = [
        'rut',
        'tipo',
        'ip',
        'resultado',
    ];
}
