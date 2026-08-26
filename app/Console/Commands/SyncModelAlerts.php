<?php

namespace App\Console\Commands;

use App\Models\Piscina;
use App\Services\ModelAlarmPersistenceService;
use App\Services\ModelAlertDashboardService;
use Illuminate\Console\Command;

class SyncModelAlerts extends Command
{
    protected $signature = 'model-alerts:sync
        {--pond=* : IDs de piscina que se deben sincronizar}
        {--window=24 : Ventana de evidencia en horas}
        {--refresh : Ignorar la cache vigente del dashboard}';

    protected $description = 'Sincroniza eventos productivos de modelos con la tabla compartida de alarmas';

    public function handle(
        ModelAlertDashboardService $dashboardService,
        ModelAlarmPersistenceService $alarmService,
    ): int {
        if (! $alarmService->available()) {
            $this->error('Las tablas alarmas y alarma_modelo_evidencias deben existir antes de sincronizar.');

            return self::FAILURE;
        }

        $requestedPonds = collect($this->option('pond'))->filter()->map(fn ($id) => (string) $id);
        $ponds = $requestedPonds->isNotEmpty()
            ? $requestedPonds
            : Piscina::query()->orderBy('id')->pluck('id')->map(fn ($id) => (string) $id);
        $windowHours = max(6, min(2160, (int) $this->option('window')));
        $totals = ['created' => 0, 'duplicates' => 0, 'skipped' => 0];

        foreach ($ponds as $pondId) {
            $dashboard = $dashboardService->dashboard($pondId, $windowHours, (bool) $this->option('refresh'));
            $resolvedPondId = (string) ($dashboard['pond_id'] ?? $pondId);
            $result = $alarmService->synchronize((array) ($dashboard['events'] ?? []), $resolvedPondId);

            foreach (array_keys($totals) as $key) {
                $totals[$key] += (int) ($result[$key] ?? 0);
            }
            $this->line("Piscina {$pondId}: {$result['created']} creadas, {$result['duplicates']} repetidas, {$result['skipped']} omitidas.");
        }

        $this->info("Sincronizacion terminada: {$totals['created']} creadas, {$totals['duplicates']} repetidas, {$totals['skipped']} omitidas.");

        return self::SUCCESS;
    }
}
