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
        Schema::create('web_pay_info', function (Blueprint $table) {
            $table->id();
            $table->string("vci",5);
            $table->string("amount",20);
            $table->string("status",10);
            $table->string("buy_order",50);
            $table->string("session_id",50);
            $table->string("card_detail",10);
            $table->string("accounting_date",10);
            $table->string("transaction_date",250);
            $table->string("authorization_code",10);
            $table->string("payment_type_code",10);
            $table->string("response_code",10);
            $table->string("installments_number",10);
            $table->string("tokenWs",250);
            $table->string("rut_paciente",250);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_pay_info');
    }
};
