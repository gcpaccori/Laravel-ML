<?php

namespace App\Services;

use App\Models\AlimentacionMes;
use App\Models\AlimentacionTabla;
use Illuminate\Support\Facades\DB;
use App\Models\AlimentacionSemana;
use App\Models\Biometria;
use App\Models\CampaniaEtapa;
use Carbon\CarbonImmutable;

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

    /**
     * Calcula el "Total Alimento Consumido (Kg)" REFERENCIAL de una biometría
     * a partir de la Tabla de Alimentación BFT de la campaña-especie.
     *
     * Regla (deducida del Excel TABLA_DE_MUESTREO_BFT):
     *  - La semana 1 de la tabla empieza en la fecha de siembra.
     *    Semana n = [siembra + (n-1)*7 , siembra + n*7)  (fin exclusivo)
     *  - El periodo de la biometría es [fecha_inicial, fecha_muestreo)
     *    -> mismos días que "TIEMPO (Días)" = fecha_muestreo - fecha_inicial.
     *  - Semana completa dentro del periodo  -> suma consumo_semanal_kg.
     *    Semana parcial                       -> consumo_diario_kg * días que caen.
     *
     *  Ej. Excel (hoja BFT-01, 1er muestreo): 25/04 -> 09/05 = 14 días
     *      = semanas 1 y 2 completas = 1.67 + 3.74 = 5.41 kg
     */

    public function calcularAlimento(int $campaniaEtapaId, string $fechaMuestreo, ?int $biometriaId = null): array
    {
        $etapa   = CampaniaEtapa::with('campaniaEspecie')->findOrFail($campaniaEtapaId);
        $especie = $etapa->campaniaEspecie;
        $fin     = CarbonImmutable::parse($fechaMuestreo)->startOfDay();

        [$inicio, $origen] = $this->resolverFechaInicial($etapa, $fin, $biometriaId);

        $resultado = [
            'total_kg'             => null,
            'fecha_siembra'        => $especie?->fecha_siembra
                ? CarbonImmutable::parse($especie->fecha_siembra)->toDateString() : null,
            'fecha_inicial'        => $inicio?->toDateString(),
            'origen_fecha_inicial' => $origen,
            'fecha_muestreo'       => $fin->toDateString(),
            'dias' => $inicio
                ? max(
                    0,
                    CarbonImmutable::parse($inicio->toDateString())
                        ->diffInDays(
                            CarbonImmutable::parse($fin->toDateString()),
                            false
                        )
                )
                : 0,
            'dias_cubiertos'       => 0,
            'semana_desde'         => null,
            'semana_hasta'         => null,
            'tabla'                => null,
            'semanas'              => [],
            'advertencias'         => [],
        ];

        $tabla = $especie
            ? AlimentacionTabla::where('campania_especie_id', $especie->id)->first()
            : null;

        if (!$tabla) {
            $resultado['advertencias'][] = 'La especie no tiene una tabla de alimentación registrada.';
            return $resultado;
        }
        if (!$especie->fecha_siembra) {
            $resultado['advertencias'][] = 'La especie no tiene fecha de siembra; no se pueden ubicar las semanas.';
            return $resultado;
        }
        if (!$inicio) {
            $resultado['advertencias'][] = 'No se pudo determinar la fecha inicial del periodo.';
            return $resultado;
        }
        if (!$tabla->calculado) {
            $resultado['advertencias'][] = 'La tabla de alimentación aún no ha sido calculada.';
        }

        $resultado['tabla'] = [
            'id'                    => $tabla->id,
            'titulo'                => $tabla->titulo,
            'poblacion_inicial'     => $tabla->poblacion_inicial,
            'mortalidad_porcentaje' => (float) $tabla->mortalidad_porcentaje,
        ];

        $siembra = CarbonImmutable::parse($especie->fecha_siembra)->startOfDay();

        // Offsets en días respecto a la siembra (fin exclusivo)
        $desde = (int) $siembra->diffInDays($inicio, false);
        $hasta = (int) $siembra->diffInDays($fin, false);

        if ($desde < 0) {
            $resultado['advertencias'][] = 'La fecha inicial es anterior a la siembra; se cuenta desde la siembra.';
            $desde = 0;
        }
        if ($hasta <= $desde) {
            $resultado['advertencias'][] = 'La fecha de muestreo debe ser posterior a la fecha inicial.';
            $hasta = $desde;
        }

        $semanas = AlimentacionSemana::query()
            ->join('alimentacion_mes as m', 'm.id', '=', 'alimentacion_semanas.alimentacion_mes_id')
            ->where('m.alimentacion_tabla_id', $tabla->id)
            ->orderBy('alimentacion_semanas.numero_semana')
            ->get([
                'alimentacion_semanas.*',
                'm.numero_mes',
                'm.tipo_alimento',
            ]);

        $total        = 0.0;
        $diasCubiertos = 0;
        $ultimaSemana = 0;

        foreach ($semanas as $s) {
            $n        = (int) $s->numero_semana;
            $semIni   = ($n - 1) * 7;
            $semFin   = $semIni + 7;
            $dias     = max(0, min($semFin, $hasta) - max($semIni, $desde));
            $semanal  = (float) $s->consumo_semanal_kg;
            $diario   = (float) $s->consumo_diario_kg;

            // Semana completa: se usa el semanal guardado (igual que el Excel)
            $aporte = $dias === 7 ? $semanal : round($diario * $dias, 3);

            if ($dias > 0) {
                $total += $aporte;
                $diasCubiertos += $dias;
                $resultado['semana_desde'] ??= $n;
                $resultado['semana_hasta'] = $n;
            }
            $ultimaSemana = max($ultimaSemana, $n);

            $resultado['semanas'][] = [
                'numero_semana'      => $n,
                'numero_mes'         => (int) $s->numero_mes,
                'tipo_alimento'      => $s->tipo_alimento,
                'fecha_desde'        => $siembra->addDays($semIni)->toDateString(),
                'fecha_hasta'        => $siembra->addDays($semFin - 1)->toDateString(),
                'ganancia_peso_g'    => (float) $s->ganancia_peso_g,
                'biomasa_kg'         => (float) $s->biomasa_kg,
                'consumo_diario_kg'  => $diario,
                'consumo_semanal_kg' => $semanal,
                'dias_considerados'  => $dias,
                'aporte_kg'          => $dias > 0 ? $aporte : 0,
                'estado'             => $dias === 7 ? 'completa' : ($dias > 0 ? 'parcial' : null),
            ];
        }

        $sinCobertura = ($hasta - $desde) - $diasCubiertos;
        if ($sinCobertura > 0) {
            $resultado['advertencias'][] =
                "{$sinCobertura} día(s) del periodo quedan fuera de la tabla (última semana registrada: {$ultimaSemana}).";
        }

        $resultado['total_kg']       = round($total, 3);
        $resultado['dias_cubiertos'] = $diasCubiertos;

        return $resultado;
    }

    /**
     * Fecha inicial del periodo (igual que en el Excel, donde la
     * "FECHA INICIAL" de un muestreo es la "FECHA" del muestreo anterior).
     * La cadena se arma por campaña-especie, sin importar la etapa.
     * Reutiliza este método en el store/update para que ambos coincidan.
     *
     * @return array{0: ?CarbonImmutable, 1: ?string}
     */
    public function resolverFechaInicial(CampaniaEtapa $etapa, CarbonImmutable $fechaMuestreo, ?int $excluirId = null): array
    {
        $anterior = Biometria::query()
            ->deCampaniaEspecie($etapa->campania_especie_id)
            ->when($excluirId, fn ($q) => $q->where('id', '!=', $excluirId))
            ->whereDate('fecha_muestreo', '<=', $fechaMuestreo->toDateString())
            ->orderByDesc('fecha_muestreo')
            ->value('fecha_muestreo');

        if ($anterior) {
            return [CarbonImmutable::parse($anterior)->startOfDay(), 'biometria_anterior'];
        }

        if ($etapa->fecha_inicio) {
            return [CarbonImmutable::parse($etapa->fecha_inicio)->startOfDay(), 'inicio_etapa'];
        }

        if ($etapa->campaniaEspecie?->fecha_siembra) {
            return [CarbonImmutable::parse($etapa->campaniaEspecie->fecha_siembra)->startOfDay(), 'fecha_siembra'];
        }

        return [null, null];
    }
}
