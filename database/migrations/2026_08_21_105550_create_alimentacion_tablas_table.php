<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cabecera de la Tabla de Alimentación BFT.
     * Cada registro representa UNA tabla de alimentación calculada
     * para una campaña_especie específica (una campaña puede tener
     * varias especies, cada una con su propia tabla de alimentación).
     */
    public function up(): void
    {
        Schema::create('alimentacion_tablas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campania_especie_id')->constrained('campania_especies')->cascadeOnDelete();
            $table->string('titulo')->nullable();
            $table->string('responsable')->nullable(); // ej. "ING. ROBERT HOYOS RIOS"
            // Parámetros base (equivalentes a B2 y B3 del Excel)
            $table->unsignedInteger('poblacion_inicial');
            $table->decimal('mortalidad_porcentaje', 5, 2);
            // Estructura de la tabla (permite tablas de distinta duración)
            $table->unsignedSmallInteger('numero_semanas')->default(20);
            $table->unsignedTinyInteger('semanas_por_mes')->default(4);
            $table->text('observaciones')->nullable();
            // true = valores calculados vigentes; false = pendiente de recalcular
            $table->boolean('calculado')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->unique('campania_especie_id'); // una tabla de alimentación por campaña_especie
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alimentacion_tablas');
    }
};
