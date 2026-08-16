<?php

namespace App\Http\Controllers;

use App\Models\ParametroAmbiente;
use App\Models\ParametroBanda;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ParametroAmbienteController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Modules/Views/Fotoperiodo', [
            'title' => 'Monitoreo de Fotoperíodo',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ],
        ]);
    }

    public function getDataParametros(Request $request)
    {
        // $hoy = now()->toDateString();
        $fecha = $request->fecha;
        $query = ParametroAmbiente::with('piscina.piscigranja')->whereDate('fecha', $fecha);

        // Piscigranja: "T" = Todas
        if ($request->piscigranja_id && $request->piscigranja_id !== 'T') {
            $query->whereHas('piscina', function ($q) use ($request) {
                $q->where('piscigranja_id', $request->piscigranja_id);
            });
        }

        // Piscina: "T" = Todas
        if ($request->piscina_id && $request->piscina_id !== 'T') {
            $query->where('piscina_id', $request->piscina_id);
        }

        // Obtener el último registro de cada piscina
        $lecturas = $query
            ->orderByDesc('fecha_medicion')
            ->get()
            ->groupBy('piscina_id')
            ->map(fn ($registros) => $registros)
            ->values();

        $ultimas = $lecturas->first();

        // Bandas de los parámetros ambientales
        $bandas = ParametroBanda::whereIn('parametro', ['iluminancia', 'temperatura_ambiente', 'humedad_ambiente'])
            ->orderBy('parametro')
            ->orderBy('low_score')
            ->get()
            ->groupBy('parametro');

        return response()->json([
            'ultima'   => $ultimas?->first(),
            'lecturas' => $ultimas?->sortBy('fecha_medicion')->values(),
            'bandas'   => $bandas,
        ]);
    }
}
