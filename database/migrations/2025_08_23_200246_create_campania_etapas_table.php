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
        // Campaña_Etapa (una campaña tiene varias etapas)
        // Desarrollo de etapas en piscinas
        Schema::create('campania_etapas', function (Blueprint $table) {
            $table->id();

            $table->foreignId('campania_especie_id')->constrained('campania_especies');
            $table->foreignId('etapa_id')->constrained();
            $table->foreignId('piscina_id')->constrained();

            $table->decimal('area_piscigranja_m2', 10, 2)->nullable();
            $table->decimal('volumen_piscigranja_m3', 10, 2)->nullable();
            $table->decimal('altura_piscigranja_m', 5, 2)->nullable();

            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();

            $table->integer('numero_peces_inicial')->nullable();
            $table->integer('numero_peces_final')->nullable();
            $table->decimal('peso_inicial_gr', 8, 2)->nullable();
            $table->decimal('peso_final_gr', 8, 2)->nullable();
            $table->decimal('densidad_siembra', 10, 4)->nullable(); // Peces/Volumen

            $table->enum('estado', ['planificada', 'en_proceso', 'finalizada', 'cancelada'])->default('planificada');

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campania_etapas');
    }
};
