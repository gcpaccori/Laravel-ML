<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Nucleo de crecimiento SISMAPISCIS: Modelo SISMAPISCIS de incremento de peso
 * (Ridge) y Modelo probabilistico SISMAPISCIS (proceso gaussiano).
 *
 * Los activos se entrenan fuera de la plataforma (interval_ml.py) y se copian
 * congelados, junto con la evidencia de la ultima corrida de validacion, a
 * storage/app/ml/crecimiento. La inferencia replica predecir_ml.py con los
 * parametros aprendidos: no reentrena ni escribe en la base de datos.
 */
class CrecimientoMlController extends Controller
{
    private const MODELOS = [
        'ridge' => [
            'archivo' => 'Modelo_Ridge_Sensores_Exploratorio.json',
            'nombre' => 'Modelo SISMAPISCIS de incremento de peso',
            'algoritmo' => 'Regresión Ridge (α = 10) con covarianza de muestreo biométrico',
            'rol' => 'Núcleo de crecimiento (candidata exploratoria de menor MAE)',
            'replay' => ['ridge_correlated', 'water_means'],
        ],
        'gp' => [
            'archivo' => 'Modelo_GP_Exposicion_Principal.json',
            'nombre' => 'Modelo probabilístico SISMAPISCIS',
            'algoritmo' => 'Proceso gaussiano, kernel lineal + constante + Matérn 3/2',
            'rol' => 'Configuración principal preespecificada',
            'replay' => ['gaussian_correlated', 'exposure'],
        ],
        'ridge_context' => [
            'archivo' => 'Modelo_Ridge_Sin_Sensores.json',
            'nombre' => 'Ridge sin sensores',
            'algoritmo' => 'Regresión Ridge (α = 10), solo peso inicial y duración',
            'rol' => 'Comparador sin información ambiental',
            'replay' => ['ridge_correlated', 'context'],
        ],
        'gp_context' => [
            'archivo' => 'Modelo_GP_Sin_Sensores.json',
            'nombre' => 'Proceso gaussiano sin sensores',
            'algoritmo' => 'Proceso gaussiano, solo peso inicial y duración',
            'rol' => 'Comparador sin información ambiental',
            'replay' => ['gaussian_correlated', 'context'],
        ],
    ];

    private const ETIQUETAS = [
        'initial_weight_g' => 'Peso inicial (g)',
        'duration_days' => 'Duración del intervalo (días)',
        'temperature_mean' => 'Temperatura media (°C)',
        'oxygen_mean' => 'OD medio (mg/L)',
        'oxygen_p10' => 'OD percentil 10 (mg/L)',
    ];

    private function dir(string $sub = ''): string
    {
        return storage_path('app/ml/crecimiento' . ($sub ? '/' . $sub : ''));
    }

    private function leer(string $ruta)
    {
        $f = $this->dir($ruta);
        return is_file($f) ? json_decode(file_get_contents($f), true) : null;
    }

    private function modelo(string $clave): ?array
    {
        $def = self::MODELOS[$clave] ?? null;
        return $def ? $this->leer('models/' . $def['archivo']) : null;
    }

