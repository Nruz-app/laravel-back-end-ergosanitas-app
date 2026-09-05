<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chat_club_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->unique(); // hilo de conversación
            $table->string('club_email')->index(); // = chequeo_cardiovascular.user_email
            $table->string('search_actual')->nullable(); // rut o nombre pegado a la sesión
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_club_sessions');
    }
};
