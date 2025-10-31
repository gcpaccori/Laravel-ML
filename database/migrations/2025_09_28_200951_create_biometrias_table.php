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
        Schema::create('biometrias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campania_etapa_id')->constrained('campania_etapas');
            $table->date('fecha_muestreo');
            $table->integer('cantidad_muestreo')->nullable(); // cantidad de muestras

            // Datos de cantidad
            $table->integer('cantidad_peces_inicial')->nullable();
            $table->integer('cantidad_peces_final')->nullable();

            // Datos de peso
            $table->decimal('peso_inicial_gr', 8, 4)->nullable();
            $table->decimal('peso_final_gr', 8, 4)->nullable();

            // Datos de tamaño
            $table->decimal('tamanio_inicial_cm', 8, 4)->nullable();
            $table->decimal('tamanio_final_cm', 8, 4)->nullable();

            // Biomasa
            $table->decimal('biomasa_inicial_kg', 8, 4)->nullable();
            $table->decimal('biomasa_final_kg', 8, 4)->nullable();

            // Indicadores
            $table->decimal('tasa_supervivencia_porcentaje', 8, 4)->nullable();
            $table->decimal('tasa_crecimiento_especifico_porcentaje', 8, 4)->nullable();

            $table->text('observaciones')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['campania_etapa_id', 'fecha_muestreo']);
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
