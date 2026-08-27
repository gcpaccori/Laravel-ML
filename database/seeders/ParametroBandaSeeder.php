<?php

namespace Database\Seeders;

use App\Models\ParametroBanda;
use Illuminate\Database\Seeder;

class ParametroBandaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $data = [

            // =========================================================
            // TEMPERATURA AGUA (°C)
            // =========================================================
            [
                "parametro"  => "temperatura",
                "title"      => "Frío",
                "color"      => "#00BFFF",
                "low_score"  => 0,
                "high_score" => 25,
                "nivel"      => "advertencia",
                "alerta"     => true,
            ],
            [
                "parametro"  => "temperatura",
                "title"      => "Óptimo",
                "color"      => "#54b947",
                "low_score"  => 25,
                "high_score" => 31,
                "nivel"      => "normal",
                "alerta"     => false,
            ],
            [
                "parametro"  => "temperatura",
                "title"      => "Alto",
                "color"      => "#fdae19",
                "low_score"  => 31,
                "high_score" => 35,
                "nivel"      => "advertencia",
                "alerta"     => true,
            ],
            [
                "parametro"  => "temperatura",
                "title"      => "Crítico",
                "color"      => "#ee1f25",
                "low_score"  => 35,
                "high_score" => 100,
                "nivel"      => "critico",
                "alerta"     => true,
            ],

            // =========================================================
            // pH
            // =========================================================
            [
                "parametro"  => "ph",
                "title"      => "Ácido",
                "color"      => "#ee1f25",
                "low_score"  => 0,
                "high_score" => 6.5,
                "nivel"      => "critico",
                "alerta"     => true,
            ],
            [
                "parametro"  => "ph",
                "title"      => "Óptimo",
                "color"      => "#54b947",
                "low_score"  => 6.5,
                "high_score" => 8.5,
                "nivel"      => "normal",
                "alerta"     => false,
            ],
            [
                "parametro"  => "ph",
                "title"      => "Alcalino",
                "color"      => "#fdae19",
                "low_score"  => 8.5,
                "high_score" => 20,
                "nivel"      => "advertencia",
                "alerta"     => true,
            ],

            // =========================================================
            // OXÍGENO DISUELTO (mg/L)
            // =========================================================
            [
                "parametro"  => "oxigeno_disuelto",
                "title"      => "Crítico",
                "color"      => "#ee1f25",
                "low_score"  => 0,
                "high_score" => 4,
                "nivel"      => "critico",
                "alerta"     => true,
            ],
            [
                "parametro"  => "oxigeno_disuelto",
                "title"      => "Bajo",
                "color"      => "#fdae19",
                "low_score"  => 4,
                "high_score" => 6.5,
                "nivel"      => "advertencia",
                "alerta"     => true,
            ],
            [
                "parametro"  => "oxigeno_disuelto",
                "title"      => "Óptimo",
                "color"      => "#54b947",
                "low_score"  => 6.5,
                "high_score" => 8,
                "nivel"      => "normal",
                "alerta"     => false,
            ],
            [
                "parametro"  => "oxigeno_disuelto",
                "title"      => "Alto",
                "color"      => "#00BFFF",
                "low_score"  => 8,
                "high_score" => 50,
                "nivel"      => "advertencia",
                "alerta"     => true,
            ],

            // =========================================================
            // NITRATO (mg/L)
            // =========================================================
            [
                "parametro"  => "ion_nitrato",
                "title"      => "Seguro",
                "color"      => "#54b947",
                "low_score"  => 0,
                "high_score" => 50,
                "nivel"      => "normal",
                "alerta"     => false,
            ],
            [
                "parametro"  => "ion_nitrato",
                "title"      => "Moderado",
                "color"      => "#fdae19",
                "low_score"  => 50,
                "high_score" => 200,
                "nivel"      => "advertencia",
                "alerta"     => true,
            ],
            [
                "parametro"  => "ion_nitrato",
                "title"      => "Crítico",
                "color"      => "#ee1f25",
                "low_score"  => 200,
                "high_score" => 20000,
                "nivel"      => "critico",
                "alerta"     => true,
            ],

            // =========================================================
            // ILUMINANCIA (LUX)
            // =========================================================
            [
                "parametro"  => "iluminancia",
                "title"      => "Oscuridad",
                "color"      => "#1F3864",
                "low_score"  => 0,
                "high_score" => 1,
                "nivel"      => "normal",
                "alerta"     => false,
            ],
            [
                "parametro"  => "iluminancia",
                "title"      => "Transición",
                "color"      => "#fdae19",
                "low_score"  => 1,
                "high_score" => 10,
                "nivel"      => "normal",
                "alerta"     => false,
            ],
            [
                "parametro"  => "iluminancia",
                "title"      => "Luz",
                "color"      => "#f2c744",
                "low_score"  => 10,
                "high_score" => 65535,
                "nivel"      => "normal",
                "alerta"     => false,
            ],

            // =========================================================
            // TEMPERATURA AMBIENTE (°C)
            // =========================================================
            [
                "parametro"  => "temperatura_ambiente",
                "title"      => "Bajo",
                "color"      => "#00BFFF",
                "low_score"  => 0,
                "high_score" => 20,
                "nivel"      => "advertencia",
                "alerta"     => true,
            ],
            [
                "parametro"  => "temperatura_ambiente",
                "title"      => "Óptimo",
                "color"      => "#54b947",
                "low_score"  => 20,
                "high_score" => 30,
                "nivel"      => "normal",
                "alerta"     => false,
            ],
            [
                "parametro"  => "temperatura_ambiente",
                "title"      => "Alto",
                "color"      => "#fdae19",
                "low_score"  => 30,
                "high_score" => 35,
                "nivel"      => "advertencia",
                "alerta"     => true,
            ],
            [
                "parametro"  => "temperatura_ambiente",
                "title"      => "Crítico",
                "color"      => "#ee1f25",
                "low_score"  => 35,
                "high_score" => 100,
                "nivel"      => "critico",
                "alerta"     => true,
            ],

            // =========================================================
            // HUMEDAD AMBIENTE (%)
            // =========================================================
            [
                "parametro"  => "humedad_ambiente",
                "title"      => "Baja",
                "color"      => "#fdae19",
                "low_score"  => 0,
                "high_score" => 40,
                "nivel"      => "advertencia",
                "alerta"     => true,
            ],
            [
                "parametro"  => "humedad_ambiente",
                "title"      => "Óptimo",
                "color"      => "#54b947",
                "low_score"  => 40,
                "high_score" => 70,
                "nivel"      => "normal",
                "alerta"     => false,
            ],
            [
                "parametro"  => "humedad_ambiente",
                "title"      => "Alta",
                "color"      => "#fdae19",
                "low_score"  => 70,
                "high_score" => 85,
                "nivel"      => "advertencia",
                "alerta"     => true,
            ],
            [
                "parametro"  => "humedad_ambiente",
                "title"      => "Crítico",
                "color"      => "#ee1f25",
                "low_score"  => 85,
                "high_score" => 100,
                "nivel"      => "critico",
                "alerta"     => true,
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
                    "high_score" => $item["high_score"],
                    "color"     => $item["color"],
                    "nivel"     => $item["nivel"],
                    "alerta"    => $item["alerta"],
                ]
            );
        }
    }
}
