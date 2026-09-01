<?php

namespace App\Services;

use App\Events\AlarmaModeloGenerada;
use App\Models\Alarma;
use App\Models\AlarmaModeloEvidencia;
use App\Models\Piscina;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class ModelAlarmPersistenceService
{
    private const MODEL_CODES = [
        'WATER_QUALITY_INDEX_ICA',
        'WATER_QUALITY_INDEX_ICA_SVM',
        'TILAPIA_GROWTH_TEMPERATURE',
        'SVM_OD_FORECAST_1H',
        'OXYGEN_STATUS_MODEL',
        'LIGHT_FEED_RESPONSE_CLASSIFIER_V1',
        'PHOTOPERIOD_GREENHOUSE_V1',
    ];

    private const PARAMETER_BY_MODEL = [
        'WATER_QUALITY_INDEX_ICA' => 'indice_calidad_agua',
        'WATER_QUALITY_INDEX_ICA_SVM' => 'indice_calidad_agua',
        'TILAPIA_GROWTH_TEMPERATURE' => 'crecimiento',
        'SVM_OD_FORECAST_1H' => 'oxigeno_disuelto',
        'OXYGEN_STATUS_MODEL' => 'oxigeno_disuelto',
        'LIGHT_FEED_RESPONSE_CLASSIFIER_V1' => 'luz_subacuatica',
        'PHOTOPERIOD_GREENHOUSE_V1' => 'fotoperiodo',
    ];

    public function available(): bool
    {
        return Schema::hasTable('alarmas')
            && Schema::hasColumns('alarmas', [
                'id',
                'piscigranja_id',
                'piscina_id',
                'modulo',
                'parametro',
                'nivel',
                'valor_detectado',
                'titulo',
                'mensaje',
                'estado',
            ])
            && Schema::hasTable('alarma_modelo_evidencias')
            && Schema::hasColumns('alarma_modelo_evidencias', [
                'alarma_id',
                'source_event_id',
                'model_code',
                'evidence',
            ]);
    }

    public function synchronize(array $events, string $requestedPondId): array
    {
        $result = [
            'available' => $this->available(),
            'received' => count($events),
            'created' => 0,
            'duplicates' => 0,
            'skipped' => 0,
            'skip_reasons' => [],
        ];

        if (! $result['available']) {
            $result['skip_reasons']['schema_unavailable'] = count($events);

            return $result;
        }

        foreach ($events as $event) {
            if (! is_array($event)) {
                $this->skip($result, 'invalid_event');
                continue;
            }
            if (($event['productive'] ?? false) !== true) {
                $this->skip($result, 'not_productive');
                continue;
            }
            $eventType = Str::lower((string) ($event['event_type'] ?? 'triggered'));
            if (! in_array($eventType, ['triggered', 'active', 'alarm'], true)) {
                $this->skip($result, 'not_triggered');
                continue;
            }
            $level = $this->level($event['suggested_severity'] ?? $event['severity'] ?? null);
            if ($level === 'normal') {
                $this->skip($result, 'normal_state');
                continue;
            }

            $model = is_array($event['model'] ?? null) ? $event['model'] : [];
            $modelCode = (string) ($model['code'] ?? $event['model_code'] ?? '');
            if (! in_array($modelCode, self::MODEL_CODES, true)) {
                $this->skip($result, 'model_not_allowed');
                continue;
            }

            $piscina = $this->resolvePiscina($event, $requestedPondId);
            if (! $piscina) {
                $this->skip($result, 'piscina_not_resolved');
                continue;
            }

            $sourceEventId = $this->sourceEventId($event, $modelCode, $piscina->id);
            if (AlarmaModeloEvidencia::query()->where('source_event_id', $sourceEventId)->exists()) {
                $result['duplicates']++;
                continue;
            }

            try {
                $alarma = DB::transaction(function () use ($event, $model, $modelCode, $piscina, $sourceEventId, $level) {
                    $value = $this->numericValue(
                        $event['predicted_value']
                        ?? $event['value']
                        ?? data_get($event, 'evidence.predicted_value')
                        ?? data_get($event, 'evidence.value'),
                    );

                    $alarma = Alarma::create([
                        'piscigranja_id' => $piscina->piscigranja_id,
                        'piscina_id' => $piscina->id,
                        'modulo' => 'inteligencia',
                        'parametro' => self::PARAMETER_BY_MODEL[$modelCode],
                        'nivel' => $level,
                        'valor_detectado' => $value,
                        'titulo' => Str::limit((string) ($event['title'] ?? $event['alarm_code'] ?? 'Alarma de modelo'), 255, ''),
                        'mensaje' => (string) ($event['message'] ?? $event['detail'] ?? ''),
                        'estado' => 'activa',
                    ]);

                    AlarmaModeloEvidencia::create([
                        'alarma_id' => $alarma->id,
                        'source_event_id' => $sourceEventId,
                        'model_code' => $modelCode,
                        'model_version' => $model['version'] ?? $event['model_version'] ?? null,
                        'asset_id' => $model['asset_id'] ?? $event['asset_id'] ?? null,
                        'policy_code' => data_get($event, 'policy.code') ?? $event['policy_code'] ?? null,
                        'horizon_minutes' => $this->positiveInteger($event['horizon_minutes'] ?? data_get($event, 'evidence.horizon_minutes')),
                        'prediction_for' => $event['prediction_for'] ?? data_get($event, 'evidence.prediction_for'),
                        'predicted_value' => $value,
                        'evidence' => $event,
                    ]);

                    return $alarma;
                });

                $result['created']++;

                try {
                    event(new AlarmaModeloGenerada($alarma));
                } catch (Throwable) {
                    // Persistence remains authoritative if realtime broadcasting is temporarily unavailable.
                }
            } catch (Throwable) {
                if (AlarmaModeloEvidencia::query()->where('source_event_id', $sourceEventId)->exists()) {
                    $result['duplicates']++;
                } else {
                    $this->skip($result, 'persistence_error');
                }
            }
        }

        return $result;
    }

    public function recent(string $requestedPondId, int $limit = 100): array
    {
        if (! $this->available()) {
            return [];
        }

        $query = Alarma::query()
            ->with('evidenciaModelo')
            ->where('modulo', 'inteligencia')
            ->whereHas('evidenciaModelo')
            ->latest('created_at');

        $piscina = $this->resolvePiscina([], $requestedPondId);
        if ($piscina) {
            $query->where('piscina_id', $piscina->id);
        }

        return $query->limit(max(1, min(200, $limit)))
            ->get()
            ->map(function (Alarma $alarma) {
                $evidence = $alarma->evidenciaModelo;

                return [
                    'id' => $alarma->id,
                    'source_event_id' => $evidence?->source_event_id,
                    'pond_id' => $alarma->piscina_id,
                    'piscigranja_id' => $alarma->piscigranja_id,
                    'alarm_code' => data_get($evidence?->evidence, 'alarm_code'),
                    'title' => $alarma->titulo,
                    'message' => $alarma->mensaje,
                    'suggested_severity' => $alarma->nivel,
                    'event_type' => $alarma->estado,
                    'productive' => true,
                    'value' => $alarma->valor_detectado,
                    'occurred_at' => $alarma->created_at?->toIso8601String(),
                    'recognized_at' => $alarma->reconocida_en?->toIso8601String(),
                    'resolved_at' => $alarma->resuelta_en?->toIso8601String(),
                    'model' => [
                        'code' => $evidence?->model_code,
                        'version' => $evidence?->model_version,
                        'asset_id' => $evidence?->asset_id,
                    ],
                    'policy' => ['code' => $evidence?->policy_code],
                    'horizon_minutes' => $evidence?->horizon_minutes,
                    'prediction_for' => $evidence?->prediction_for?->toIso8601String(),
                    'evidence' => $evidence?->evidence ?? [],
                ];
            })
            ->all();
    }

    private function resolvePiscina(array $event, string $requestedPondId): ?Piscina
    {
        $candidates = [
            $event['piscina_id'] ?? null,
            $event['pond_id'] ?? null,
            $requestedPondId,
        ];

        foreach ($candidates as $candidate) {
            if ($candidate === null || $candidate === '' || $candidate === 'T') {
                continue;
            }

            $text = (string) $candidate;
            $id = ctype_digit($text) ? (int) $text : null;
            if ($id === null && preg_match('/^LEGACY-POND-(\d+)$/', $text, $matches)) {
                $id = (int) $matches[1];
            }
            if ($id && ($piscina = Piscina::query()->find($id))) {
                return $piscina;
            }
        }

        return null;
    }

    private function sourceEventId(array $event, string $modelCode, int $piscinaId): string
    {
        $source = (string) ($event['source_event_id'] ?? $event['id'] ?? '');
        if ($source === '') {
            $source = hash('sha256', json_encode([
                'model' => $modelCode,
                'piscina' => $piscinaId,
                'alarm' => $event['alarm_code'] ?? null,
                'prediction_for' => $event['prediction_for'] ?? null,
                'value' => $event['predicted_value'] ?? $event['value'] ?? null,
            ], JSON_THROW_ON_ERROR));
        }

        return strlen($source) <= 191 ? $source : hash('sha256', $source);
    }

    private function level(mixed $severity): string
    {
        return match (Str::lower((string) $severity)) {
            'normal', 'info', 'informativo' => 'normal',
            'critical', 'critico', 'critical_high' => 'critico',
            'emergency', 'emergencia' => 'emergencia',
            default => 'advertencia',
        };
    }

    private function numericValue(mixed $value): ?float
    {
        if (! is_numeric($value) || ! is_finite((float) $value)) {
            return null;
        }

        return max(-9999999.999, min(9999999.999, (float) $value));
    }

    private function positiveInteger(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value >= 0 ? (int) $value : null;
    }

    private function skip(array &$result, string $reason): void
    {
        $result['skipped']++;
        $result['skip_reasons'][$reason] = ($result['skip_reasons'][$reason] ?? 0) + 1;
    }
}
