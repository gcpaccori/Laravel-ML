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
        // Campaña_Etapa_Piscina (etapa desarrollada en una o varias piscinas)
        Schema::create('campania_etapa_piscinas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campania_etapa_id')->constrained();
            $table->foreignId('piscina_id')->constrained();
            $table->timestamps();
            $table->unique(['campania_etapa_id', 'piscina_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campania_etapa_piscinas');
    }
};
