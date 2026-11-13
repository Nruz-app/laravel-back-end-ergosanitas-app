<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC 07 — Registro de consultas y descargas de certificados por RUT.
 *
 * Tabla aditiva de solo inserción (Especificación legal §7.4: visualización y descarga de
 * documentos). No guarda nombre, datos clínicos ni la URL firmada. Ningún endpoint la lee,
 * la edita ni la borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificado_descargas', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('rut', 20); // RUT normalizado (sin puntos, k minúscula)
            $table->string('tipo', 20); // consulta | certificado_ecg | certificado_bio
            $table->string('ip', 45); // IPv4 o IPv6
            $table->string('resultado', 20); // encontrado | sin_resultados | ok | no_encontrado | error
            $table->timestamp('created_at')->useCurrent();

            $table->index('rut');
        });
    }

    /**
     * Reversible en local y en staging. En producción se niega: la tabla guarda trazabilidad
     * (Ley 21.719) y un rollback la perdería.
     */
    public function down(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('No se revierte certificado_descargas en producción: se perdería trazabilidad.');
        }

        Schema::dropIfExists('certificado_descargas');
    }
};
