<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC 04 — Carro de servicios: bloques de agenda y carro (borrador anonimo por uuid).
 *
 * Pacientes y lineas del carro usan soft delete: "quitar" no borra filas (especificacion legal §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cf_bloques_agenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('zona_id')->nullable()->constrained('cf_zonas'); // null = todas las zonas
            $table->unsignedTinyInteger('dia_semana'); // ISO: 1 = lunes ... 7 = domingo
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->string('franja', 10); // manana | tarde
            $table->unsignedSmallInteger('cupo')->default(6); // visitas (direcciones) por bloque
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['dia_semana', 'activo']);
        });

        Schema::create('cf_carritos', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('comuna_id')->nullable()->constrained('cf_comunas');
            $table->string('direccion', 255)->nullable();
            $table->string('referencia', 255)->nullable();
            $table->string('plan', 20)->default('ninguno'); // ninguno | te_cuida | familiar
            $table->string('estado', 20)->default('abierto'); // abierto | convertido | expirado
            $table->timestamp('expira_en')->nullable();
            $table->timestamps();
        });

        Schema::create('cf_carrito_pacientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrito_id')->constrained('cf_carritos');
            $table->unsignedSmallInteger('orden')->default(1);
            $table->string('nombre', 250)->nullable();
            $table->string('rut', 20)->nullable();
            $table->date('fecha_nac')->nullable();
            $table->string('sexo', 1)->nullable(); // F | M
            $table->string('prevision', 50)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('consentimiento')->default(false);
            $table->string('apoderado_nombre', 250)->nullable();
            $table->string('apoderado_rut', 20)->nullable();
            $table->boolean('consentimiento_apoderado')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('cf_carrito_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carrito_paciente_id')->constrained('cf_carrito_pacientes');
            $table->foreignId('producto_id')->constrained('cf_productos');
            $table->unsignedSmallInteger('cantidad')->default(1);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cf_carrito_lineas');
        Schema::dropIfExists('cf_carrito_pacientes');
        Schema::dropIfExists('cf_carritos');
        Schema::dropIfExists('cf_bloques_agenda');
    }
};
