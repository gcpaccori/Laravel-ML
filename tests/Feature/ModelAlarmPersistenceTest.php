<?php

use App\Events\AlarmaGenerada;
use App\Models\Piscigranja;
use App\Services\ModelAlarmPersistenceService;
use App\Services\ModelAlertDashboardService;
use Illuminate\Support\Facades\Event;

it('persists a productive model event once in the shared alarm table', function () {
    Event::fake([AlarmaGenerada::class]);
    $farm = Piscigranja::create(['nombre' => 'Granja de prueba']);
    $pond = $farm->piscinas()->create(['nombre' => 'Piscina 1']);
    $service = app(ModelAlarmPersistenceService::class);
    $event = [
        'id' => 'MODEL-EVENT-001',
        'productive' => true,
        'pond_id' => "LEGACY-POND-{$pond->id}",
        'alarm_code' => 'MODEL_OD_THRESHOLD_FORECAST',
        'title' => 'Oxigeno proyectado bajo',
        'message' => 'La proyeccion cruza el limite aprobado.',
        'suggested_severity' => 'critical',
        'predicted_value' => 4.2,
        'prediction_for' => now()->addHour()->toIso8601String(),
        'horizon_minutes' => 60,
        'model' => [
            'code' => 'SVM_OD_FORECAST_1H',
            'version' => 'v1',
            'asset_id' => 'ASSET-001',
        ],
        'policy' => ['code' => 'OD-LOW-1H'],
    ];

    $first = $service->synchronize([$event], "LEGACY-POND-{$pond->id}");
    $second = $service->synchronize([$event], "LEGACY-POND-{$pond->id}");

    expect($first['created'])->toBe(1)
        ->and($second['duplicates'])->toBe(1);
    $this->assertDatabaseHas('alarmas', [
        'piscigranja_id' => $farm->id,
        'piscina_id' => $pond->id,
        'modulo' => 'inteligencia',
        'parametro' => 'oxigeno_disuelto',
        'nivel' => 'critico',
        'estado' => 'activa',
    ]);
    $this->assertDatabaseCount('alarmas', 1);
    $this->assertDatabaseHas('alarma_modelo_evidencias', [
        'source_event_id' => 'MODEL-EVENT-001',
        'model_code' => 'SVM_OD_FORECAST_1H',
        'model_version' => 'v1',
    ]);
    Event::assertDispatchedTimes(AlarmaGenerada::class, 1);
});

it('does not persist technical observations or unknown model sources', function () {
    $farm = Piscigranja::create(['nombre' => 'Granja de prueba']);
    $pond = $farm->piscinas()->create(['nombre' => 'Piscina 1']);
    $service = app(ModelAlarmPersistenceService::class);

    $result = $service->synchronize([
        [
            'id' => 'OBS-001',
            'productive' => false,
            'pond_id' => $pond->id,
            'model' => ['code' => 'SVM_OD_FORECAST_1H'],
        ],
        [
            'id' => 'SENSOR-001',
            'productive' => true,
            'pond_id' => $pond->id,
            'model' => ['code' => 'RAW_SENSOR_THRESHOLD'],
        ],
        [
            'id' => 'CLEARED-001',
            'productive' => true,
            'event_type' => 'cleared',
            'pond_id' => $pond->id,
            'model' => ['code' => 'SVM_OD_FORECAST_1H'],
        ],
        [
            'id' => 'NORMAL-001',
            'productive' => true,
            'event_type' => 'triggered',
            'suggested_severity' => 'normal',
            'pond_id' => $pond->id,
            'model' => ['code' => 'SVM_OD_FORECAST_1H'],
        ],
    ], (string) $pond->id);

    expect($result['created'])->toBe(0)
        ->and($result['skipped'])->toBe(4)
        ->and($result['skip_reasons']['not_productive'])->toBe(1)
        ->and($result['skip_reasons']['model_not_allowed'])->toBe(1)
        ->and($result['skip_reasons']['not_triggered'])->toBe(1)
        ->and($result['skip_reasons']['normal_state'])->toBe(1);
    $this->assertDatabaseCount('alarmas', 0);
});

it('keeps a manual light scenario outside the productive alarm lifecycle', function () {
    $scenario = app(ModelAlertDashboardService::class)->lightScenario([
        'maximum_lux' => 800,
        'photoperiod_hours' => 12,
        'dawn_hour' => 6,
        'horizon_hours' => 24,
    ]);

    expect($scenario['mode'])->toBe('manual_protocol_scenario')
        ->and($scenario['alarm_preview']['can_emit'])->toBeFalse()
        ->and($scenario['alarm_preview']['status'])->toBe('not_emitted')
        ->and($scenario['points'])->toHaveCount(49);
});
