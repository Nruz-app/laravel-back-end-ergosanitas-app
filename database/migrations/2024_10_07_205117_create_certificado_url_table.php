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
        Schema::create('certificado_url', function (Blueprint $table) {
            $table->id();
            $table->string("rut_paciente",20);
            $table->string("url_pdf",250);
            $table->string("name_pdf",100);
            $table->string("titulo",100);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('certificado_url');
    }
};
