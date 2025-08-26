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
        Schema::create('piscigranjas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 150);
            $table->string('departamento_id', 2)->nullable();
            $table->string('provincia_id', 4)->nullable();
            $table->string('distrito_id', 6)->nullable();
            $table->string('direccion')->nullable();
            $table->string('latitud')->nullable();
            $table->string('longitud')->nullable();
            $table->text('descripcion')->nullable();
            $table->string('propietario')->nullable();
            $table->string('telefono_contacto', 20)->nullable();
            $table->string('email_contacto')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('piscigranjas');
    }
};
