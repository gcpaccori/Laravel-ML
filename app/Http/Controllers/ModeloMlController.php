<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

class ModeloMlController extends Controller
{
    public function index()
    {
        return Inertia::render('Modules/Views/ModelosMl', [
            'title' => 'Modelos de aprendizaje automatico',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Modelos de aprendizaje automatico'],
            ],
        ]);
    }

    public function proyecciones(Request $request)
    {
        $baseUrl = $this->backendUrl();
        $pondId = $this->pondId($request);
        $windowHours = [
            '6h' => 6,
            '24h' => 24,
            '7d' => 168,
            '30d' => 720,
            '90d' => 2160,
        ][$request->input('ventana', '7d')] ?? 168;
        $growthProjectionDays = max(1, min(365, (int) $request->input('proyeccion_dias', 7)));
        $cacheKey = "modelos-ml:dashboard:{$pondId}:{$windowHours}:{$growthProjectionDays}";
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return response()->json($cached);
        }

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(25)
                ->get("{$baseUrl}/ponds/{$pondId}/ai/dashboard", [
                    'window_hours' => $windowHours,
                    'growth_projection_days' => $growthProjectionDays,
                ]);
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'Los datos de modelos siguen preparandose. Intenta actualizar en unos segundos.',
                'backend_url' => $baseUrl,
            ], 503);
        }

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo consultar el backend FastAPI local de modelos.',
                'backend_url' => $baseUrl,
                'backend_status' => $response->status(),
                'detail' => $response->body(),
            ], 502);
        }

        $payload = $response->json();
        Cache::put($cacheKey, $payload, now()->addSeconds(90));

        return response()->json($payload);
    }

    public function simulacion(Request $request)
    {
        $baseUrl = $this->backendUrl();
        $pondId = $this->pondId($request);
        $payload = $request->validate([
            'temperature_c' => ['nullable', 'numeric', 'between:0,45'],
            'ph' => ['nullable', 'numeric', 'between:0,14'],
            'dissolved_oxygen_mg_l' => ['nullable', 'numeric', 'between:0,30'],
            'nitrate_ion' => ['nullable', 'numeric', 'between:0,500'],
            'projection_days' => ['nullable', 'integer', 'between:1,365'],
            'active_models' => ['nullable', 'array'],
            'active_models.*' => ['string'],
        ]);

        try {
            $response = Http::acceptJson()
                ->connectTimeout(3)
                ->timeout(20)
                ->post("{$baseUrl}/ponds/{$pondId}/digital-twin/simulate", $payload);
        } catch (\Throwable $exception) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo calcular el escenario en este momento.',
            ], 503);
        }

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'El simulador local no pudo procesar el escenario.',
                'detail' => $response->json('detail'),
            ], 502);
        }

        return response()->json($response->json());
    }

    public function suite()
    {
        $url = env('MODELOS_ML_SUITE_URL', 'http://37.60.226.53:3031');

        return Inertia::location($url);
    }

    private function backendUrl(): string
    {
        return rtrim(env('AQUACULTURE_BACKEND_URL', 'http://aquaculture_backend:8000/api/v1'), '/');
    }

    private function pondId(Request $request): string
    {
        $pondId = (string) $request->input('piscina_id', '1');

        return $pondId === 'T' ? '1' : $pondId;
    }
}
