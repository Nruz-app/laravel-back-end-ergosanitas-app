<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * SPEC 05 — Mantenedor del carro: historial de cambios de estado del pedido y dos columnas
 * informativas en cf_pedidos. Aditiva: no modifica datos existentes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cf_pedido_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_id')->constrained('cf_pedidos');
            $table->string('user_email', 150);
            $table->string('campo', 20); // estado | estado_pago
            $table->string('anterior', 20)->nullable();
            $table->string('nuevo', 20);
            $table->string('motivo', 500)->nullable();
            $table->timestamps();

            $table->index(['pedido_id', 'created_at']);
        });

        Schema::table('cf_pedidos', function (Blueprint $table) {
            $table->string('anulado_motivo', 500)->nullable()->after('estado_pago');
            $table->timestamp('atendido_en')->nullable()->after('anulado_motivo');
        });
    }

    public function down(): void
    {
        Schema::table('cf_pedidos', function (Blueprint $table) {
            $table->dropColumn(['anulado_motivo', 'atendido_en']);
        });

        Schema::dropIfExists('cf_pedido_historial');
    }
};
