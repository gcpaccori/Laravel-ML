<?php

namespace App\Http\Controllers;

use App\Models\Alarma;
use App\Services\ModelAlertDashboardService;
use App\Services\ModelAlarmPersistenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AlarmaModeloController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Modules/Views/AlarmasModelos', [
            'title' => 'Alarmas de modelos',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Modelos de aprendizaje automatico', 'route' => 'monitoreo.modelosmls.index'],
                ['label' => 'Alarmas de modelos'],
            ],
        ]);
    }

    public function dashboard(
        Request $request,
        ModelAlertDashboardService $service,
        ModelAlarmPersistenceService $alarms,
    ): JsonResponse
    {
        $validated = $request->validate([
            'piscina_id' => ['nullable', 'string', 'max:128'],
            'ventana_horas' => ['nullable', 'integer', 'between:6,2160'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        $dashboard = $service->dashboard(
            pondId: (string) ($validated['piscina_id'] ?? 'T'),
            windowHours: (int) ($validated['ventana_horas'] ?? 24),
            refresh: (bool) ($validated['refresh'] ?? false),
        );
        $resolvedPondId = (string) ($dashboard['pond_id'] ?? $validated['piscina_id'] ?? 'T');
        $sync = $alarms->synchronize((array) ($dashboard['events'] ?? []), $resolvedPondId);

        if ($sync['available']) {
            $dashboard['events'] = $alarms->recent($resolvedPondId);
            $dashboard['summary']['active_events'] = collect($dashboard['events'])
                ->where('event_type', 'activa')
                ->count();
        }
        $dashboard['meta']['alarm_storage'] = $sync;

        return response()->json($dashboard);
    }

    // Alarmas de modelo para el desplegable de la barra.
    //
    // Se separan por vigencia, que no es lo mismo que por estado. Una alarma
    // que predijo algo para las 14:00 deja de ser actualidad a las 14:01,
    // aunque nadie la haya cerrado: su ventana ya paso y lo unico que queda
    // por hacer con ella es comprobar si acerto. Por eso hay tres estados:
    //
    //   vigente   sigue abierta y su plazo no ha vencido
    //   vencida   sigue abierta pero el momento que predijo ya paso
    //   resuelta  alguien la cerro
    //
    // Se guardan las tres. Las dos ultimas son el material con el que despues
    // se mide si el modelo acerto de verdad.
    public function pendientes(): JsonResponse
    {
        try {
            $filas = Alarma::query()
                ->with('evidenciaModelo')
                ->where('modulo', 'inteligencia')
                ->whereHas('evidenciaModelo')
                ->latest('created_at')
                ->limit(40)
                ->get()
                ->map(function (Alarma $a) {
                    $ev = $a->evidenciaModelo;
                    $para = $ev?->prediction_for ? \Carbon\Carbon::parse($ev->prediction_for) : null;
                    $vencida = $para !== null && $para->isPast();

                    if ($a->estado !== 'activa') {
                        $vigencia = 'resuelta';
                    } elseif ($vencida) {
                        $vigencia = 'vencida';
                    } else {
                        $vigencia = 'vigente';
                    }

                    return [
                        'id' => $a->id,
                        'titulo' => $a->titulo,
                        'mensaje' => $a->mensaje,
                        'nivel' => $a->nivel,
                        'valor' => $a->valor_detectado,
                        'ocurrio_en' => $a->created_at?->toIso8601String(),
                        'resuelta_en' => $a->resuelta_en?->toIso8601String(),
                        'vigencia' => $vigencia,
                        'model_code' => $ev?->model_code,
                        'predijo' => $ev?->predicted_value,
                        'para' => $para?->toIso8601String(),
                    ];
                })
                ->values();
        } catch (\Throwable) {
            $filas = collect();
        }

        return response()->json([
            'pendientes' => $filas->where('vigencia', 'vigente')->count(),
            'alarmas' => $filas,
        ]);
    }
    public function lightScenario(Request $request, ModelAlertDashboardService $service): JsonResponse
    {
        $validated = $request->validate([
            'maximum_lux' => ['required', 'numeric', 'between:0,200000'],
            'current_lux' => ['nullable', 'numeric', 'between:0,200000'],
            'photoperiod_hours' => ['required', 'numeric', 'between:0,24'],
            'dawn_hour' => ['required', 'numeric', 'between:0,23.99'],
            'horizon_hours' => ['required', 'integer', 'between:1,72'],
        ]);

        return response()->json($service->lightScenario($validated));
    }

    public function lightStatus(Request $request, ModelAlertDashboardService $service): JsonResponse
    {
        $validated = $request->validate([
            'piscina_id' => ['nullable', 'string', 'max:128'],
            'refresh' => ['nullable', 'boolean'],
        ]);

        return response()->json($service->lightStatus(
            pondId: (string) ($validated['piscina_id'] ?? 'T'),
            refresh: (bool) ($validated['refresh'] ?? false),
        ));
    }
}
