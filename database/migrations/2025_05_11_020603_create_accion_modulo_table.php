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
        Schema::create('accion_modulo', function (Blueprint $table) {
            $table->foreignId('modulo_id')->constrained('modulos')->onDelete('cascade');
            $table->foreignId('accion_id')->constrained('accions')->onDelete('cascade');

            $table->primary(['modulo_id', 'accion_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accion_modulo');
    }
};
