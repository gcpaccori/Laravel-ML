<?php

namespace App\Http\Controllers;

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
