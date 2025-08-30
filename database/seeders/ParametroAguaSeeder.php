<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ParametroAgua;
use App\Models\Piscina;
use Carbon\Carbon;

class ParametroAguaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $piscinas = Piscina::all();

        if ($piscinas->isEmpty()) {
            $this->command->warn('No hay piscinas registradas. Primero crea algunas.');
            return;
        }

        foreach ($piscinas as $piscina) {
            $fecha = Carbon::now()->subDays(14)->startOfDay();

            while ($fecha->lessThanOrEqualTo(Carbon::now())) {
                ParametroAgua::create([
                    'piscina_id'        => $piscina->id,
                    'temperatura'       => fake()->randomFloat(2, 20, 32), // °C
                    'ph'                => fake()->randomFloat(2, 6.5, 9),
                    'oxigeno_disuelto'  => fake()->randomFloat(2, 4, 10), // mg/L
                    'ion_nitrato'       => fake()->randomFloat(2, 0, 50), // mg/L
                    'fecha_medicion'    => $fecha,
                ]);

                // // avanzar 10 minutos
                // $fecha->addMinutes(60);

                // Avanzamos 6 horas
                $fecha->addHours(6);
            }
        }

        $this->command->info('Datos de parámetros de agua generados (cada 10 minutos por 14 días).');
    }
}
