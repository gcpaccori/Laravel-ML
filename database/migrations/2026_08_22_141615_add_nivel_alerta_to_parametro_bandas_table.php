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
        Schema::table('parametro_bandas', function (Blueprint $table) {
            // Severidad de la banda. 'normal' = rango óptimo, no genera alarma.
            $table->enum('nivel', ['normal', 'advertencia', 'critico', 'emergencia'])->default('normal')->after('title');
            // Permite desactivar una banda sin borrarla (ej. mientras se calibra un umbral)
            $table->boolean('alerta')->default(true)->after('nivel');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('parametro_bandas', function (Blueprint $table) {
            $table->dropColumn(['nivel', 'alerta']);
        });
    }
};
