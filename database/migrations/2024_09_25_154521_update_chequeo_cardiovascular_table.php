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
        Schema::table('chequeo_cardiovascular', function (Blueprint $table) {
            $table->string("fileStatus",10)->after('created_at');
            $table->string("fileName",250)->after('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('chequeo_cardiovascular', function (Blueprint $table) {
            // Eliminar los campos en caso de revertir la migración
            $table->dropColumn('fileStatus');
            $table->dropColumn('fileName');
        });
    }
};
