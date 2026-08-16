<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Almacena cada envío de datos del sensor LILYGO para la pestaña
     * de Fotoperíodo: iluminancia, estado lumínico, temperatura y
     * humedad ambiente, además del contador acumulado de horas de
     * luz (L) y oscuridad (D) del día.
     */
    public function up(): void
    {
        Schema::create('parametro_ambientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('piscina_id')->nullable()->constrained();
            // Momento exacto del envío del sensor (puede diferir levemente de created_at)
            $table->timestamp('fecha_medicion')->useCurrent();
            // Fecha del envío, derivada de fecha_medicion. Permite agrupar y reiniciar el contador de fotoperíodo cada día.
            $table->date('fecha');
            // Sensor de luz: mide de 1 a 65535 LUX
            $table->unsignedInteger('iluminancia');
            // Calculado a partir de la iluminancia: < 1 lux => OSCURIDAD | 1-10 lux => TRANSICION | > 10 lux => LUZ
            $table->enum('estado_luminico', ['OSCURIDAD', 'TRANSICION', 'LUZ']);
            $table->float('temperatura_ambiente')->nullable();
            $table->float('humedad_ambiente')->nullable();
            // Contador acumulado (tipo cronómetro) de segundos de luz/oscuridad del día en curso, calculado hasta este registro.
            $table->unsignedInteger('segundos_luz')->default(0);
            $table->unsignedInteger('segundos_oscuridad')->default(0);
            $table->timestamps();

            $table->index(['fecha', 'fecha_medicion']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parametro_ambientes');
    }
};
