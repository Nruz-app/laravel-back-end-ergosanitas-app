<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC 07 — Análisis de IA de la bioimpedancia, persistido.
 *
 * El certificado de bioimpedancia llamaba a OpenAI en cada descarga. Ahora el análisis se guarda
 * la primera vez y se reutiliza mientras los datos enviados a la IA (payload_hash) no cambien.
 * Si la bioimpedancia se recarga, el hash cambia, se genera un análisis nuevo y el anterior se
 * conserva como histórico: nada se sobrescribe ni se borra.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bioimpedancia_analisis_ia', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('bioimpedancia_id'); // id de bioimpedancia, sin FK en cascada
            $table->string('rut', 20);
            $table->char('payload_hash', 64); // sha256 del JSON enviado a la IA
            $table->string('modelo', 40);
            $table->json('analisis');
            $table->timestamps();

            $table->unique(['bioimpedancia_id', 'payload_hash'], 'bio_analisis_ia_bio_hash_unique');
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
            throw new RuntimeException('No se revierte bioimpedancia_analisis_ia en producción: se perdería trazabilidad.');
        }

        Schema::dropIfExists('bioimpedancia_analisis_ia');
    }
};
