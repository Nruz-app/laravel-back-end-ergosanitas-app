<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC 04 — Carro de servicios (servicio-cart-flow): catalogo.
 *
 * Zonas, comunas, productos (los 35 campos de productos.csv), componentes de perfiles y packs,
 * tramos de operativos, reglas de negocio y registro de cambios de visibilidad. Aditiva: no toca
 * las tablas del flujo anterior (servicios, agenda_horas, web_pay_info).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cf_zonas', function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger('zona')->unique();
            $table->unsignedInteger('cargo_servicio'); // domicilio de servicios no laboratorio
            $table->unsignedInteger('cargo_laboratorio'); // domicilio de laboratorio + toma de muestra
            $table->timestamps();
        });

        Schema::create('cf_comunas', function (Blueprint $table) {
            $table->id();
            $table->string('comuna', 100)->unique();
            $table->foreignId('zona_id')->nullable()->constrained('cf_zonas'); // null = fuera de RM
            $table->unsignedInteger('km_ida_vuelta')->nullable();
            $table->boolean('a_cotizar')->default(false);
            $table->boolean('comuna_lejana_chequeo_cv')->default(false);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('cf_productos', function (Blueprint $table) {
            $table->id();
            $table->string('sku', 60)->unique(); // clave propia: el codigo Fonasa se repite
            $table->string('tipo', 30); // servicio_domicilio, examen_lab, perfil_lab, pack_lab, servicio_evento, operativo, a_cotizar, toma_muestra
            $table->string('categoria', 120);
            $table->string('nombre', 255);
            $table->string('codigo_fonasa', 20)->nullable();
            $table->unsignedInteger('costo_domilab')->nullable(); // interno, nunca sale por la API
            $table->unsignedInteger('precio')->nullable(); // CLP con IVA
            $table->unsignedInteger('precio_te_cuida')->nullable();
            $table->unsignedInteger('precio_familiar')->nullable();
            $table->unsignedInteger('precio_adulto_mayor')->nullable();
            $table->unsignedTinyInteger('edad_min_beneficio')->nullable();
            $table->string('agrega_automatico', 60)->nullable(); // SKU que el carro suma solo (TRUTROL)
            $table->boolean('publicable')->default(false); // recomendacion del negocio
            $table->boolean('visible_tienda')->default(false); // interruptor del dueno: lo unico que mira la tienda
            $table->string('motivo_no_publicable', 255)->nullable();
            $table->boolean('es_laboratorio')->default(false);
            $table->boolean('incluye_toma_muestra')->default(false);
            $table->boolean('requiere_orden_medica')->default(false);
            $table->boolean('requiere_ayuno')->default(false);
            $table->unsignedTinyInteger('horas_ayuno')->nullable();
            $table->boolean('solo_ruta_manana')->default(false);
            $table->boolean('solo_domicilio')->default(false);
            $table->boolean('solo_operativo')->default(false);
            $table->unsignedTinyInteger('edad_min')->nullable();
            $table->unsignedTinyInteger('edad_max')->nullable();
            $table->string('sexo', 1)->nullable(); // F, M o null
            $table->boolean('aplica_plan')->default(false);
            $table->boolean('proteger_luz')->default(false);
            $table->boolean('muestra_orina')->default(false);
            $table->boolean('muestra_deposicion')->default(false);
            $table->text('descripcion_publica')->nullable();
            $table->text('preparacion')->nullable();
            $table->text('nota_interna')->nullable(); // interno, nunca sale por la API
            $table->text('observacion')->nullable();
            $table->timestamps();

            $table->index(['visible_tienda', 'tipo']);
            $table->index('categoria');
        });

        Schema::create('cf_producto_componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pack_id')->constrained('cf_productos');
            $table->foreignId('componente_id')->constrained('cf_productos');
            $table->unsignedSmallInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['pack_id', 'componente_id']);
        });

        Schema::create('cf_operativo_tramos', function (Blueprint $table) {
            $table->id();
            $table->string('sku_operativo', 60);
            $table->unsignedInteger('pacientes_min');
            $table->unsignedInteger('pacientes_max')->nullable();
            $table->unsignedInteger('precio_rm')->nullable();
            $table->unsignedInteger('precio_rm_comuna_lejana')->nullable();
            $table->unsignedInteger('precio_v_region_ohiggins')->nullable();
            $table->unsignedInteger('precio_otras_regiones')->nullable();
            $table->string('nota', 255)->nullable();
            $table->timestamps();

            $table->index('sku_operativo');
        });

        Schema::create('cf_reglas', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 80)->unique();
            $table->json('valor');
            $table->string('descripcion', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('cf_visibilidad_log', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('cf_productos');
            $table->string('user_email', 150);
            $table->boolean('anterior');
            $table->boolean('nuevo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cf_visibilidad_log');
        Schema::dropIfExists('cf_reglas');
        Schema::dropIfExists('cf_operativo_tramos');
        Schema::dropIfExists('cf_producto_componentes');
        Schema::dropIfExists('cf_productos');
        Schema::dropIfExists('cf_comunas');
        Schema::dropIfExists('cf_zonas');
    }
};
