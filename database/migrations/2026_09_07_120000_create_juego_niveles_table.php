<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('juego_niveles', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 20); // 'clinico' (badge) | 'completitud' (barra de progreso)
            $table->string('slug', 30); // clase CSS del HTML de referencia
            $table->string('nombre', 30); // etiqueta visible en la carta
            $table->integer('valor_min'); // clinico: -1 a 100 | completitud: 0 a 5
            $table->integer('valor_max');
            $table->string('color_fondo', 10);
            $table->string('color_texto', 10);
            $table->unsignedTinyInteger('orden');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['tipo', 'slug']);
            $table->index(['tipo', 'valor_min', 'valor_max']);
        });

        // Siembra en el propio up() para que un entorno nuevo quede utilizable con migrate.
        // La banda -1/-1 es el centinela de "sin evaluar": mantiene el badge en la tabla,
        // asi los umbrales se retunean con un UPDATE sin tocar el SP ni el codigo PHP.
        $ahora = now();

        DB::table('juego_niveles')->insert([
            ['tipo' => 'clinico', 'slug' => 'sin_evaluar', 'nombre' => 'SIN EVALUAR', 'valor_min' => -1, 'valor_max' => -1, 'color_fondo' => '#f3f4f6', 'color_texto' => '#9ca3af', 'orden' => 0, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['tipo' => 'clinico', 'slug' => 'bajo', 'nombre' => 'BAJO', 'valor_min' => 0, 'valor_max' => 74, 'color_fondo' => '#fee2e2', 'color_texto' => '#dc2626', 'orden' => 1, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['tipo' => 'clinico', 'slug' => 'medio', 'nombre' => 'MEDIO', 'valor_min' => 75, 'valor_max' => 94, 'color_fondo' => '#fef3c7', 'color_texto' => '#b45309', 'orden' => 2, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['tipo' => 'clinico', 'slug' => 'alto', 'nombre' => 'ALTO', 'valor_min' => 95, 'valor_max' => 100, 'color_fondo' => '#dcfce7', 'color_texto' => '#15803d', 'orden' => 3, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['tipo' => 'completitud', 'slug' => 'inicial', 'nombre' => 'Inicial', 'valor_min' => 0, 'valor_max' => 2, 'color_fondo' => '#e5e7eb', 'color_texto' => '#6b7280', 'orden' => 1, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['tipo' => 'completitud', 'slug' => 'evaluado', 'nombre' => 'Evaluado', 'valor_min' => 3, 'valor_max' => 4, 'color_fondo' => '#e0e7ff', 'color_texto' => '#4f46e5', 'orden' => 2, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['tipo' => 'completitud', 'slug' => 'completo', 'nombre' => 'Completo', 'valor_min' => 5, 'valor_max' => 5, 'color_fondo' => '#dcfce7', 'color_texto' => '#15803d', 'orden' => 3, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('juego_niveles');
    }
};
