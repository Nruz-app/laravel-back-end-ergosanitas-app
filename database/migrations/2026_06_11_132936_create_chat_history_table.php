<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('chat_history', function (Blueprint $table) {
            $table->id();

            // Identificador del chat (por paciente)
            $table->string('patient_identifier')->index();
            // aquí guardas RUT o nombre normalizado

            // rol: user | assistant | system
            $table->string('role');

            // mensaje
            $table->longText('message');

            // opcional: JSON clínico usado en esa respuesta
            $table->longText('context_json')->nullable();

            $table->timestamps();

            $table->index(['patient_identifier', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_history');
    }
};
