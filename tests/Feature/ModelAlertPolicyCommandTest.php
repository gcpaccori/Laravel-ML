<?php

use App\Models\Piscigranja;
use App\Models\User;

it('requires an explicit technical policy before model alarms can be enabled', function () {
    $this->artisan('model-alerts:approve-policy', [
        'code' => 'OD-SVM-LOW-1H',
        '--model' => 'SVM_OD_FORECAST_1H',
        '--operator' => 'lte',
        '--threshold' => 5,
        '--unit' => 'mg/L',
        '--severity' => 'advertencia',
    ])->assertExitCode(2);

    $farm = Piscigranja::create(['nombre' => 'Granja de prueba']);
    $pond = $farm->piscinas()->create(['nombre' => 'Piscina 1']);
    $approver = User::factory()->create();

    $this->artisan('model-alerts:approve-policy', [
        'code' => 'OD-SVM-LOW-1H',
        '--model' => 'SVM_OD_FORECAST_1H',
        '--piscina' => $pond->id,
        '--operator' => 'lte',
        '--threshold' => 5,
        '--unit' => 'mg/L',
        '--severity' => 'advertencia',
        '--reason' => 'Limite aprobado para la operacion de prueba.',
        '--approved-by' => $approver->id,
    ])->assertExitCode(0);

    $this->assertDatabaseHas('model_alert_policies', [
        'code' => 'OD-SVM-LOW-1H',
        'model_code' => 'SVM_OD_FORECAST_1H',
        'piscina_id' => $pond->id,
        'status' => 'approved',
        'operator' => 'lte',
        'threshold' => 5,
        'unit' => 'mg/L',
        'severity' => 'advertencia',
        'approved_by' => $approver->id,
    ]);
});
