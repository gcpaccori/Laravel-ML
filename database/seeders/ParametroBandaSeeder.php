<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ParametroBandaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::table('parametro_bandas')->insert([
            // Temperatura
            ["parametro" => "temperatura", "title" => "Frío", "color" => "#00BFFF", "low_score" => 0, "high_score" => 25],
            ["parametro" => "temperatura", "title" => "Óptimo", "color" => "#54b947", "low_score" => 25, "high_score" => 31],
            ["parametro" => "temperatura", "title" => "Alto", "color" => "#fdae19", "low_score" => 31, "high_score" => 35],
            ["parametro" => "temperatura", "title" => "Crítico", "color" => "#ee1f25", "low_score" => 35, "high_score" => 40],

            // pH
            ["parametro" => "ph", "title" => "Ácido", "color" => "#ee1f25", "low_score" => 0, "high_score" => 6.5],
            ["parametro" => "ph", "title" => "Óptimo", "color" => "#54b947", "low_score" => 6.5, "high_score" => 8.5],
            ["parametro" => "ph", "title" => "Alcalino", "color" => "#fdae19", "low_score" => 8.5, "high_score" => 14],

            // Oxígeno disuelto
            ["parametro" => "oxigeno", "title" => "Crítico", "color" => "#ee1f25", "low_score" => 0, "high_score" => 4],
            ["parametro" => "oxigeno", "title" => "Bajo", "color" => "#fdae19", "low_score" => 4, "high_score" => 6.5],
            ["parametro" => "oxigeno", "title" => "Óptimo", "color" => "#54b947", "low_score" => 6.5, "high_score" => 8.0],
            ["parametro" => "oxigeno", "title" => "Alto", "color" => "#00BFFF", "low_score" => 8.0, "high_score" => 15],

            // Nitrato
            ["parametro" => "nitrato", "title" => "Seguro", "color" => "#54b947", "low_score" => 0.01, "high_score" => 0.1],
            ["parametro" => "nitrato", "title" => "Moderado", "color" => "#fdae19", "low_score" => 0.1, "high_score" => 0.2],
            ["parametro" => "nitrato", "title" => "Crítico", "color" => "#ee1f25", "low_score" => 0.2, "high_score" => 2],
        ]);

    }
}
