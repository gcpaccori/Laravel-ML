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
            $table->foreignId('biometria_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('numero')->nullable(); // n° de pez muestreado
            $table->float('peso_g');
            $table->float('longitud_cm');
            $table->timestamps();
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
