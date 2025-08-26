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
        Schema::create('ubigeo_distritos', function (Blueprint $table) {
            $table->string('id', 6)->primary(); // Código de distrito
            $table->string('name');
            $table->string('provincia_id', 4);
            $table->string('departamento_id', 2);

            $table->foreign('provincia_id')->references('id')->on('ubigeo_provincias');
            // $table->foreign('departamento_id')->references('id')->on('ubigeo_departamentos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ubigeo_distritos');
    }
};
