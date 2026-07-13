<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biometrias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campania_etapa_id')->constrained('campania_etapas');
            $table->date('fecha_inicial');

            // Muestreo
            $table->date('fecha_muestreo');
            $table->integer('cantidad_muestreo')->nullable(); // cantidad de muestras
            $table->float('muestreo_porcentaje');
            $table->unsignedSmallInteger('tiempo_dias');
            $table->float('cantidad_peces_iniciales');
            $table->float('cantidad_peces_actuales');

            // Biometría
            $table->float('bi_kg')->comment('Biomasa inicial');
            $table->float('bf_kg')->comment('Biomasa final');
            $table->float('prom_longitud_cm');
            $table->float('prom_peso_g');
            $table->float('tasa_crecimiento_g_dia');
            $table->float('total_alimento_consumido_kg');
            $table->float('conversion_alimenticia');
            $table->float('tasa_supervivencia_porcentaje')->nullable();

            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('fecha_muestreo');
            $table->index('fecha_inicial');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometrias');
    }
};
