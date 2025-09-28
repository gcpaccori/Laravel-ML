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
        // Relación campaña + especie
        Schema::create('campania_especies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campania_id')->constrained();
            $table->foreignId('especie_id')->constrained();
            $table->integer('cantidad_siembra')->nullable();
            $table->date('fecha_siembra')->nullable();
            $table->integer('cantidad_cosechada')->nullable();
            $table->decimal('peso_inicial_gr', 8, 2)->nullable();
            $table->decimal('peso_final_gr', 8, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campania_especies');
    }
};
