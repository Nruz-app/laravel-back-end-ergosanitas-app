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
        Schema::create('bioimpedancia', function (Blueprint $table) {
            $table->id();

            // paciente
            $table->string('rut');
            $table->string('nombre')->nullable();
            $table->string('sexo')->nullable();
            $table->integer('edad')->nullable();
            $table->float('estatura_cm')->nullable();

            // medición base
            $table->float('peso_kg')->nullable();
            $table->float('imc')->nullable();
            $table->float('grasa_corporal_pct')->nullable();
            $table->float('masa_muscular_kg')->nullable();
            $table->float('agua_corporal_pct')->nullable();

            // metabolismo
            $table->integer('tasa_metabolica_basal_kcal')->nullable();
            $table->float('grasa_visceral')->nullable();
            $table->integer('edad_corporal')->nullable();
            $table->float('smi')->nullable();

            // control
            $table->float('peso_objetivo_kg')->nullable();
            $table->float('control_peso_kg')->nullable();

            // metadata
            $table->string('archivo')->nullable();
            $table->text('observaciones')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bioimpedancia');
    }
};
