<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Detalle semanal de la tabla de alimentación (filas 7-26 del Excel).
     *
     * Columnas de ENTRADA (las llena el usuario en el formulario):
     *  - ganancia_peso_g      -> columna B (Ganancia peso g)
     *  - tasa_alimentacion_porcentaje -> columna E (T.A %)
     *
     * Columnas CALCULADAS por el backend (no se editan a mano):
     *  - poblacion_calculada  -> columna C
     *  - biomasa_kg           -> columna D (B en Kg)
     *  - consumo_diario_kg    -> columna F
     *  - consumo_semanal_kg   -> columna G
     *
     * Fórmulas de referencia (ver AlimentacionBftService):
     *  poblacion(1)   = poblacion_inicial
     *  poblacion(n)   = poblacion(n-1) - (poblacion_inicial * mortalidad% / (numero_semanas-1))
     *  biomasa_kg     = ganancia_peso_g * poblacion_calculada / 1000
     *  consumo_diario_kg  = biomasa_kg * (tasa_alimentacion_porcentaje / 100)
     *  consumo_semanal_kg = consumo_diario_kg * 7
     */
    public function up(): void
    {
        Schema::create('alimentacion_semanas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alimentacion_mes_id')->constrained('alimentacion_mes')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero_semana'); // correlativo global: 1..N
            // Entradas del formulario
            $table->decimal('ganancia_peso_g', 8, 3);
            $table->decimal('tasa_alimentacion_porcentaje', 5, 2);
            // Resultados calculados por el backend
            $table->decimal('poblacion_calculada', 10, 2)->nullable();
            $table->decimal('biomasa_kg', 12, 3)->nullable();
            $table->decimal('consumo_diario_kg', 12, 4)->nullable();
            $table->decimal('consumo_semanal_kg', 12, 3)->nullable();
            $table->timestamps();
            $table->unique(['alimentacion_mes_id', 'numero_semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alimentacion_semanas');
    }
};
