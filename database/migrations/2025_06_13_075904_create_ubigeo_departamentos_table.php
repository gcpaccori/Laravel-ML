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
        Schema::create('ubigeo_departamentos', function (Blueprint $table) {
            $table->string('id', 2)->primary(); // ID tipo string de 2 dígitos
            $table->string('name', 100);
            $table->string('code', 10);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ubigeo_departamentos');
    }
};
