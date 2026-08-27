<?php

namespace App\Services;

use App\Models\AlimentacionMes;
use App\Models\AlimentacionTabla;
use Illuminate\Support\Facades\DB;

class AlimentacionBftService
{
    /**
     * Recalcula y persiste TODA la tabla de alimentación (horarios, meses
     * y semanas) a partir de los datos crudos del formulario.
     *
     * $horarios: [ ['hora' => '08:00'], ['hora' => '11:00'], ... ]
     * $semanas:  [ ['numero_semana' => 1, 'ganancia_peso_g' => 0.33, 'tasa_alimentacion_porcentaje' => 15], ... ]
     * $meses:    [ ['numero_mes' => 1, 'tipo_alimento' => '0.45mm'], ... ]
     *
     * Fórmulas (replican el Excel Tabla_de_Alimentacion_BFT):
     *   población(1) = población_inicial
     *   población(n) = población(n-1) - (población_inicial * mortalidad% / (numero_semanas-1))
     *   biomasa_kg        = ganancia_peso_g * población / 1000
     *   consumo_diario_kg = biomasa_kg * (tasa_alimentacion_porcentaje / 100)
     *   consumo_semanal_kg = consumo_diario_kg * 7
     *   consumo_mensual_kg = SUMA(consumo_semanal_kg) de las semanas del mes
     */
    public function generar(AlimentacionTabla $tabla, array $horarios, array $semanas, array $meses): AlimentacionTabla
    {
        DB::transaction(function () use ($tabla, $horarios, $semanas, $meses) {
            $this->sincronizarHorarios($tabla, $horarios);

            // Se regenera todo el detalle en cada cálculo para evitar
            // inconsistencias parciales (elimina meses -> cascada a semanas).
            $tabla->meses()->delete();

            $poblacionInicial = (float) $tabla->poblacion_inicial;
            $mortalidad = (float) $tabla->mortalidad_porcentaje;
            $numeroSemanas = (int) $tabla->numero_semanas;
            $semanasPorMes = (int) $tabla->semanas_por_mes;

            $decrementoPoblacional = $numeroSemanas > 1
                ? ($poblacionInicial * ($mortalidad / 100)) / ($numeroSemanas - 1)
                : 0;

            $tipoAlimentoPorMes = collect($meses)->keyBy('numero_mes');
            $semanasOrdenadas = collect($semanas)->sortBy('numero_semana')->values();

            /** @var array<int, AlimentacionMes> $mesesCreados */
            $mesesCreados = [];
            $poblacionAnterior = null;

            foreach ($semanasOrdenadas as $semanaInput) {
                $numeroSemana = (int) $semanaInput['numero_semana'];
                $numeroMes = (int) ceil($numeroSemana / $semanasPorMes);

                if (! isset($mesesCreados[$numeroMes])) {
                    $mesesCreados[$numeroMes] = $tabla->meses()->create([
                        'numero_mes' => $numeroMes,
                        'tipo_alimento' => $tipoAlimentoPorMes->get($numeroMes)['tipo_alimento'] ?? null,
                    ]);
                }

                $poblacion = $poblacionAnterior === null
                    ? $poblacionInicial
                    : $poblacionAnterior - $decrementoPoblacional;

                $gananciaPesoG = (float) $semanaInput['ganancia_peso_g'];
                $tasaAlimentacion = (float) $semanaInput['tasa_alimentacion_porcentaje'];

                $biomasaKg = ($gananciaPesoG * $poblacion) / 1000;
                $consumoDiarioKg = $biomasaKg * ($tasaAlimentacion / 100);
                $consumoSemanalKg = $consumoDiarioKg * 7;

                $mesesCreados[$numeroMes]->semanas()->create([
                    'numero_semana' => $numeroSemana,
                    'ganancia_peso_g' => $gananciaPesoG,
                    'tasa_alimentacion_porcentaje' => $tasaAlimentacion,
                    'poblacion_calculada' => $poblacion,
                    'biomasa_kg' => $biomasaKg,
                    'consumo_diario_kg' => $consumoDiarioKg,
                    'consumo_semanal_kg' => $consumoSemanalKg,
                ]);

                $poblacionAnterior = $poblacion;
            }

            foreach ($mesesCreados as $mes) {
                $mes->update([
                    'consumo_mensual_kg' => $mes->semanas()->sum('consumo_semanal_kg'),
                ]);
            }

            $tabla->update(['calculado' => true]);
        });

        return $tabla->fresh(['horarios', 'meses.semanas']);
    }

    private function sincronizarHorarios(AlimentacionTabla $tabla, array $horarios): void
    {
        $tabla->horarios()->delete();

        foreach (array_values($horarios) as $index => $horario) {
            $tabla->horarios()->create([
                'hora' => $horario['hora'],
                'orden' => $index + 1,
            ]);
        }
    }

    /**
     * Arma la estructura ya lista para la vista Inertia (Vue) y para el
     * PDF (Blade), incluyendo el reparto de la ración diaria en gramos
     * entre los horarios configurados (columnas J,K,L,M del Excel).
     */
    public function tablaParaVista(AlimentacionTabla $tabla): array
    {
        $tabla->loadMissing(['horarios', 'meses.semanas']);

        $horarios = $tabla->horarios;
        $countHorarios = max($horarios->count(), 1);

        $meses = $tabla->meses->map(function (AlimentacionMes $mes) use ($horarios, $countHorarios) {
            return [
                'numero_mes' => $mes->numero_mes,
                'tipo_alimento' => $mes->tipo_alimento,
                'consumo_mensual_kg' => round((float) $mes->consumo_mensual_kg, 3),
                'semanas' => $mes->semanas->map(function ($semana) use ($horarios, $countHorarios) {
                    $gramosPorHorario = ((float) $semana->consumo_diario_kg / $countHorarios) * 1000;

                    return [
                        'numero_semana' => $semana->numero_semana,
                        'ganancia_peso_g' => round((float) $semana->ganancia_peso_g, 3),
                        'poblacion' => round((float) $semana->poblacion_calculada, 0),
                        'biomasa_kg' => round((float) $semana->biomasa_kg, 3),
                        'tasa_alimentacion_porcentaje' => round((float) $semana->tasa_alimentacion_porcentaje, 2),
                        'consumo_diario_kg' => round((float) $semana->consumo_diario_kg, 4),
                        'consumo_semanal_kg' => round((float) $semana->consumo_semanal_kg, 3),
                        'frecuencias' => $horarios->map(fn ($h) => [
                            'hora' => $h->hora->format('H:i'),
                            'gramos' => round($gramosPorHorario, 2),
                        ])->values(),
                    ];
                })->values(),
            ];
        })->values();

        return [
            'tabla' => [
                'id' => $tabla->id,
                'titulo' => $tabla->titulo,
                'responsable' => $tabla->responsable,
                'poblacion_inicial' => $tabla->poblacion_inicial,
                'mortalidad_porcentaje' => (float) $tabla->mortalidad_porcentaje,
                'numero_semanas' => $tabla->numero_semanas,
                'semanas_por_mes' => $tabla->semanas_por_mes,
                'observaciones' => $tabla->observaciones,
            ],
            'horarios' => $horarios->map(fn ($h) => ['hora' => $h->hora->format('H:i')])->values(),
            'meses' => $meses,
        ];
    }
}
