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
        Schema::create('juego_atributos', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 30)->unique(); // corazon | vitalidad | composicion | resistencia
            $table->string('nombre', 40); // etiqueta visible en la carta
            $table->string('icono', 10); // emoji
            $table->string('descripcion', 200); // de que columnas sale el atributo
            $table->unsignedTinyInteger('orden');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        // Siembra en el propio up(): el front no debe hardcodear iconos ni etiquetas.
        // El SP calcula estos cuatro slugs; desactivar una fila la oculta, no la deja de calcular.
        $ahora = now();

        DB::table('juego_atributos')->insert([
            ['slug' => 'corazon', 'nombre' => 'Corazon', 'icono' => '❤️', 'descripcion' => 'Electrocardiograma y antecedente cardiovascular', 'orden' => 1, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['slug' => 'vitalidad', 'nombre' => 'Vitalidad', 'icono' => '🫁', 'descripcion' => 'Saturacion de oxigeno y presion arterial', 'orden' => 2, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['slug' => 'composicion', 'nombre' => 'Composicion', 'icono' => '💪', 'descripcion' => 'Indice de masa corporal y bioimpedancia', 'orden' => 3, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
            ['slug' => 'resistencia', 'nombre' => 'Resistencia', 'icono' => '🛡️', 'descripcion' => 'Hemoglucotest y antecedentes generales', 'orden' => 4, 'activo' => true, 'created_at' => $ahora, 'updated_at' => $ahora],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('juego_atributos');
    }
};