    public function resumen()
    {
        $scores = $this->leer('evidence/replay_scores.json') ?? [];
        $replay = $this->leer('evidence/replay_predictions.json') ?? [];
        $intervalos = $this->leer('evidence/intervals.json')['receipt_available'] ?? [];
        $actual = $this->leer('evidence/current_interval.json');
        $huecos = $this->leer('evidence/missing_sensor_hours.json');
        $nuevas = $this->leer('evidence/new_label_test.json');
        $run = $this->leer('run.json');

        if (!$scores || !$this->modelo('ridge')) {
            return response()->json(['disponible' => false, 'motivo' => 'No se encontraron los activos del núcleo de crecimiento.'], 200);
        }

        $buscar = function (string $fam, string $var) use ($scores) {
            foreach ($scores as $s) {
                if ($s['family'] === $fam && $s['variant'] === $var) {
                    return $s;
                }
            }
            return null;
        };

        $modelos = [];
        foreach (self::MODELOS as $clave => $def) {
            $m = $this->modelo($clave);
            if (!$m) {
                continue;
            }
            $s = $buscar(...$def['replay']);
            $modelos[] = [
                'clave' => $clave,
                'nombre' => $def['nombre'],
                'algoritmo' => $def['algoritmo'],
                'rol' => $def['rol'],
                'familia' => $m['family'],
                'entradas' => array_map(fn ($k) => ['clave' => $k, 'etiqueta' => self::ETIQUETAS[$k] ?? $k], $m['feature_names']),
                'medias' => $m['means'],
                'escalas' => $m['scales'],
                'coeficientes' => $m['coefficients'] ?? null,
                'log_parametros' => $m['log_parameters'] ?? null,
                'penalizacion' => $m['ridge_penalty'] ?? null,
                'limites' => $m['feature_bounds'],
                'n_intervalos' => $m['n_training_intervals'],
                'ultimo_objetivo' => $m['training_last_target'],
                'mae_g' => $s['MAE_g'] ?? null,
                'rmse_g' => $s['RMSE_g'] ?? null,
                'max_error_g' => $s['max_error_g'] ?? null,
                'sesgo_g' => $s['bias_g'] ?? null,
            ];
        }

        // Comparadores y referencias que el informe conserva fuera del
        // nucleo: sus cifras provienen del informe integrado.
        $extra = $buscar('extra_trees', 'water_means') ?? $buscar('extra_trees', 'exposure');
        $referencias = [
            ['nombre' => 'Random Forest', 'rol' => 'Comparador (500 árboles, profundidad 3)', 'metrica' => 'MAE 13,72 g con sensores · 12,40 g sin sensores', 'fuente' => 'Informe integrado'],
            ['nombre' => 'Extra Trees', 'rol' => 'Diagnóstico (240 árboles, profundidad 2)', 'metrica' => $extra ? sprintf('MAE %s g · RMSE %s g', number_format($extra['MAE_g'], 2, ',', ''), number_format($extra['RMSE_g'], 2, ',', '')) : '-', 'fuente' => 'Corrida de validación'],
            ['nombre' => 'TGC generalizado', 'rol' => 'Referencia matemática', 'metrica' => 'MAPE 2,26 % en tres biometrías iniciales', 'fuente' => 'Informe integrado'],
            ['nombre' => 'von Bertalanffy (VBGF)', 'rol' => 'Referencia matemática', 'metrica' => 'MAPE 3,43 % en tres biometrías iniciales', 'fuente' => 'Informe integrado'],
            ['nombre' => 'Crecimiento térmico ΔL = −1,6707 + 0,09682·T', 'rol' => 'Simulación de escenarios (gemelo digital)', 'metrica' => 'R² 0,95 de su referencia de origen', 'fuente' => 'Informe integrado'],
            ['nombre' => 'DissolvedOxygen0DRoyer2021', 'rol' => 'Balance dinámico 0D de oxígeno', 'metrica' => 'Simulación física, sin MAE de crecimiento', 'fuente' => 'Informe integrado'],
        ];

        $evaluacion = [];
        foreach ($replay as $r) {
            foreach (['ridge', 'gp', 'ridge_context', 'gp_context'] as $clave) {
                [$fam, $var] = self::MODELOS[$clave]['replay'];
                if ($r['family'] === $fam && $r['variant'] === $var) {
                    $fila = $evaluacion[$r['target']] ?? [
                        'fecha' => $r['target'],
                        'desde' => $r['anchor'],
                        'peso_observado_g' => $r['actual_weight_g'],
                        'incremento_observado_g' => $r['observed_gain_g'],
                        'peso_inicial_g' => $r['actual_weight_g'] - $r['observed_gain_g'],
                    ];
                    $fila[$clave] = [
                        'peso_g' => $r['predicted_weight_g'],
                        'incremento_g' => $r['predicted_gain_g'],
                        'sd_g' => $r['conditional_latent_gain_sd_g'],
                        'error_g' => $r['absolute_error_g'],
                    ];
                    $evaluacion[$r['target']] = $fila;
                }
            }
        }
        ksort($evaluacion);

        $biometrias = [];
        foreach ($intervalos as $i) {
            $biometrias[$i['anchor']] = $i['initial_weight_g'];
            $biometrias[$i['target']] = $i['actual_weight_g'];
        }
        ksort($biometrias);

        // Intervalo en curso: se recalcula aqui con los activos congelados y
        // se compara con la salida de la corrida (predecir_ml.py).
        $intervaloActual = null;
        if ($actual) {
            $calculo = [];
            foreach (['ridge', 'gp'] as $clave) {
                try {
                    $calculo[$clave] = $this->predecir($this->modelo($clave), $actual['features']);
                } catch (\Throwable $e) {
                    $calculo[$clave] = null;
                }
            }
            $aceptadas = $actual['accepted_predictions'] ?? [];
            $intervaloActual = [
                'desde' => $actual['start'],
                'hasta' => $actual['end'],
                'estado' => $actual['status'],
                'admisible' => (bool) ($actual['quality']['eligible'] ?? false),
                'cobertura' => $actual['quality']['coverage'] ?? [],
                'horas_esperadas' => $actual['quality']['expected_hours'] ?? null,
                'horas_faltantes' => $huecos['missing_hours'] ?? null,
                'entradas' => $actual['features'],
                'corrida' => [
                    'ridge' => $aceptadas['ridge'] ?? ($actual['diagnostic_only_nonadmissible_predictions']['ridge'] ?? null),
                    'gp' => $aceptadas['gp'] ?? ($actual['diagnostic_only_nonadmissible_predictions']['gp'] ?? null),
                ],
                'laravel' => $calculo,
            ];
        }

        return response()->json([
            'disponible' => true,
            'corrida' => $run,
            'modelos' => $modelos,
            'referencias' => $referencias,
            'evaluacion' => array_values($evaluacion),
            'biometrias' => collect($biometrias)->map(fn ($p, $f) => ['fecha' => $f, 'peso_g' => $p])->values(),
            'intervalo_actual' => $intervaloActual,
            'nuevas_biometrias' => $nuevas['n_cases'] ?? 0,
        ]);
    }

