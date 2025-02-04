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
        Schema::create('agenda_horas', function (Blueprint $table) {
            $table->id();
            $table->string("nombre_paciente",250);
            $table->string("rut_paciente",20);
            $table->string("edad_paciente",3);
            $table->string("direccion_paciente",250);
            $table->string("email_paciente",50);
            $table->string("celular_paciente",20);
            $table->string("sexo_paciente",15);
            $table->unsignedBigInteger('servicios_id');
            $table->string("comuna_paciente",50);
			$table->string("pagado_paciente",20);
            $table->string("fecha_reserva_paciente",50);
            $table->foreign('servicios_id')->references('id')->on('servicios')->onDelete('cascade'); 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agenda_horas');
    }
};
