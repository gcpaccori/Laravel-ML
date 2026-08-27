<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Horarios de alimentación diaria (columnas J,K,L,M del Excel: 08:00,
     * 11:00, 14:00, 17:00). Configurables por tabla porque cada campaña
     * puede repartir el alimento en distinta cantidad de tomas al día.
     * El consumo diario se reparte en partes iguales entre los horarios
     * activos de la tabla al momento de mostrar/exportar (no se guarda
     * duplicado por horario).
     */
    public function up(): void
    {
        Schema::create('alimentacion_horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alimentacion_tabla_id')->constrained('alimentacion_tablas')->cascadeOnDelete();
            $table->time('hora');
            $table->unsignedTinyInteger('orden')->default(1);
            $table->timestamps();
            $table->unique(['alimentacion_tabla_id', 'hora']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alimentacion_horarios');
    }
};
