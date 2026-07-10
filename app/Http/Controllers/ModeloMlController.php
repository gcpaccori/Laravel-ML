<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use RuntimeException;
use Throwable;

class ModeloMlController extends Controller
{
    public function index()
    {
        return Inertia::render('Modules/Views/ModelosMl', [
            'title' => 'Modelos ML',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Modelos ML'],
            ],
        ]);
    }

    public function proyecciones(Request $request)
    {
        $baseUrl = rtrim(
            env('AQUACULTURE_BACKEND_URL', env('FASTAPI_BACKEND_URL', 'http://aquaculture_backend:8000')),
            '/'
        );
        $horizon = $this->horizonConfig((string) $request->input('horizonte', '72h'));

        try {
            $dashboard = $this->backendGet($baseUrl, '/frontend/dashboard');
            $pondId = $this->resolvePondId($request->input('piscina_id', 'T'), $dashboard);
            $lifecycle = $this->backendGet($baseUrl, '/ml/lifecycle/status');
            $activeAssets = $this->backendGet($baseUrl, '/ml/model-assets', [
                'status' => 'active',
                'include_payload' => 'false',
            ]);

            $selectedModels = $this->selectedModelCodes($activeAssets);
            $projection = $this->backendPost($baseUrl, "/digital-twin/{$pondId}/projection", [
                'horizon_hours' => $horizon['hours'],
                'step_hours' => $horizon['step'],
                'selected_models' => $selectedModels,
                'variable_adjustments_per_hour' => new \stdClass(),
                'operational_controls' => [
                    'aeration_percent' => 60,
                    'filtration_percent' => 60,
                    'feeding_percent' => 100,
                ],
            ]);

            return response()->json(
                $this->buildResponse(
                    baseUrl: $baseUrl,
                    pondId: $pondId,
                    horizon: $horizon,
                    requestedWindow: (string) $request->input('ventana', '30d'),
                    dashboard: $dashboard,
                    lifecycle: $lifecycle,
                    portfolio: [],
                    activeAssets: $activeAssets,
                    projection: $projection,
                )
            );
        } catch (Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo consultar el backend MLOps real de aquaculture_backend.',
                'backend_url' => $baseUrl,
                'legacy_flask_used' => false,
                'detail' => $exception->getMessage(),
            ], 502);
        }
    }

    private function backendGet(string $baseUrl, string $path, array $query = []): array
    {
        $response = Http::acceptJson()->timeout(30)->get("{$baseUrl}/api/v1{$path}", $query);
        if ($response->failed()) {
            throw new RuntimeException("GET {$path} fallo con HTTP {$response->status()}");
        }
        return $response->json() ?? [];
    }

    private function backendPost(string $baseUrl, string $path, array $payload): array
    {
        $response = Http::acceptJson()->timeout(30)->post("{$baseUrl}/api/v1{$path}", $payload);
        if ($response->failed()) {
            throw new RuntimeException("POST {$path} fallo con HTTP {$response->status()}");
        }
        return $response->json() ?? [];
    }

    private function horizonConfig(string $value): array
    {
        return match ($value) {
            '24h' => ['key' => '24h', 'label' => '24 horas', 'hours' => 24, 'step' => 1],
            '7d' => ['key' => '7d', 'label' => '7 dias', 'hours' => 168, 'step' => 12],
            '30d' => ['key' => '30d', 'label' => '30 dias', 'hours' => 720, 'step' => 24],
            default => ['key' => '72h', 'label' => '72 horas', 'hours' => 72, 'step' => 3],
        };
    }

    private function resolvePondId(mixed $piscinaId, array $dashboard): string
    {
        $ponds = $dashboard['ponds'] ?? [];
        $default = $dashboard['selection']['pond_id'] ?? ($ponds[0]['id'] ?? 'LEGACY-POND-1');

        if ($piscinaId === null || $piscinaId === '' || $piscinaId === 'T') {
            return $default;
        }

        $candidate = is_numeric($piscinaId) ? "LEGACY-POND-{$piscinaId}" : (string) $piscinaId;
        foreach ($ponds as $pond) {
            if (($pond['id'] ?? null) === $candidate || (string) ($pond['extra_metadata']['source_id'] ?? '') === (string) $piscinaId) {
                return (string) $pond['id'];
            }
        }

        return $default;
    }

    private function selectedModelCodes(array $activeAssets): array
    {
        return ['DO_DYNAMIC_0D_ROYER_2021'];
    }

    private function buildResponse(
        string $baseUrl,
        string $pondId,
        array $horizon,
        string $requestedWindow,
        array $dashboard,
        array $lifecycle,
        array $portfolio,
        array $activeAssets,
        array $projection,
    ): array {
        $points = $projection['points'] ?? [];
        $baseline = $projection['baseline_values'] ?? [];
        $units = $projection['baseline_units'] ?? [];
        $observedAt = $projection['baseline_observed_at'] ?? [];
        $pondName = $this->pondName($pondId, $dashboard);
        $doAsset = $this->compatibleDoAsset($activeAssets);
        $doAssetAudit = $doAsset ? $this->auditDoAsset($baseUrl, $doAsset, $baseline) : null;

        $models = [];
        $models[] = $this->digitalTwinDissolvedOxygenModel($projection, $points, $baseline, $units);
        $models[] = $this->nitrateScenarioModel($activeAssets, $projection, $points, $baseline, $units);

        return [
            'status' => 'ok',
            'backend_engine' => 'aquaculture_backend FastAPI',
            'backend_url' => $baseUrl,
            'legacy_flask_used' => false,
            'pond_id' => $pondId,
            'latest' => [
                'timestamp' => $observedAt['dissolved_oxygen_mg_l'] ?? $observedAt['nitrate_ion'] ?? null,
                'piscina' => $pondName,
                'ion_nitrato' => $baseline['nitrate_ion'] ?? null,
                'ion_nitrato_unit' => $this->displayUnit($units['nitrate_ion'] ?? null),
                'oxigeno_disuelto' => $baseline['dissolved_oxygen_mg_l'] ?? null,
                'oxigeno_disuelto_unit' => $this->displayUnit($units['dissolved_oxygen_mg_l'] ?? 'mg/L'),
                'ph' => $baseline['ph'] ?? null,
                'temperatura' => $baseline['water_temperature_c'] ?? null,
            ],
            'summary' => [
                'samples' => $dashboard['system_metrics']['clean_measurements_loaded'] ?? null,
                'available_models' => count($models),
                'active_assets' => $lifecycle['active_model_assets'] ?? count($activeAssets),
                'training_jobs' => $lifecycle['total_training_jobs'] ?? null,
                'total_assets' => $lifecycle['total_model_assets'] ?? count($activeAssets),
                'backend_status' => $dashboard['backend']['status'] ?? 'online',
                'projection_method' => $projection['traceability']['projection_method'] ?? null,
            ],
            'filters' => [
                'horizonte' => $horizon['key'],
                'horizonte_label' => $horizon['label'],
                'step_hours' => $horizon['step'],
                'ventana' => $requestedWindow,
            ],
            'lifecycle' => [
                'datasets_enabled' => $lifecycle['datasets_enabled'] ?? null,
                'training_enabled' => $lifecycle['training_enabled'] ?? null,
                'model_assets_enabled' => $lifecycle['model_assets_enabled'] ?? null,
                'active_model_assets' => $lifecycle['active_model_assets'] ?? null,
                'total_model_assets' => $lifecycle['total_model_assets'] ?? null,
                'routes' => $lifecycle['routes'] ?? [],
            ],
            'portfolio' => $this->summarizePortfolio($portfolio),
            'active_assets' => $this->summarizeAssets($activeAssets),
            'models' => $models,
            'warnings' => array_values(array_unique(array_filter(array_merge(
                $projection['warnings'] ?? [],
                $this->qualityWarnings($doAsset, $activeAssets, $doAssetAudit)
            )))),
            'traceability' => [
                'source' => 'aquaculture_backend:/api/v1',
                'legacy_flask_used' => false,
                'digital_twin_projection' => "/digital-twin/{$pondId}/projection",
                'do_ml_asset_audit' => $doAssetAudit,
                'selected_models' => $projection['traceability']['selected_models'] ?? [],
                'projection_method' => $projection['traceability']['projection_method'] ?? null,
                'model_layer_semantics' => $projection['traceability']['model_layer_semantics'] ?? null,
                'generated_data_used' => $projection['traceability']['generated_data_used'] ?? null,
                'decision_grade' => $projection['traceability']['decision_grade'] ?? null,
            ],
        ];
    }

    private function compatibleDoAsset(array $assets): ?array
    {
        $preferred = ['ML_SUPERVISED_LINEAR_REG', 'ML_NONLINEAR_RANDOM_FOREST'];
        foreach ($preferred as $code) {
            foreach ($assets as $asset) {
                $payload = $asset['artifact_payload'] ?? [];
                $features = $payload['feature_names'] ?? [];
                if (($asset['model_code'] ?? null) === $code
                    && ($payload['target_variable'] ?? null) === 'dissolved_oxygen_mg_l'
                    && ! $this->requiresLagFeatures($features)
                ) {
                    return $asset;
                }
            }
        }

        return null;
    }

    private function requiresLagFeatures(array $features): bool
    {
        foreach ($features as $feature) {
            if (str_contains((string) $feature, '_t_minus_')) {
                return true;
            }
        }
        return false;
    }

    private function auditDoAsset(string $baseUrl, array $asset, array $baseline): array
    {
        $features = $asset['artifact_payload']['feature_names'] ?? [];
        $payload = [];
        foreach ($features as $feature) {
            $payload[$feature] = (float) ($baseline[$feature] ?? 0);
        }

        try {
            $prediction = $this->backendPost($baseUrl, "/models/{$asset['model_code']}/predict", [
                'features' => $payload,
            ]);
        } catch (Throwable $exception) {
            return [
                'model_code' => $asset['model_code'] ?? null,
                'asset_id' => $asset['asset_id'] ?? null,
                'status' => 'predict_failed',
                'usable_for_projection' => false,
                'message' => $exception->getMessage(),
            ];
        }

        $value = is_numeric($prediction['prediction'] ?? null)
            ? (float) $prediction['prediction']
            : null;
        $observed = is_numeric($baseline['dissolved_oxygen_mg_l'] ?? null)
            ? (float) $baseline['dissolved_oxygen_mg_l']
            : null;
        $usable = $this->doPredictionPassesSanity($value, $observed);

        return [
            'model_code' => $asset['model_code'] ?? null,
            'asset_id' => $asset['asset_id'] ?? null,
            'version' => $asset['version'] ?? null,
            'status' => $usable ? 'passed_sanity_check' : 'failed_sanity_check',
            'usable_for_projection' => $usable,
            'baseline_observed_mg_l' => $observed,
            'baseline_prediction_mg_l' => $value,
            'prediction_id' => $prediction['traceability']['prediction_id'] ?? null,
            'reason' => $usable
                ? 'Prediccion puntual dentro de rango operativo.'
                : 'Prediccion puntual fuera de rango o demasiado lejos de la medicion actual.',
        ];
    }

    private function doPredictionPassesSanity(?float $prediction, ?float $observed): bool
    {
        if ($prediction === null || $prediction < 2.0 || $prediction > 15.0) {
            return false;
        }
        if ($observed === null) {
            return true;
        }

        return abs($prediction - $observed) <= max(2.0, abs($observed) * 0.35);
    }

    private function mlDissolvedOxygenModel(array $asset, array $mlSeries, array $points, array $baseline, array $units): array
    {
        $unit = $this->displayUnit($units['dissolved_oxygen_mg_l'] ?? 'mg/L');
        $twinSeries = $this->seriesFromProjection($points, 'dissolved_oxygen_mg_l');
        $metrics = $asset['metrics_json'] ?? [];
        $r2 = $metrics['r2'] ?? null;

        return [
            'code' => $asset['model_code'] ?? 'ML_SUPERVISED_LINEAR_REG',
            'name' => 'Oxigeno disuelto - inferencia ML activa',
            'message' => 'Usa el asset activo del ciclo MLOps y ejecuta predict contra aquaculture_backend.',
            'status' => 'asset_activo',
            'source' => 'aquaculture_backend.ml_active_asset',
            'engine' => 'FastAPI MLOps',
            'asset_id' => $asset['asset_id'] ?? null,
            'version' => $asset['version'] ?? null,
            'target_variable' => $asset['artifact_payload']['target_variable'] ?? null,
            'features' => $asset['artifact_payload']['feature_names'] ?? [],
            'metrics' => $metrics,
            'mae' => $metrics['mae'] ?? null,
            'unit' => $unit,
            'current_value' => $baseline['dissolved_oxygen_mg_l'] ?? null,
            'forecast' => $mlSeries,
            'chart' => $this->chartOptions(
                'Oxigeno disuelto',
                $unit,
                [
                    ['name' => 'Gemelo digital / escenario', 'data' => $twinSeries, 'color' => '#60A5FA'],
                    ['name' => 'Asset ML activo', 'data' => $mlSeries, 'color' => '#16A34A'],
                ]
            ),
            'traceability' => [
                'route' => "/models/{$asset['model_code']}/predict",
                'asset_status' => $asset['status'] ?? null,
                'artifact_path' => $asset['artifact_path'] ?? null,
                'feature_set_id' => $asset['feature_set_id'] ?? null,
                'training_job_id' => $asset['training_job_id'] ?? null,
                'quality_note' => $r2 !== null && $r2 <= 0
                    ? 'Asset activo, pero R2 no positivo; revisar generalizacion antes de decisiones productivas.'
                    : 'Asset activo usado para inferencia.',
            ],
        ];
    }

    private function digitalTwinDissolvedOxygenModel(array $projection, array $points, array $baseline, array $units): array
    {
        $unit = $this->displayUnit($units['dissolved_oxygen_mg_l'] ?? 'mg/L');
        $participation = $this->participation($projection, 'DO_DYNAMIC_0D_ROYER_2021');

        return [
            'code' => 'DO_DYNAMIC_0D_ROYER_2021',
            'name' => 'Oxigeno disuelto - modelo mecanistico',
            'message' => 'Participa en el gemelo digital; la curva es escenario operacional trazable, no Flask legacy.',
            'status' => 'gemelo_digital',
            'source' => 'aquaculture_backend.digital_twin_projection',
            'engine' => 'FastAPI model runner',
            'asset_id' => $participation['asset_id'] ?? null,
            'unit' => $unit,
            'current_value' => $baseline['dissolved_oxygen_mg_l'] ?? null,
            'forecast' => $this->seriesFromProjection($points, 'dissolved_oxygen_mg_l'),
            'chart' => $this->chartOptions(
                'Oxigeno disuelto - escenario',
                $unit,
                [
                    [
                        'name' => 'Escenario gemelo digital',
                        'data' => $this->seriesFromProjection($points, 'dissolved_oxygen_mg_l'),
                        'color' => '#2563EB',
                    ],
                ]
            ),
            'traceability' => [
                'route' => '/digital-twin/{pond_id}/projection',
                'participation_status' => $participation['status'] ?? null,
                'influence_weight' => $participation['influence_weight'] ?? null,
                'explanation' => $participation['explanation'] ?? null,
                'projection_method' => $projection['traceability']['projection_method'] ?? null,
                'model_layer_semantics' => $projection['traceability']['model_layer_semantics'] ?? null,
            ],
        ];
    }

    private function nitrateScenarioModel(array $activeAssets, array $projection, array $points, array $baseline, array $units): array
    {
        $unit = $this->displayUnit($units['nitrate_ion'] ?? 'source_unit');
        $nitrateAsset = $this->assetByTarget($activeAssets, 'nitrate_ion');

        return [
            'code' => 'NITRATE_ION_OPERATIONAL_SCENARIO',
            'name' => 'Ion nitrato - escenario del ciclo nitrogenado',
            'message' => $nitrateAsset
                ? 'Existe asset activo para nitrate_ion; se muestra junto al escenario operacional.'
                : 'No hay asset ML activo con target nitrate_ion; se muestra solo escenario operacional trazable.',
            'status' => $nitrateAsset ? 'asset_activo' : 'escenario_sin_asset',
            'source' => 'aquaculture_backend.digital_twin_projection',
            'engine' => 'FastAPI digital twin',
            'asset_id' => $nitrateAsset['asset_id'] ?? null,
            'unit' => $unit,
            'current_value' => $baseline['nitrate_ion'] ?? null,
            'forecast' => $this->seriesFromProjection($points, 'nitrate_ion'),
            'chart' => $this->chartOptions(
                'Ion nitrato',
                $unit,
                [
                    [
                        'name' => 'Escenario gemelo digital',
                        'data' => $this->seriesFromProjection($points, 'nitrate_ion'),
                        'color' => '#F59E0B',
                    ],
                ]
            ),
            'traceability' => [
                'route' => '/digital-twin/{pond_id}/projection',
                'asset_status' => $nitrateAsset['status'] ?? null,
                'projection_method' => $projection['traceability']['projection_method'] ?? null,
                'model_layer_semantics' => $projection['traceability']['model_layer_semantics'] ?? null,
                'quality_note' => $nitrateAsset
                    ? 'Asset activo encontrado para nitrate_ion.'
                    : 'Sin asset productivo para nitrate_ion; no se etiqueta como inferencia ML.',
            ],
        ];
    }

    private function seriesFromProjection(array $points, string $code): array
    {
        return array_values(array_map(function (array $point) use ($code) {
            return [
                'timestamp' => $point['timestamp'] ?? null,
                'hour' => $point['hour'] ?? null,
                'label' => 'h ' . ($point['hour'] ?? 0),
                'value' => is_numeric($point['values'][$code] ?? null)
                    ? round((float) $point['values'][$code], 4)
                    : null,
            ];
        }, $points));
    }

    private function chartOptions(string $title, string $unit, array $series): array
    {
        $labels = [];
        foreach ($series as $item) {
            foreach ($item['data'] as $point) {
                $labels[] = $point['label'] ?? null;
            }
            if ($labels) {
                break;
            }
        }

        return [
            'tooltip' => ['trigger' => 'axis'],
            'legend' => ['top' => 0],
            'grid' => ['left' => '3%', 'right' => '4%', 'bottom' => '3%', 'containLabel' => true],
            'xAxis' => ['type' => 'category', 'boundaryGap' => false, 'data' => $labels],
            'yAxis' => ['type' => 'value', 'name' => $unit],
            'series' => array_map(fn (array $item) => [
                'name' => $item['name'],
                'type' => 'line',
                'smooth' => true,
                'symbolSize' => 4,
                'connectNulls' => false,
                'lineStyle' => ['width' => 3, 'color' => $item['color']],
                'itemStyle' => ['color' => $item['color']],
                'areaStyle' => ['opacity' => 0.08],
                'data' => array_map(fn (array $point) => $point['value'], $item['data']),
            ], $series),
            'title' => ['text' => $title, 'left' => 0, 'top' => 0, 'textStyle' => ['fontSize' => 13]],
        ];
    }

    private function participation(array $projection, string $modelCode): array
    {
        foreach ($projection['model_participation'] ?? [] as $item) {
            if (($item['model_code'] ?? null) === $modelCode) {
                return $item;
            }
        }
        return [];
    }

    private function assetByTarget(array $assets, string $target): ?array
    {
        foreach ($assets as $asset) {
            if (($asset['artifact_payload']['target_variable'] ?? null) === $target) {
                return $asset;
            }
        }
        return null;
    }

    private function summarizePortfolio(array $portfolio): array
    {
        return array_values(array_map(fn (array $item) => [
            'model_code' => $item['model_code'] ?? null,
            'family' => $item['family'] ?? null,
            'readiness_status' => $item['readiness_status'] ?? null,
            'can_train' => $item['can_train'] ?? false,
            'active_asset_id' => $item['active_asset_id'] ?? null,
            'active_metrics' => $item['active_metrics'] ?? [],
            'missing_variables' => $item['missing_variables'] ?? [],
        ], $portfolio));
    }

    private function summarizeAssets(array $assets): array
    {
        usort($assets, fn (array $left, array $right) => $this->assetPriority($left) <=> $this->assetPriority($right));

        return array_values(array_map(fn (array $asset) => [
            'asset_id' => $asset['asset_id'] ?? null,
            'model_code' => $asset['model_code'] ?? null,
            'version' => $asset['version'] ?? null,
            'status' => $asset['status'] ?? null,
            'target_variable' => $asset['artifact_payload']['target_variable'] ?? null,
            'feature_names' => $asset['artifact_payload']['feature_names'] ?? [],
            'metrics' => $asset['metrics_json'] ?? [],
        ], $assets));
    }

    private function assetPriority(array $asset): int
    {
        $code = $asset['model_code'] ?? '';
        $target = $asset['artifact_payload']['target_variable'] ?? '';

        if ($target === 'dissolved_oxygen_mg_l' && $code === 'ML_SUPERVISED_LINEAR_REG') {
            return 0;
        }
        if ($target === 'dissolved_oxygen_mg_l' && $code === 'ML_NONLINEAR_RANDOM_FOREST') {
            return 1;
        }
        if ($target === 'dissolved_oxygen_mg_l') {
            return 2;
        }
        if ($target === 'nitrate_ion') {
            return 3;
        }
        return 10;
    }

    private function qualityWarnings(?array $doAsset, array $assets, ?array $doAssetAudit = null): array
    {
        $warnings = [];
        if (! $doAsset) {
            $warnings[] = 'No se encontro asset ML activo compatible para oxigeno disuelto; se muestra solo gemelo digital.';
        } elseif (($doAssetAudit['usable_for_projection'] ?? false) !== true) {
            $observed = $doAssetAudit['baseline_observed_mg_l'] ?? null;
            $predicted = $doAssetAudit['baseline_prediction_mg_l'] ?? null;
            $warnings[] = sprintf(
                'Asset ML de oxigeno activo auditado pero excluido de las curvas: predice %s mg/L frente a %s mg/L observado.',
                is_numeric($predicted) ? round((float) $predicted, 4) : 'N/D',
                is_numeric($observed) ? round((float) $observed, 4) : 'N/D'
            );
        } elseif (($doAsset['metrics_json']['r2'] ?? 1) <= 0) {
            $warnings[] = 'El asset ML activo de oxigeno disuelto tiene R2 no positivo; queda como diagnostico, no como decision productiva.';
        }
        if (! $this->assetByTarget($assets, 'nitrate_ion')) {
            $warnings[] = 'No existe asset ML activo para ion nitrato; la curva de nitrato es escenario operacional trazable.';
        }
        return $warnings;
    }

    private function pondName(string $pondId, array $dashboard): string
    {
        foreach ($dashboard['ponds'] ?? [] as $pond) {
            if (($pond['id'] ?? null) === $pondId) {
                return $pond['name'] ?? $pondId;
            }
        }
        return $pondId;
    }

    private function displayUnit(?string $unit): string
    {
        return match ($unit) {
            'degC' => 'C',
            'source_unit' => 'unidad fuente',
            null, '' => '',
            default => $unit,
        };
    }
}
