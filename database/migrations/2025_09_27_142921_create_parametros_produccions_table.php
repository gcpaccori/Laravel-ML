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
        Schema::create('parametros_produccions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campania_etapa_id')->constrained('campania_etapas');
            $table->integer('dias_alimentacion')->nullable();
            $table->integer('dias_muestreo')->nullable();
            $table->integer('numero_muestreos')->nullable();
            $table->decimal('cantidad_alimento_total_kg', 10, 2)->nullable();
            $table->decimal('racion_diaria_gr', 10, 6)->nullable();
            $table->integer('frecuencia_diaria')->default(3); // Número de veces al día
            $table->decimal('cantidad_por_frecuencia_gr', 10, 6)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametros_produccions');
    }
};
