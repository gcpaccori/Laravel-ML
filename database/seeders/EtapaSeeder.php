<?php

namespace Database\Seeders;

use App\Models\Etapa;
use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class EtapaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $etapas = [
            ['nombre' => 'Inicio', 'descripcion' => 'Etapa inicial de la crianza', 'orden' => 1],
            ['nombre' => 'Crecimiento', 'descripcion' => 'Etapa de desarrollo y crecimiento', 'orden' => 2],
            ['nombre' => 'Engorde', 'descripcion' => 'Etapa final antes de la cosecha', 'orden' => 3],
        ];

        foreach ($etapas as $etapa) {
            Etapa::updateOrCreate(
                ['nombre' => $etapa['nombre']], // evita duplicados
                ['descripcion' => $etapa['descripcion'], 'orden' => $etapa['orden']]
            );
        }
    }
}
