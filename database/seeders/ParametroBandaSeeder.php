<?php

namespace Database\Seeders;

use App\Models\ParametroBanda;
use Illuminate\Database\Seeder;

class ParametroBandaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        $data = [

            // Temperatura Agua (°C)
            [
                "parametro"  => "temperatura",
                "title"      => "Frío",
                "color"      => "#00BFFF",
                "low_score"  => 0,
                "high_score" => 25,
            ],
            [
                "parametro"  => "temperatura",
                "title"      => "Óptimo",
                "color"      => "#54b947",
                "low_score"  => 25,
                "high_score" => 31,
            ],
            [
                "parametro"  => "temperatura",
                "title"      => "Alto",
                "color"      => "#fdae19",
                "low_score"  => 31,
                "high_score" => 35,
            ],
            [
                "parametro"  => "temperatura",
                "title"      => "Crítico",
                "color"      => "#ee1f25",
                "low_score"  => 35,
                "high_score" => 100,
            ],

            // pH
            [
                "parametro"  => "ph",
                "title"      => "Ácido",
                "color"      => "#ee1f25",
                "low_score"  => 0,
                "high_score" => 6.5,
            ],
            [
                "parametro"  => "ph",
                "title"      => "Óptimo",
                "color"      => "#54b947",
                "low_score"  => 6.5,
                "high_score" => 8.5,
            ],
            [
                "parametro"  => "ph",
                "title"      => "Alcalino",
                "color"      => "#fdae19",
                "low_score"  => 8.5,
                "high_score" => 20,
            ],

            // Oxígeno disuelto (mg/L)
            [
                "parametro"  => "oxigeno_disuelto",
                "title"      => "Crítico",
                "color"      => "#ee1f25",
                "low_score"  => 0,
                "high_score" => 4,
            ],
            [
                "parametro"  => "oxigeno_disuelto",
                "title"      => "Bajo",
                "color"      => "#fdae19",
                "low_score"  => 4,
                "high_score" => 6.5,
            ],
            [
                "parametro"  => "oxigeno_disuelto",
                "title"      => "Óptimo",
                "color"      => "#54b947",
                "low_score"  => 6.5,
                "high_score" => 8,
            ],
            [
                "parametro"  => "oxigeno_disuelto",
                "title"      => "Alto",
                "color"      => "#00BFFF",
                "low_score"  => 8,
                "high_score" => 50,
            ],

            // Nitrato (mg/L)
            [
                "parametro"  => "ion_nitrato",
                "title"      => "Seguro",
                "color"      => "#54b947",
                "low_score"  => 0,
                "high_score" => 50,
            ],
            [
                "parametro"  => "ion_nitrato",
                "title"      => "Moderado",
                "color"      => "#fdae19",
                "low_score"  => 50,
                "high_score" => 200,
            ],
            [
                "parametro"  => "ion_nitrato",
                "title"      => "Crítico",
                "color"      => "#ee1f25",
                "low_score"  => 200,
                "high_score" => 20000,
            ],

            // Iluminancia (LUX) - Fotoperíodo, alimentado por el sensor LILYGO
            [
                "parametro"  => "iluminancia",
                "title"      => "Oscuridad",
                "color"      => "#1F3864",
                "low_score"  => 0,
                "high_score" => 1,
            ],
            [
                "parametro"  => "iluminancia",
                "title"      => "Transición",
                "color"      => "#fdae19",
                "low_score"  => 1,
                "high_score" => 10,
            ],
            [
                "parametro"  => "iluminancia",
                "title"      => "Luz",
                "color"      => "#f2c744",
                "low_score"  => 10,
                "high_score" => 65535,
            ],

            // Temperatura ambiente (°C)
            [
                "parametro"  => "temperatura_ambiente",
                "title"      => "Bajo",
                "color"      => "#00BFFF",
                "low_score"  => 0,
                "high_score" => 20,
            ],
            [
                "parametro"  => "temperatura_ambiente",
                "title"      => "Óptimo",
                "color"      => "#54b947",
                "low_score"  => 20,
                "high_score" => 30,
            ],
            [
                "parametro"  => "temperatura_ambiente",
                "title"      => "Alto",
                "color"      => "#fdae19",
                "low_score"  => 30,
                "high_score" => 35,
            ],
            [
                "parametro"  => "temperatura_ambiente",
                "title"      => "Crítico",
                "color"      => "#ee1f25",
                "low_score"  => 35,
                "high_score" => 100,
            ],
            
            // Humedad ambiente (%)
            [
                "parametro"  => "humedad_ambiente",
                "title"      => "Baja",
                "color"      => "#fdae19",
                "low_score"  => 0,
                "high_score" => 40,
            ],
            [
                "parametro"  => "humedad_ambiente",
                "title"      => "Óptimo",
                "color"      => "#54b947",
                "low_score"  => 40,
                "high_score" => 70,
            ],
            [
                "parametro"  => "humedad_ambiente",
                "title"      => "Alta",
                "color"      => "#fdae19",
                "low_score"  => 70,
                "high_score" => 85,
            ],
            [
                "parametro"  => "humedad_ambiente",
                "title"      => "Crítico",
                "color"      => "#ee1f25",
                "low_score"  => 85,
                "high_score" => 100,
            ],
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
