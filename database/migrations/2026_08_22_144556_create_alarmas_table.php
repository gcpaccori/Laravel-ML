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
        Schema::create('alarmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piscigranja_id')->constrained();
            $table->foreignId('piscina_id')->nullable()->constrained();
            $table->string('modulo', 100); // ['calidad_agua', calidad_ambiente, 'iot_sensores', 'equipos','insumos', 'produccion', 'inteligencia']
            $table->string('parametro')->nullable(); // temperatura, ph, od, nh3...
            $table->enum('nivel', ['normal', 'advertencia', 'critico', 'emergencia'])->default('advertencia');
            $table->decimal('valor_detectado', 10, 3)->nullable();
            $table->string('titulo');
            $table->text('mensaje')->nullable();
            $table->enum('estado', ['activa', 'resuelta'])->default('activa');
            $table->foreignId('resuelta_por')->nullable()->constrained('users');
            $table->timestamp('resuelta_en')->nullable();
            $table->timestamps();

            $table->index(['piscigranja_id', 'estado']);
            $table->index(['piscina_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('alarmas');
    }
};
