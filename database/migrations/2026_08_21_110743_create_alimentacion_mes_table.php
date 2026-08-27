<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrupación mensual (columnas H e I del Excel: "Tipo de Alimento" y
     * "Consumo mensual (Kg)"). El Excel solo llena estos dos valores en
     * la primera fila de cada bloque de 4 semanas -> aquí viven a nivel
     * de mes, y las semanas cuelgan de este registro.
     */
    public function up(): void
    {
        Schema::create('alimentacion_mes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alimentacion_tabla_id')->constrained('alimentacion_tablas')->cascadeOnDelete();
            $table->unsignedTinyInteger('numero_mes'); // 1, 2, 3...
            $table->string('tipo_alimento')->nullable(); // ej. "0.45mm", "Extruido 2mm"
            // Calculado: SUMA(consumo_semanal_kg) de las semanas del mes
            $table->decimal('consumo_mensual_kg', 12, 3)->nullable();
            $table->timestamps();
            $table->unique(['alimentacion_tabla_id', 'numero_mes']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alimentacion_mes');
    }
};
