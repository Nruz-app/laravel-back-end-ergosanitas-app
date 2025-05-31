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
        Schema::create('incidentes_deportivos', function (Blueprint $table) {
            $table->id();
            $table->string("nombres",250);
            $table->string("edad",5);
            $table->string("deporte",100);
            $table->string("tipo_lesion",100);
            $table->string("ubicacion",100);
            $table->string("parte_cuerpo",100);
            $table->string("descripcion",100);
            $table->string("primeros_auxilios",100);
            $table->string("gravedad",100);
            $table->string("estado",100);
            $table->string("user_email",100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('incidentes_deportivos');
    }
};
