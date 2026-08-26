<?php

namespace App\Console\Commands;

use App\Models\ModelAlertPolicy;
use App\Models\Piscina;
use App\Models\User;
use Illuminate\Console\Command;

class ApproveModelAlertPolicy extends Command
{
    private const MODELS = [
        'WATER_QUALITY_INDEX_ICA',
        'SVM_OD_FORECAST_1H',
        'TILAPIA_GROWTH_TEMPERATURE',
        'LIGHT_FEED_RESPONSE_CLASSIFIER_V1',
    ];

    private const OPERATORS = ['lt', 'lte', 'gt', 'gte'];

    private const SEVERITIES = ['advertencia', 'critico', 'emergencia'];

    protected $signature = 'model-alerts:approve-policy
        {code : Codigo estable de la politica, por ejemplo OD-SVM-LOW-1H}
        {--model= : Codigo del modelo autorizado}
        {--piscina= : ID de piscina; omita para una politica global}
        {--operator= : lt, lte, gt o gte}
        {--threshold= : Valor de corte aprobado}
        {--unit= : Unidad del valor de corte}
        {--severity=advertencia : advertencia, critico o emergencia}
        {--reason= : Justificacion tecnica aprobada}
        {--policy-version=1 : Version entera de la politica}
        {--approved-by= : ID del usuario que aprobo la politica}';

    protected $description = 'Aprueba explicitamente una politica que permite a un modelo local crear alarmas';

    public function handle(): int
    {
        $model = (string) $this->option('model');
        $operator = (string) $this->option('operator');
        $threshold = $this->option('threshold');
        $severity = (string) $this->option('severity');
        $reason = trim((string) $this->option('reason'));
        $piscinaId = $this->option('piscina');
        $approverId = $this->option('approved-by');

        if (! in_array($model, self::MODELS, true)) {
            $this->error('Debe indicar un modelo permitido con --model='.implode('|', self::MODELS));

            return self::INVALID;
        }
        if (! in_array($operator, self::OPERATORS, true)) {
            $this->error('El operador debe ser lt, lte, gt o gte.');

            return self::INVALID;
        }
        if (! is_numeric($threshold) || ! is_finite((float) $threshold)) {
            $this->error('Debe indicar un valor numerico finito con --threshold.');

            return self::INVALID;
        }
        if (! in_array($severity, self::SEVERITIES, true)) {
            $this->error('La severidad debe ser advertencia, critico o emergencia.');

            return self::INVALID;
        }
        if ($reason === '') {
            $this->error('La aprobacion exige una justificacion con --reason.');

            return self::INVALID;
        }

        $piscina = null;
        if ($piscinaId !== null && $piscinaId !== '') {
            $piscina = Piscina::query()->find($piscinaId);
            if (! $piscina) {
                $this->error("No existe la piscina {$piscinaId}.");

                return self::INVALID;
            }
        }

        if ($approverId !== null && $approverId !== '' && ! User::query()->find($approverId)) {
            $this->error("No existe el usuario aprobador {$approverId}.");

            return self::INVALID;
        }

        $policy = ModelAlertPolicy::query()->updateOrCreate(
            ['code' => (string) $this->argument('code')],
            [
                'model_code' => $model,
                'piscina_id' => $piscina?->id,
                'status' => 'approved',
                'operator' => $operator,
                'threshold' => (float) $threshold,
                'unit' => $this->option('unit') ?: null,
                'severity' => $severity,
                'version' => max(1, (int) $this->option('policy-version')),
                'rationale' => $reason,
                'approved_by' => $approverId ?: null,
                'approved_at' => now(),
            ],
        );

        $scope = $policy->piscina_id ? "piscina {$policy->piscina_id}" : 'todas las piscinas';
        $this->info("Politica {$policy->code} aprobada para {$scope}. FastAPI la leera en su siguiente calculo local.");

        return self::SUCCESS;
    }
}
