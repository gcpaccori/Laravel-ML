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
        $baseUrl = rtrim(env('FLASK_MODELOS_ML_URL', 'http://flask_sismapiscis:5000'), '/');

        $response = Http::acceptJson()->timeout(120)->get("{$baseUrl}/api/modelos-ml/proyecciones", [
            'piscigranja_id' => $request->input('piscigranja_id', 'T'),
            'piscina_id' => $request->input('piscina_id', 'T'),
            'horizonte' => $request->input('horizonte', '72h'),
            'ventana' => $request->input('ventana', 'all'),
            'retrain' => $request->boolean('retrain') ? '1' : null,
        ]);

        if ($response->failed()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No se pudo consultar el backend Python de modelos.',
                'backend_url' => $baseUrl,
                'backend_status' => $response->status(),
                'detail' => $response->body(),
            ], 502);
        }

        return response()->json($response->json());
    }

    public function suite()
    {
        $url = env('MODELOS_ML_SUITE_URL', 'https://acuicola-frontend.vercel.app');

        return Inertia::location($url);
    }
}
