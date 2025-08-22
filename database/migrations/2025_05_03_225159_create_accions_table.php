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
        Schema::create('accions', function (Blueprint $table) {
            $table->id();
            $table->string('accion');
            $table->string('name');
            $table->string('icon');
            $table->string('type');
            $table->string('name_funcion');
            $table->boolean('active')->default(1);
            $table->char('location', 1)->default('T');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accions');
    }
};
