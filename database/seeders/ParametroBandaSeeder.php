<?php

namespace Database\Seeders;

use App\Models\ParametroBanda;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class ParametroBandaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $data = [
            // Temperatura
            ["parametro" => "temperatura", "title" => "Frío",    "color" => "#00BFFF", "low_score" => 0,   "high_score" => 25],
            ["parametro" => "temperatura", "title" => "Óptimo",  "color" => "#54b947", "low_score" => 25,  "high_score" => 31],
            ["parametro" => "temperatura", "title" => "Alto",    "color" => "#fdae19", "low_score" => 31,  "high_score" => 35],
            ["parametro" => "temperatura", "title" => "Crítico", "color" => "#ee1f25", "low_score" => 35,  "high_score" => 40],

            // pH
            ["parametro" => "ph", "title" => "Ácido",    "color" => "#ee1f25", "low_score" => 0,   "high_score" => 6.5],
            ["parametro" => "ph", "title" => "Óptimo",   "color" => "#54b947", "low_score" => 6.5, "high_score" => 8.5],
            ["parametro" => "ph", "title" => "Alcalino", "color" => "#fdae19", "low_score" => 8.5, "high_score" => 14],

            // Oxígeno disuelto
            ["parametro" => "oxigeno", "title" => "Crítico", "color" => "#ee1f25", "low_score" => 0,   "high_score" => 4],
            ["parametro" => "oxigeno", "title" => "Bajo",    "color" => "#fdae19", "low_score" => 4,   "high_score" => 6.5],
            ["parametro" => "oxigeno", "title" => "Óptimo",  "color" => "#54b947", "low_score" => 6.5, "high_score" => 8.0],
            ["parametro" => "oxigeno", "title" => "Alto",    "color" => "#00BFFF", "low_score" => 8.0, "high_score" => 15],

            // Nitrato
            ["parametro" => "nitrato", "title" => "Seguro",    "color" => "#54b947", "low_score" => 1,    "high_score" => 50],
            ["parametro" => "nitrato", "title" => "Moderado",  "color" => "#fdae19", "low_score" => 50,   "high_score" => 200],
            ["parametro" => "nitrato", "title" => "Crítico",   "color" => "#ee1f25", "low_score" => 200,  "high_score" => 2000],
        ];

        foreach ($data as $item) {
            ParametroBanda::updateOrCreate(
                [
                    "parametro" => $item["parametro"],
                    "title"     => $item["title"],
                ],
                [
                    "low_score" => $item["low_score"],
                    "high_score"=> $item["high_score"],
                    "color"     => $item["color"],
                ]
            );
        }
    }
}
