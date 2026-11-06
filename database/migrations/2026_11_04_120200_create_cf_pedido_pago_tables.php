<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC 04 — Carro de servicios: pedido (snapshot inmutable del carro), ordenes medicas, pagos
 * y solicitudes de cotizacion. Un pedido nunca se borra: se anula (estado = anulado).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cf_pedidos', function (Blueprint $table) {
            $table->id();
            $table->string('numero', 20)->unique(); // ES-AAAA-NNNNNN
            $table->uuid('token_consulta')->unique(); // requisito para consultar o pagar sin login
            $table->foreignId('carrito_id')->constrained('cf_carritos');
            $table->string('comuna', 100);
            $table->unsignedTinyInteger('zona')->nullable();
            $table->string('direccion', 255);
            $table->string('referencia', 255)->nullable();
            $table->string('plan', 20)->default('ninguno');
            $table->unsignedInteger('subtotal_lab')->default(0);
            $table->unsignedInteger('subtotal_servicios')->default(0);
            $table->unsignedInteger('toma_muestra')->default(0);
            $table->unsignedInteger('insumos')->default(0);
            $table->unsignedInteger('cargo_domicilio')->default(0);
            $table->unsignedInteger('descuento_plan')->default(0);
            $table->unsignedInteger('total_sin_redondeo')->default(0);
            $table->unsignedInteger('ajuste_redondeo')->default(0);
            $table->unsignedInteger('total');
            $table->date('fecha');
            $table->foreignId('bloque_id')->constrained('cf_bloques_agenda');
            $table->string('bloque', 20); // "07:30-10:00", copiado para el historico
            $table->boolean('requiere_manana')->default(false);
            $table->string('estado', 20)->default('pendiente_pago'); // pendiente_pago | pagado | confirmado | atendido | anulado
            $table->string('medio_pago', 20); // webpay | transferencia | efectivo
            $table->string('estado_pago', 20)->default('pendiente'); // pendiente | pagado | rechazado
            $table->string('contacto_nombre', 250);
            $table->string('contacto_email', 150);
            $table->string('contacto_telefono', 20);
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index(['fecha', 'bloque_id', 'estado']);
        });

        Schema::create('cf_pedido_pacientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('cf_pedidos');
            $table->unsignedSmallInteger('orden')->default(1);
            $table->string('nombre', 250);
            $table->string('rut', 20);
            $table->date('fecha_nac');
            $table->string('sexo', 1)->nullable();
            $table->string('prevision', 50)->nullable();
            $table->string('telefono', 20)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('consentimiento')->default(false);
            $table->string('apoderado_nombre', 250)->nullable();
            $table->string('apoderado_rut', 20)->nullable();
            $table->boolean('consentimiento_apoderado')->default(false);
            $table->json('preparacion')->nullable(); // preparacion consolidada (la mas exigente)
            $table->timestamps();
        });

        Schema::create('cf_pedido_lineas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_paciente_id')->constrained('cf_pedido_pacientes');
            $table->string('sku', 60);
            $table->string('nombre', 255);
            $table->string('tipo', 30);
            $table->unsignedInteger('precio_unitario');
            $table->unsignedSmallInteger('cantidad');
            $table->unsignedInteger('subtotal');
            $table->boolean('automatica')->default(false); // toma de muestra / TRUTROL agregados por el carro
            $table->timestamps();
        });

        Schema::create('cf_pedido_ordenes_medicas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_paciente_id')->constrained('cf_pedido_pacientes');
            $table->string('path', 255); // disco privado "local", nunca public/
            $table->string('mime', 100);
            $table->string('nombre_original', 255);
            $table->timestamps();
        });

        Schema::create('cf_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('cf_pedidos');
            $table->string('medio', 20);
            $table->unsignedInteger('monto');
            $table->string('buy_order', 26)->nullable()->unique(); // Transbank admite hasta 26 caracteres
            $table->string('token', 255)->nullable()->unique();
            $table->string('status', 30)->nullable();
            $table->integer('response_code')->nullable();
            $table->string('authorization_code', 20)->nullable();
            $table->json('payload')->nullable();
            $table->string('estado', 20)->default('iniciado'); // iniciado | aprobado | rechazado | error
            $table->timestamps();
        });

        Schema::create('cf_cotizaciones', function (Blueprint $table) {
            $table->id();
            $table->string('organizacion', 250)->nullable();
            $table->string('comuna', 100);
            $table->unsignedInteger('personas')->nullable();
            $table->string('servicio', 255);
            $table->string('sku', 60)->nullable();
            $table->date('fecha_tentativa')->nullable();
            $table->string('contacto_nombre', 250);
            $table->string('contacto_email', 150);
            $table->string('contacto_telefono', 20);
            $table->text('mensaje')->nullable();
            $table->string('estado', 20)->default('nueva'); // nueva | contactada | cerrada
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cf_cotizaciones');
        Schema::dropIfExists('cf_pagos');
        Schema::dropIfExists('cf_pedido_ordenes_medicas');
        Schema::dropIfExists('cf_pedido_lineas');
        Schema::dropIfExists('cf_pedido_pacientes');
        Schema::dropIfExists('cf_pedidos');
    }
};
