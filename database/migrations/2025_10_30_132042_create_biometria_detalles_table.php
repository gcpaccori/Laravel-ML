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
        Schema::create('biometria_detalles', function (Blueprint $table) {
            $table->id();

            // Relación con la biometría principal
            $table->foreignId('biometria_id')->constrained('biometrias');

            // Número o código de muestra (opcional)
            $table->integer('numero_muestra')->nullable();

            // Datos de la muestra individual
            $table->decimal('tamanio_cm', 8, 4)->nullable();
            $table->decimal('peso_gr', 8, 4)->nullable();

            // Si deseas registrar fecha/hora exacta del muestreo individual
            $table->date('fecha_registro')->nullable();

            // Observación individual (opcional)
            $table->text('observacion')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices para consultas rápidas
            $table->index(['biometria_id', 'numero_muestra']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('biometria_detalles');
    }
};