    public function inferir(Request $request)
    {
        $datos = $request->validate([
            'initial_weight_g' => 'required|numeric|gt:0|max:5000',
            'duration_days' => 'required|numeric|gt:0|max:365',
            'temperature_mean' => 'required|numeric|between:0,45',
            'oxygen_mean' => 'required|numeric|between:0,30',
            'oxygen_p10' => 'required|numeric|between:0,30',
        ]);
        $datos = array_map('floatval', $datos);

        $salida = [];
        foreach (array_keys(self::MODELOS) as $clave) {
            $salida[$clave] = $this->predecir($this->modelo($clave), $datos);
        }

        return response()->json(['entradas' => $datos, 'resultados' => $salida]);
    }

    // Traduccion de predecir_ml.py: mismas medias, escalas, coeficientes,
    // kernel y factor de Cholesky del activo exportado.
    private function predecir(?array $m, array $f): array
    {
        if (!$m) {
            throw new \RuntimeException('Activo no disponible');
        }
        $x = array_map(fn ($k) => (float) $f[$k], $m['feature_names']);
        $z = [];
        foreach ($x as $i => $v) {
            $z[] = ($v - $m['means'][$i]) / $m['scales'][$i];
        }
        $sd = null;
        if ($m['family'] === 'gaussian_correlated') {
            $k = array_map(fn ($xt) => $this->covarianza($z, $xt, $m['log_parameters']), $m['x_train']);
            $ganancia = $m['y_mean'] + $m['y_scale'] * $this->dot($k, $m['alpha']);
            $v = [];
            foreach ($m['cholesky'] as $i => $fila) {
                $s = 0.0;
                for ($j = 0; $j < $i; $j++) {
                    $s += $fila[$j] * $v[$j];
                }
                $v[] = ($k[$i] - $s) / $fila[$i];
            }
            $sd = $m['y_scale'] * sqrt(max(0, $this->covarianza($z, $z, $m['log_parameters']) - $this->dot($v, $v)));
        } else {
            $ganancia = $m['y_mean'] + $m['y_scale'] * $this->dot($z, $m['coefficients']);
        }
        $fuera = [];
        foreach ($m['feature_names'] as $i => $k) {
            [$lo, $hi] = $m['feature_bounds'][$k];
            if ($x[$i] < $lo - 1e-9 || $x[$i] > $hi + 1e-9) {
                $fuera[] = $k;
            }
        }
        return [
            'predicted_gain_g' => $ganancia,
            'predicted_weight_g' => (float) $f['initial_weight_g'] + $ganancia,
            'conditional_latent_gain_sd_g' => $sd,
            'outside_training_features' => $fuera,
        ];
    }

    private function dot(array $a, array $b): float
    {
        $s = 0.0;
        foreach ($a as $i => $v) {
            $s += $v * $b[$i];
        }
        return $s;
    }

    private function covarianza(array $x, array $z, array $t): float
    {
        $d = count($x);
        $dist = 0.0;
        foreach ($x as $i => $v) {
            $dist += ($v - $z[$i]) ** 2;
        }
        $r = sqrt(3) * sqrt($dist / $d);
        return exp($t[0]) * (1 + $this->dot($x, $z) / $d) + exp($t[1]) * (1 + $r) * exp(-$r);
    }
}
