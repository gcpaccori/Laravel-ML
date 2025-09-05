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
            ['nombre' => 'Inicio', 'descripcion' => 'Etapa inicial de la crianza'],
            ['nombre' => 'Crecimiento', 'descripcion' => 'Etapa de desarrollo y crecimiento'],
            ['nombre' => 'Engorde', 'descripcion' => 'Etapa final antes de la cosecha'],
        ];

        foreach ($etapas as $etapa) {
            Etapa::firstOrCreate(
                ['nombre' => $etapa['nombre']], // evita duplicados
                ['descripcion' => $etapa['descripcion']]
            );
        }
    }
}
