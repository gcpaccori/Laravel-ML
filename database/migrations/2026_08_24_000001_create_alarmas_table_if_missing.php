<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('alarmas')) {
            return;
        }

        Schema::create('alarmas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piscigranja_id')->constrained();
            $table->foreignId('piscina_id')->nullable()->constrained();
            $table->string('modulo', 100);
            $table->string('parametro')->nullable();
            $table->enum('nivel', ['normal', 'advertencia', 'critico', 'emergencia'])->default('advertencia');
            $table->decimal('valor_detectado', 10, 3)->nullable();
            $table->string('titulo');
            $table->text('mensaje')->nullable();
            $table->enum('estado', ['activa', 'reconocida', 'resuelta'])->default('activa');
            $table->foreignId('reconocida_por')->nullable()->constrained('users');
            $table->timestamp('reconocida_en')->nullable();
            $table->timestamp('resuelta_en')->nullable();
            $table->timestamps();

            $table->index(['piscigranja_id', 'estado']);
            $table->index(['piscina_id', 'estado']);
        });
    }

    public function down(): void
    {
        // Shared table owned by the general alarm module; never drop it here.
    }
};
