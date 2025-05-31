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
        Schema::create('chequeo_cardiovascular', function (Blueprint $table) {
            $table->id();
            $table->string("nombre",250);
            $table->string("rut",20);
            $table->string("edad",3);
            $table->string("estatura",10);
            $table->string("peso",10);
            $table->string("hemoglucotest",3);
            $table->string("pulso",3);
            $table->string("presionArterial",15);
            $table->string("saturacionOxigeno",15);
            $table->string("temperatura",5);
            $table->string("imc",4);
            $table->string("enfermedadesCronicas",250);
            $table->string("medicamentosDiarios",250);
            $table->string("sistemaOsteoarticular",250);
            $table->string("sistemaCardiovascular",250);
            $table->string("enfermedadesAnteriores",250);
            $table->string("Recuperacion",250);
            $table->string("gradoIncidenciaPosterio",250);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chequeo_cardiovascular');
    }
};
