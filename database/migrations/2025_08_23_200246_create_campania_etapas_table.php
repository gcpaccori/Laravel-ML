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
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_fin')->nullable();
            $table->integer('cantidad_inicial')->nullable();
            $table->integer('cantidad_final')->nullable();
            $table->decimal('peso_promedio_gr', 8, 2)->nullable();
            $table->enum('estado', ['en_proceso', 'finalizada', 'cancelada'])->default('en_proceso');
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
