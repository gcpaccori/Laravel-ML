<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ModelAlertDashboardService
{
    private const LIGHT_MODEL_CODE = 'LIGHT_FEED_RESPONSE_CLASSIFIER_V1';

    private const LIGHT_VARIABLES = [
        'underwater_illuminance_lux',
        'illuminance_lux',
        'light_intensity_lux',
        'light_lux',
        'lux',
        'ppfd_umol_m2_s',
    ];

    private const MODEL_CODES = [
        'SVM_OD_FORECAST_1H',
        'OXYGEN_STATUS_MODEL',
        'TILAPIA_GROWTH_TEMPERATURE',
        'WATER_QUALITY_INDEX_ICA',
        'WATER_QUALITY_INDEX_ICA_SVM',
        'BPNN_MEA_FEED_INTAKE',
        'BIOFLOC_WATER_QUALITY',
        self::LIGHT_MODEL_CODE,
    ];

    public function dashboard(string $pondId, int $windowHours = 24, bool $refresh = false): array
    {
        $resolvedPondId = $this->resolvePondId($pondId);
        $windowHours = max(6, min(2160, $windowHours));
        $cacheKey = "model-alerts:dashboard:{$resolvedPondId}:{$windowHours}";
        $staleKey = "{$cacheKey}:stale";

        if ($refresh) {
            Cache::forget($cacheKey);
        } elseif (is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        try {
            $payload = $this->fetchDashboard($resolvedPondId, $windowHours);
            Cache::put($cacheKey, $payload, now()->addSeconds($this->cacheSeconds()));
            Cache::put($staleKey, $payload, now()->addSeconds($this->staleSeconds()));

            return $payload;
        } catch (Throwable $exception) {
            $stale = Cache::get($staleKey);
            if (is_array($stale)) {
                $stale['meta'] = array_merge($stale['meta'] ?? [], [
                    'stale' => true,
                    'degraded' => true,
                    'message' => 'Se muestra el ultimo resultado disponible mientras FastAPI se recupera.',
                ]);

                return $stale;
            }

            $unavailable = $this->unavailableDashboard($resolvedPondId, $windowHours);
            Cache::put($cacheKey, $unavailable, now()->addSeconds(min(30, $this->cacheSeconds())));

            return $unavailable;
        }
    }

    public function lightStatus(string $pondId, bool $refresh = false): array
    {
        $resolvedPondId = $this->resolvePondId($pondId);
        $cacheKey = "model-alerts:light:{$resolvedPondId}";
        $staleKey = "{$cacheKey}:stale";

        if ($refresh) {
            Cache::forget($cacheKey);
        } elseif (is_array($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        try {
            $baseUrl = $this->backendUrl();
            $responses = Http::pool(fn (Pool $pool) => [
                $pool->as('sensors')->acceptJson()
                    ->connectTimeout($this->connectTimeout())
                    ->timeout(min($this->timeout(), 8))
                    ->get("{$baseUrl}/sensors", ['pond_id' => $resolvedPondId]),
                $pool->as('measurements')->acceptJson()
                    ->connectTimeout($this->connectTimeout())
                    ->timeout(min($this->timeout(), 10))
                    ->get("{$baseUrl}/measurements/clean", [
                        'pond_id' => $resolvedPondId,
                        'limit' => 1000,
                    ]),
            ]);

            if (! $this->responseSuccessful($responses['sensors'] ?? null)
                && ! $this->responseSuccessful($responses['measurements'] ?? null)) {
                throw new RuntimeException('FastAPI did not return light context.');
            }

            $payload = array_merge(
                $this->lightContext(
                    $this->listPayload($this->responseJson($responses['sensors'] ?? null), ['data', 'sensors']),
                    $this->listPayload($this->responseJson($responses['measurements'] ?? null), ['data', 'measurements']),
                ),
                [
                    'pond_id' => $resolvedPondId,
                    'generated_at' => now()->toIso8601String(),
                    'meta' => [
                        'source' => 'fastapi_local',
                        'stale' => false,
                        'degraded' => false,
                    ],
                ],
            );

            Cache::put($cacheKey, $payload, now()->addSeconds($this->cacheSeconds()));
            Cache::put($staleKey, $payload, now()->addSeconds($this->staleSeconds()));

            return $payload;
        } catch (Throwable) {
            $stale = Cache::get($staleKey);
            if (is_array($stale)) {
                $stale['meta'] = array_merge($stale['meta'] ?? [], [
                    'stale' => true,
                    'degraded' => true,
                    'message' => 'Se conserva la ultima lectura de luz disponible.',
                ]);

                return $stale;
            }

            $unavailable = array_merge($this->lightContext([], []), [
                'pond_id' => $resolvedPondId,
                'generated_at' => now()->toIso8601String(),
                'meta' => [
                    'source' => 'unavailable',
                    'stale' => false,
                    'degraded' => true,
                    'message' => 'No se pudo consultar el estado del sensor de luz.',
                ],
            ]);
            Cache::put($cacheKey, $unavailable, now()->addSeconds(min(30, $this->cacheSeconds())));

            return $unavailable;
        }
    }

    public function lightScenario(array $input): array
    {
        $maximumLux = (float) ($input['maximum_lux'] ?? 500);
        $photoperiodHours = (float) ($input['photoperiod_hours'] ?? 12);
        $dawnHour = (float) ($input['dawn_hour'] ?? 6);
        $horizonHours = (int) ($input['horizon_hours'] ?? 24);
        $currentLux = isset($input['current_lux']) ? (float) $input['current_lux'] : null;
        $startsAt = CarbonImmutable::now(config('app.timezone', 'UTC'))->startOfHour();
        $points = [];

        for ($step = 0; $step <= $horizonHours * 2; $step++) {
            $timestamp = $startsAt->addMinutes($step * 30);
            $hour = (float) $timestamp->hour + ((float) $timestamp->minute / 60);
            $elapsed = fmod($hour - $dawnHour + 24, 24);
            $isContinuous = $photoperiodHours >= 23.9;
            $insideLightWindow = $isContinuous || ($photoperiodHours > 0 && $elapsed <= $photoperiodHours);
            $shape = 0.0;

            if ($isContinuous) {
                $shape = 1.0;
            } elseif ($insideLightWindow) {
                $shape = max(0.0, sin(M_PI * ($elapsed / $photoperiodHours)));
            }

            $lux = round($maximumLux * $shape, 2);
            $phase = 'oscuro';
            if ($insideLightWindow) {
                $progress = $isContinuous ? 0.5 : $elapsed / max($photoperiodHours, 0.1);
                $phase = $progress < 0.15 ? 'amanecer' : ($progress > 0.85 ? 'atardecer' : 'dia');
            }

            $points[] = [
                'timestamp' => $timestamp->toIso8601String(),
                'value' => $lux,
                'unit' => 'lux',
                'phase' => $phase,
            ];
        }

        return [
            'mode' => 'manual_protocol_scenario',
            'model_code' => self::LIGHT_MODEL_CODE,
            'generated_at' => now()->toIso8601String(),
            'inputs' => [
                'maximum_lux' => $maximumLux,
                'photoperiod_hours' => $photoperiodHours,
                'dawn_hour' => $dawnHour,
                'horizon_hours' => $horizonHours,
            ],
            'points' => $points,
            'chart' => $this->lineChart(
                'Escenario manual del protocolo de luz',
                'lux',
                [[
                    'name' => 'Protocolo simulado',
                    'type' => 'line',
                    'showSymbol' => false,
                    'smooth' => true,
                    'lineStyle' => ['type' => 'dashed', 'width' => 2, 'color' => '#f59e0b'],
                    'itemStyle' => ['color' => '#f59e0b'],
                    'data' => array_map(fn (array $point) => [$point['timestamp'], $point['value']], $points),
                ]],
            ),
            'twin' => [
                'light_mode' => 'manual',
                'light_level' => $currentLux ?? ($points[0]['value'] ?? 0),
                'unit' => 'lux',
                'phase' => $points[0]['phase'] ?? 'oscuro',
            ],
            'alarm_preview' => [
                'status' => 'not_emitted',
                'can_emit' => false,
                'severity' => null,
                'message' => 'Este escenario sirve para explorar el gemelo; no es una prediccion entrenada ni genera una alarma productiva.',
            ],
        ];
    }

    private function fetchDashboard(string $pondId, int $windowHours): array
    {
        $native = Http::acceptJson()
            ->connectTimeout($this->connectTimeout())
            ->timeout(min($this->timeout(), 8))
            ->get("{$this->backendUrl()}/ponds/{$pondId}/model-alerts/dashboard", [
                'window_hours' => $windowHours,
            ]);

        if ($native->successful() && is_array($native->json())) {
            $payload = $native->json();
            $payload['meta'] = array_merge($payload['meta'] ?? [], [
                'source' => 'fastapi_model_alert_contract',
                'stale' => false,
                'degraded' => false,
            ]);

            return $payload;
        }

        return $this->legacyCompatibilityDashboard($pondId, $windowHours);
    }

    private function legacyCompatibilityDashboard(string $pondId, int $windowHours): array
    {
        $baseUrl = $this->backendUrl();
        $responses = Http::pool(fn (Pool $pool) => [
            $pool->as('dashboard')->acceptJson()
                ->connectTimeout($this->connectTimeout())
                ->timeout($this->timeout())
                ->get("{$baseUrl}/ponds/{$pondId}/ai/dashboard", [
                    'window_hours' => $windowHours,
                    'growth_projection_days' => 7,
                ]),
            $pool->as('sensors')->acceptJson()
                ->connectTimeout($this->connectTimeout())
                ->timeout(min($this->timeout(), 8))
                ->get("{$baseUrl}/sensors", ['pond_id' => $pondId]),
            $pool->as('alerts')->acceptJson()
                ->connectTimeout($this->connectTimeout())
                ->timeout(min($this->timeout(), 8))
                ->get("{$baseUrl}/alerts", ['pond_id' => $pondId]),
            $pool->as('measurements')->acceptJson()
                ->connectTimeout($this->connectTimeout())
                ->timeout(min($this->timeout(), 10))
                ->get("{$baseUrl}/measurements/clean", [
                    'pond_id' => $pondId,
                    'limit' => 1000,
                ]),
        ]);

        $dashboard = $this->responseJson($responses['dashboard'] ?? null);
        $sensors = $this->listPayload($this->responseJson($responses['sensors'] ?? null), ['data', 'sensors']);
        $alerts = $this->listPayload($this->responseJson($responses['alerts'] ?? null), ['data', 'alerts']);
        $measurements = $this->listPayload($this->responseJson($responses['measurements'] ?? null), ['data', 'measurements']);

        if ($dashboard === [] && $sensors === [] && $alerts === [] && $measurements === []) {
            throw new RuntimeException('FastAPI did not return model alert inputs.');
        }

        $light = $this->lightContext($sensors, $measurements);
        $cards = $this->modelCards($dashboard, $light);
        $observations = $this->legacyModelObservations($alerts);

        return [
            'schema_version' => '1.0-compat',
            'generated_at' => now()->toIso8601String(),
            'pond_id' => $pondId,
            'summary' => [
                'active_events' => 0,
                'can_emit' => collect($cards)->where('can_emit', true)->count(),
                'shadow' => collect($cards)->where('maturity', 'shadow')->count(),
                'blocked' => collect($cards)->whereIn('maturity', ['blocked_inputs', 'collecting_data'])->count(),
                'technical_observations' => count($observations),
            ],
            'models' => $cards,
            'events' => [],
            'technical_observations' => $observations,
            'light' => $light,
            'meta' => [
                'source' => 'legacy_fastapi_adapter',
                'stale' => false,
                'degraded' => false,
                'message' => 'Compatibilidad temporal: las observaciones antiguas no se presentan como alarmas productivas.',
                'window_hours' => $windowHours,
            ],
        ];
    }

    private function modelCards(array $dashboard, array $light): array
    {
        $models = collect($dashboard['models'] ?? [])->keyBy('code');
        $svm = (array) ($models->get('SVM_OD_FORECAST_1H') ?? []);
        $growth = (array) ($models->get('TILAPIA_GROWTH_TEMPERATURE') ?? []);
        $ica = (array) ($models->get('WATER_QUALITY_INDEX_ICA') ?? []);

        $svmStatus = (string) ($svm['status'] ?? 'sin_datos');
        $growthHasBiometrics = ! empty($growth['biometric_context'] ?? $dashboard['biometrics'] ?? null);
        $icaReady = in_array((string) ($ica['status'] ?? ''), ['calculado', 'calculated'], true);

        return [
            $this->card(
                code: 'WATER_QUALITY_INDEX_ICA',
                alarmCode: 'MODEL_ICA_DEGRADATION',
                name: 'Deterioro del indice de calidad de agua',
                purpose: 'Detecta un empeoramiento persistente del ICA calculado con temperatura, pH, oxigeno e ion nitrato.',
                maturity: $icaReady ? 'ready_for_policy' : 'blocked_inputs',
                detail: $icaReady
                    ? 'La formula esta disponible; falta aprobar la politica que convertira su salida en alarma.'
                    : 'El indice no puede evaluarse con los datos disponibles.',
                horizon: 'Estado actual y cambio persistente',
                inputs: ['Temperatura', 'pH', 'Oxigeno disuelto', 'Ion nitrato'],
                model: $ica,
                policy: 'Pendiente de aprobacion acuicola',
            ),
            $this->card(
                code: 'TILAPIA_GROWTH_TEMPERATURE',
                alarmCode: 'MODEL_GROWTH_DEVIATION',
                name: 'Desviacion del crecimiento esperado',
                purpose: 'Compara una biometria nueva contra la trayectoria proyectada para el lote.',
                maturity: $growthHasBiometrics ? 'shadow' : 'blocked_inputs',
                detail: $growthHasBiometrics
                    ? 'La proyeccion se muestra en evaluacion; necesita backtest y una banda de error local.'
                    : 'Falta una biometria util para construir y comprobar la proyeccion.',
                horizon: '1 a 365 dias',
                inputs: ['Temperatura', 'Longitud', 'Peso', 'Fecha de biometria'],
                model: $growth,
                policy: 'Desviacion fuera de una banda validada',
            ),
            $this->card(
                code: 'SVM_OD_FORECAST_1H',
                alarmCode: 'MODEL_OD_THRESHOLD_FORECAST',
                name: 'Cruce futuro de oxigeno disuelto',
                purpose: 'Anticipa si el oxigeno proyectado cruzara un limite aprobado dentro de una hora.',
                maturity: $svmStatus === 'asset_activo' ? 'ready_for_policy' : ($svmStatus === 'candidato_bloqueado' ? 'shadow' : 'blocked_inputs'),
                detail: $svmStatus === 'asset_activo'
                    ? 'Existe un artefacto activo; todavia se requiere una politica aprobada para emitir alarmas.'
                    : 'El artefacto actual permanece en evaluacion y no puede alarmar al productor.',
                horizon: '1 hora',
                inputs: ['Temperatura', 'pH', 'Oxigeno disuelto', 'Ion nitrato', 'Historial temporal'],
                model: $svm,
                policy: 'Cruce futuro de un limite configurado',
            ),
            $this->card(
                code: self::LIGHT_MODEL_CODE,
                alarmCode: 'MODEL_LIGHT_FEED_RESPONSE_RISK',
                name: 'Luz y respuesta alimentaria',
                purpose: 'Estimara la probabilidad de respuesta alimentaria usando luz subacuatica, fotoperiodo y contexto del agua.',
                maturity: $light['sensor_registered'] ? 'collecting_data' : 'blocked_inputs',
                detail: $light['sensor_registered']
                    ? 'El sensor existe, pero aun faltan etiquetas de consumo o remanente para entrenar el modelo.'
                    : 'No hay un sensor de luz registrado; la card solo permite explorar un escenario manual.',
                horizon: 'Siguiente evento de alimentacion',
                inputs: ['Luz subacuatica', 'Fotoperiodo', 'Hora', 'OD', 'Temperatura', 'Racion', 'Respuesta observada'],
                model: [
                    'status' => $light['sensor_registered'] ? 'collecting_data' : 'sin_sensor',
                    'chart' => $light['chart'],
                    'current_value' => $light['latest_value'],
                    'unit' => $light['unit'],
                    'data_timestamp' => $light['latest_at'],
                ],
                policy: 'Probabilidad calibrada de baja respuesta',
                missingInputs: $light['missing_inputs'],
            ),
        ];
    }

    private function card(
        string $code,
        string $alarmCode,
        string $name,
        string $purpose,
        string $maturity,
        string $detail,
        string $horizon,
        array $inputs,
        array $model,
        string $policy,
        array $missingInputs = [],
    ): array {
        return [
            'code' => $code,
            'alarm_code' => $alarmCode,
            'name' => $name,
            'purpose' => $purpose,
            'maturity' => $maturity,
            'can_emit' => false,
            'alarm_state' => 'not_emitted',
            'status_detail' => $detail,
            'horizon' => $horizon,
            'inputs' => $inputs,
            'missing_inputs' => $missingInputs,
            'current_value' => $model['current_value'] ?? null,
            'unit' => $model['unit'] ?? null,
            'data_timestamp' => $model['data_timestamp'] ?? $model['issued_at'] ?? null,
            'projection' => [
                'available' => ! empty($model['chart']['series'] ?? []),
                'chart' => $model['chart'] ?? $this->lineChart('Sin proyeccion disponible', '', []),
            ],
            'model_status' => $model['status'] ?? 'sin_datos',
            'asset_id' => $model['asset_id'] ?? null,
            'version' => $model['version'] ?? $model['asset_version'] ?? null,
            'metrics' => $model['metrics'] ?? [],
            'usage' => $model['usage'] ?? null,
            'policy' => [
                'status' => 'draft',
                'condition' => $policy,
                'persistence' => 'Pendiente de validacion',
                'severity_mapping' => 'Pendiente de validacion',
            ],
        ];
    }

    private function lightContext(array $sensors, array $measurements): array
    {
        $lightSensors = array_values(array_filter($sensors, fn (array $sensor) => in_array(
            Str::lower((string) ($sensor['variable_code'] ?? '')),
            self::LIGHT_VARIABLES,
            true,
        )));
        $lightRows = array_values(array_filter($measurements, fn (array $row) => in_array(
            Str::lower((string) ($row['variable_code'] ?? '')),
            self::LIGHT_VARIABLES,
            true,
        )));
        usort($lightRows, fn (array $left, array $right) => strcmp((string) ($left['time'] ?? ''), (string) ($right['time'] ?? '')));
        $lightRows = array_slice($lightRows, -240);
        $latest = $lightRows === [] ? null : $lightRows[array_key_last($lightRows)];
        $unit = $latest['standard_unit'] ?? 'lux';

        $series = $lightRows === [] ? [] : [[
            'name' => 'Luz observada',
            'type' => 'line',
            'showSymbol' => false,
            'smooth' => false,
            'lineStyle' => ['width' => 2, 'color' => '#0b7cff'],
            'itemStyle' => ['color' => '#0b7cff'],
            'data' => array_map(fn (array $row) => [
                $row['time'] ?? null,
                isset($row['clean_value']) ? (float) $row['clean_value'] : null,
            ], $lightRows),
        ]];

        $missing = [];
        if ($lightSensors === []) {
            $missing[] = 'sensor de luz subacuatica registrado';
        }
        if ($lightRows === []) {
            $missing[] = 'serie temporal de luz validada';
        }
        $missing[] = 'eventos de alimentacion con cantidad ofrecida';
        $missing[] = 'etiqueta de respuesta o alimento remanente';

        return [
            'model_code' => self::LIGHT_MODEL_CODE,
            'sensor_registered' => $lightSensors !== [],
            'sensor_count' => count($lightSensors),
            'observation_count' => count($lightRows),
            'latest_value' => isset($latest['clean_value']) ? (float) $latest['clean_value'] : null,
            'latest_at' => $latest['time'] ?? null,
            'unit' => $unit,
            'quality_flag' => $latest['quality_flag'] ?? null,
            'maturity' => $lightSensors === [] ? 'blocked_inputs' : 'collecting_data',
            'can_emit' => false,
            'missing_inputs' => $missing,
            'chart' => $this->lineChart('Luz subacuatica observada', (string) $unit, $series),
            'projection' => [
                'available' => false,
                'message' => 'La proyeccion real se habilitara cuando exista una serie suficiente y un modelo validado.',
            ],
            'alarm' => [
                'code' => 'MODEL_LIGHT_FEED_RESPONSE_RISK',
                'status' => 'not_emitted',
                'message' => 'Sin artefacto activo no se emite una alarma de luz.',
            ],
        ];
    }

    private function legacyModelObservations(array $alerts): array
    {
        $deduplicated = [];

        foreach ($alerts as $alert) {
            $risk = (array) ($alert['evidence']['risk'] ?? []);
            $source = (string) ($risk['source'] ?? '');
            if (! in_array($source, self::MODEL_CODES, true)) {
                continue;
            }

            $key = implode(':', [
                $alert['pond_id'] ?? 'pond',
                $alert['alert_code'] ?? 'alert',
                $source,
            ]);
            $candidate = [
                'id' => $alert['id'] ?? $key,
                'alarm_code' => $alert['alert_code'] ?? null,
                'model_code' => $source,
                'severity' => $alert['severity'] ?? 'warning',
                'message' => $alert['message'] ?? 'Observacion de modelo',
                'occurred_at' => $alert['created_at'] ?? null,
                'productive' => false,
                'source_contract' => 'legacy_snapshot',
                'evidence' => $alert['evidence'] ?? [],
            ];

            if (! isset($deduplicated[$key]) || strcmp((string) $candidate['occurred_at'], (string) $deduplicated[$key]['occurred_at']) > 0) {
                $deduplicated[$key] = $candidate;
            }
        }

        return array_values($deduplicated);
    }

    private function unavailableDashboard(string $pondId, int $windowHours): array
    {
        $light = $this->lightContext([], []);

        return [
            'schema_version' => '1.0-compat',
            'generated_at' => now()->toIso8601String(),
            'pond_id' => $pondId,
            'summary' => [
                'active_events' => 0,
                'can_emit' => 0,
                'shadow' => 0,
                'blocked' => 4,
                'technical_observations' => 0,
            ],
            'models' => $this->modelCards([], $light),
            'events' => [],
            'technical_observations' => [],
            'light' => $light,
            'meta' => [
                'source' => 'unavailable',
                'stale' => false,
                'degraded' => true,
                'message' => 'FastAPI no respondio y todavia no existe un resultado anterior en cache.',
                'window_hours' => $windowHours,
            ],
        ];
    }

    private function lineChart(string $title, string $unit, array $series): array
    {
        return [
            'animation' => false,
            'title' => ['text' => $title, 'left' => 0, 'textStyle' => ['fontSize' => 13, 'fontWeight' => 600]],
            'tooltip' => ['trigger' => 'axis'],
            'legend' => ['top' => 28, 'left' => 0],
            'grid' => ['left' => 48, 'right' => 22, 'top' => 68, 'bottom' => 52],
            'xAxis' => ['type' => 'time', 'boundaryGap' => false],
            'yAxis' => ['type' => 'value', 'name' => $unit, 'scale' => true],
            'dataZoom' => [
                ['type' => 'inside'],
                ['type' => 'slider', 'height' => 18, 'bottom' => 8],
            ],
            'series' => $series,
        ];
    }

    private function responseJson(mixed $response): array
    {
        if (! $response instanceof Response || ! $response->successful()) {
            return [];
        }

        $json = $response->json();

        return is_array($json) ? $json : [];
    }

    private function responseSuccessful(mixed $response): bool
    {
        return $response instanceof Response && $response->successful();
    }

    private function listPayload(array $payload, array $keys): array
    {
        if (array_is_list($payload)) {
            return array_values(array_filter($payload, 'is_array'));
        }

        foreach ($keys as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                return array_values(array_filter($payload[$key], 'is_array'));
            }
        }

        return [];
    }

    private function resolvePondId(string $pondId): string
    {
        $pondId = trim($pondId);
        if ($pondId === '' || $pondId === 'T') {
            return 'LEGACY-POND-1';
        }
        if (ctype_digit($pondId)) {
            return "LEGACY-POND-{$pondId}";
        }

        return $pondId;
    }

    private function backendUrl(): string
    {
        return rtrim((string) config('services.aquaculture_backend.url'), '/');
    }

    private function connectTimeout(): int
    {
        return max(1, (int) config('services.aquaculture_backend.connect_timeout', 2));
    }

    private function timeout(): int
    {
        return max(3, (int) config('services.aquaculture_backend.timeout', 18));
    }

    private function cacheSeconds(): int
    {
        return max(15, (int) config('services.aquaculture_backend.cache_seconds', 60));
    }

    private function staleSeconds(): int
    {
        return max($this->cacheSeconds(), (int) config('services.aquaculture_backend.stale_seconds', 1800));
    }
}
