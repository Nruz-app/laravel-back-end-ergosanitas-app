<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC 06 — Chat comercial «Ergosanito».
 *
 * Historial de cada hilo, agrupado por session_id (no por paciente ni por club: este chat no
 * identifica a nadie). Los mensajes del visitante se guardan ya enmascarados ([RUT], [EMAIL],
 * [TELEFONO]) y los del asistente sin el marcador [DERIVAR]. No se guarda el contexto completo
 * del catálogo: basta su hash para saber con qué versión se respondió.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_comercial_history', function (Blueprint $table) {
            $table->id();
            $table->string('session_id', 64)->index();
            $table->string('role', 16); // user | assistant
            $table->text('message'); // del visitante: enmascarado; del asistente: sin [DERIVAR]
            $table->boolean('derivar')->default(false); // solo en filas assistant
            $table->string('contexto_hash', 64)->nullable(); // sha256 del contexto de catálogo usado
            $table->unsignedInteger('tokens_prompt')->nullable();
            $table->unsignedInteger('tokens_cacheados')->nullable(); // usage.prompt_tokens_details.cached_tokens
            $table->unsignedInteger('tokens_respuesta')->nullable();
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * Solo para rollback local de una tabla recién creada. Nunca se ejecuta en producción.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_comercial_history');
    }
};
