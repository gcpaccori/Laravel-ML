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
        Schema::create('campanias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piscigranja_id')->constrained();
            $table->string('nombre', 150); // Ej: "Campaña Tilapia 2025-I"
            $table->string('tipo_pescado', 100);
            $table->integer('cantidad_siembra')->nullable();
            $table->date('fecha_inicio')->nullable();
            $table->date('fecha_cosecha_estimada')->nullable();
            $table->date('fecha_cosecha_real')->nullable();
            $table->decimal('peso_promedio_gr', 8, 2)->nullable();
            $table->integer('cantidad_cosechada')->nullable();
            $table->decimal('mortalidad_porcentaje', 5, 2)->nullable();
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
        Schema::dropIfExists('campanias');
    }
};
