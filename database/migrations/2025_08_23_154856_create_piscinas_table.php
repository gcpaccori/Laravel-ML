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
        Schema::create('piscinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piscigranja_id')->constrained();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->decimal('superficie_m2', 8, 2)->nullable();
            $table->decimal('profundidad_m', 5, 2)->nullable();
            $table->decimal('volumen_m3', 10, 2)->nullable(); // Agregado basado en Excel
            $table->enum('estado', ['operativa', 'mantenimiento', 'inactiva'])->default('operativa');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('piscinas');
    }
};
