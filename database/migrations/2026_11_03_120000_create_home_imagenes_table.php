<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC 03 — Mantenedor de imagenes del Home.
 *
 * Guarda los metadatos de las imagenes de promociones, alianzas y galeria del Home comercial
 * del front (src/home-ergo/). Los archivos viven en public/HomeImagenes/ y la URL publica se
 * arma al responder con config('services.home_imagenes.url'): aqui solo va el nombre del archivo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('home_imagenes', function (Blueprint $table) {
            $table->id();
            $table->enum('seccion', ['promocion', 'alianza', 'galeria']); // reemplaza a "destacado" del JSON
            $table->string('archivo', 255); // solo el nombre del archivo, nunca la URL
            $table->string('alt', 255); // obligatorio: accesibilidad y SEO
            $table->string('caption', 150)->nullable(); // texto bajo la imagen y del mensaje de WhatsApp
            $table->boolean('activo')->default(true); // ocultar sin borrar
            $table->unsignedInteger('orden')->default(0); // orden dentro de la seccion
            $table->timestamps();

            $table->index(['seccion', 'activo', 'orden']);
        });

        // Siembra en el propio up(), como la Spec 02: las 24 entradas de home-promociones.json
        // (destacado true -> promocion, false -> alianza) y home-galeria.json, en su orden.
        // Los archivos de origen estan en resources/home-imagenes-semilla/.
        $ahora = now();

        $filas = [
            ['promocion', 'promo-mes-corazon-01.png', 'Promoción de agosto, mes del corazón, de Ergo SaniTas', 'Agosto, mes del corazón', true],
            ['promocion', 'promo-mes-corazon-02.png', 'Segunda promoción de agosto, mes del corazón, de Ergo SaniTas', 'Agosto, mes del corazón', true],
            ['promocion', 'promo-chequeo-cardiovascular.jpg', 'Promoción del chequeo cardiovascular deportivo de Ergo SaniTas', 'Chequeo cardiovascular deportivo', true],
            ['promocion', 'promo-chequeo-cardiologo-deportivo.png', 'Promoción del chequeo con cardiólogo deportivo de Ergo SaniTas', 'Chequeo con cardiólogo deportivo', true],
            ['promocion', 'promo-bioimpedancia-01.png', 'Oferta de bioimpedancia de Ergo SaniTas', 'Bioimpedancia', true],
            ['promocion', 'promo-bioimpedancia-02.png', 'Segunda oferta de bioimpedancia de Ergo SaniTas', 'Bioimpedancia', true],
            ['promocion', 'promo-holter-presion-arterial.png', 'Promoción del Holter de presión arterial de Ergo SaniTas', 'Holter de presión arterial', true],
            ['promocion', 'promo-laboratorio-fonasa.webp', 'Exámenes de laboratorio con convenio Fonasa de Ergo SaniTas', 'Exámenes de laboratorio con Fonasa', true],
            ['promocion', 'promo-laboratorio-toma-muestras.png', 'Laboratorio clínico y toma de muestras a domicilio de Ergo SaniTas', 'Laboratorio clínico y toma de muestras', true],
            ['promocion', 'promo-adulto-mayor.png', 'Servicios para el adulto mayor de Ergo SaniTas', 'Servicios para el adulto mayor', true],
            ['promocion', 'promo-pase-deportivo.jpg', 'Pase deportivo de Ergo SaniTas', 'Pase deportivo', true],
            ['alianza', 'alianza-chequeo-cardiovascular.png', 'Alianza de Ergo SaniTas para el chequeo cardiovascular deportivo', 'Alianza — Chequeo cardiovascular deportivo', true],
            ['alianza', 'alianza-ecg-domicilio.png', 'Alianza de Ergo SaniTas para el electrocardiograma a domicilio', 'Alianza — ECG a domicilio', true],
            ['alianza', 'alianza-bioimpedancia.png', 'Alianza de Ergo SaniTas para el examen de bioimpedancia', 'Alianza — Bioimpedancia', true],
            ['alianza', 'alianza-holter.png', 'Alianza de Ergo SaniTas para el examen Holter', 'Alianza — Holter', true],
            ['alianza', 'alianza-examen-laboratorio.png', 'Alianza de Ergo SaniTas para exámenes de laboratorio', 'Alianza — Exámenes de laboratorio', true],
            ['alianza', 'alianza-enfermeria.png', 'Alianza de Ergo SaniTas para el servicio de enfermería', 'Alianza — Enfermería', true],
            ['alianza', 'alianza-neurobionta.png', 'Alianza de Ergo SaniTas con Neurobionta', 'Alianza — Neurobionta', true],
            ['alianza', 'alianza-la-voz-san-bernardo.png', 'Alianza de Ergo SaniTas con La Voz de San Bernardo', 'Alianza — La Voz de San Bernardo', true],
            ['alianza', 'info-holter-instrucciones.jpg', 'Instrucciones para el paciente que usa un Holter de presión arterial', 'Instrucciones para el paciente — Holter de presión arterial', false],
            ['galeria', 'operativo-electrocardiograma-01.jpg', 'Profesional de Ergo SaniTas tomando un electrocardiograma en un operativo en terreno', 'Electrocardiogramas en terreno', true],
            ['galeria', 'operativo-electrocardiograma-02.jpg', 'Equipo de Ergo SaniTas realizando electrocardiogramas durante un operativo', 'Operativo de chequeo cardiovascular', true],
            ['galeria', 'operativo-primeros-auxilios.jpg', 'Personal de primeros auxilios de Ergo SaniTas cubriendo un evento deportivo', 'Primeros auxilios en eventos deportivos', true],
            ['galeria', 'respaldo-esteban-paredes.jpg', 'El futbolista Esteban Paredes junto al equipo de Ergo SaniTas', 'Esteban Paredes con el equipo de Ergo SaniTas', true],
        ];

        $orden = ['promocion' => 0, 'alianza' => 0, 'galeria' => 0];
        $insertar = [];

        foreach ($filas as [$seccion, $archivo, $alt, $caption, $activo]) {
            $insertar[] = [
                'seccion' => $seccion,
                'archivo' => $archivo,
                'alt' => $alt,
                'caption' => $caption,
                'activo' => $activo,
                'orden' => ++$orden[$seccion],
                'created_at' => $ahora,
                'updated_at' => $ahora,
            ];
        }

        DB::table('home_imagenes')->insert($insertar);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('home_imagenes');
    }
};
