<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC 06 — Chat comercial «Ergosanito».
 *
 * Una fila por hilo de conversación de un visitante anónimo del Home. El session_id es el UUID
 * que el front guarda en localStorage (home_chat_session_id); por eso la memoria del chat es por
 * sesión y no por IP. No se guarda IP, user agent, email, nombre ni RUT.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_comercial_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->unique(); // UUID que el front guarda en localStorage
            $table->unsignedInteger('mensajes')->default(0); // turnos del visitante en este hilo
            $table->timestamp('ultimo_mensaje_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * Solo para rollback local de una tabla recién creada. Nunca se ejecuta en producción.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_comercial_sessions');
    }
};
