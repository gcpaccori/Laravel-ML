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
        Schema::create('parametro_bandas', function (Blueprint $table) {
            $table->id();
            $table->string('parametro'); // temperatura, ph, oxigeno, nitrato
            $table->string('title'); // Frío, Óptimo, Crítico, etc.
            $table->string('color', 20); // #hex o nombre CSS
            $table->decimal('low_score', 8, 2); // límite inferior
            $table->decimal('high_score', 8, 2); // límite superior
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametro_bandas');
    }
};
