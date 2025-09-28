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
        Schema::create('parametro_aguas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piscina_id')->nullable()->constrained();
            $table->decimal('temperatura', 5, 2)->nullable();    // °C
            $table->decimal('ph', 4, 2)->nullable();
            $table->decimal('oxigeno_disuelto', 5, 2)->nullable(); // mg/L
            $table->decimal('ion_nitrato', 8, 2)->nullable();          // mg/L
            $table->dateTime('fecha_medicion')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('parametro_aguas');
    }
};
