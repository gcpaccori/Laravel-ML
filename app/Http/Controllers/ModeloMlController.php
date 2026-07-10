<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;

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
        $baseUrl = rtrim(env('AQUACULTURE_BACKEND_URL', 'http://aquaculture_backend:8000/api/v1'), '/');
        $pondId = (string) $request->input('piscina_id', '1');
        $pondId = $pondId === 'T' ? '1' : $pondId;

        $response = Http::acceptJson()
            ->connectTimeout(5)
            ->timeout(45)
            ->get("{$baseUrl}/ponds/{$pondId}/ai/dashboard");

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo consultar el backend FastAPI local de modelos.',
                'backend_url' => $baseUrl,
                'backend_status' => $response->status(),
                'detail' => $response->body(),
            ], 502);
        }

        return response()->json($response->json());
    }

    public function suite()
    {
        $url = env('MODELOS_ML_SUITE_URL', 'http://37.60.226.53:3031');

        return Inertia::location($url);
    }
}
