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
        Schema::create('chat_club_history', function (Blueprint $table) {
            $table->id();
            $table->string('session_id')->index();
            $table->string('club_email')->index();
            $table->string('role'); // user | assistant
            $table->longText('message');
            $table->longText('context_json')->nullable(); // JSON clínico usado en esa respuesta
            $table->timestamps();

            $table->index(['session_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chat_club_history');
    }
};
