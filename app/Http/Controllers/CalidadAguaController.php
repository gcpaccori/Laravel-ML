<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use App\Models\CalidadAgua;
use App\Models\Piscigranja;
use Illuminate\Http\Request;
use App\Models\ParametroAgua;
use App\Models\ParametroBanda;
use Illuminate\Support\Facades\DB;

class CalidadAguaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $bands = [
            "bandsTemperatura" => [
                "bands" => ParametroBanda::where('parametro', 'temperatura')->get()
                    ->map(fn($item) => [
                        "title"     => $item->title,
                        "color"     => $item->color,
                        "lowScore"  => $item->low_score,
                        "highScore" => $item->high_score,
                    ]),
                "min" => 0,
                "max" => 40,
            ],
            "bandsPh" => [
                "bands" => ParametroBanda::where('parametro', 'ph')->get()
                    ->map(fn($item) => [
                        "title"     => $item->title,
                        "color"     => $item->color,
                        "lowScore"  => $item->low_score,
                        "highScore" => $item->high_score,
                    ]),
                "min" => 0,
                "max" => 14,
            ],
            "bandsOxigeno" => [
                "bands" => ParametroBanda::where('parametro', 'oxigeno')->get()
                    ->map(fn($item) => [
                        "title"     => $item->title,
                        "color"     => $item->color,
                        "lowScore"  => $item->low_score,
                        "highScore" => $item->high_score,
                    ]),
                "min" => 0,
                "max" => 15,
            ],
            "bandsNitrato" => [
                "bands" => ParametroBanda::where('parametro', 'nitrato')->get()
                    ->map(fn($item) => [
                        "title"     => $item->title,
                        "color"     => $item->color,
                        "lowScore"  => $item->low_score,
                        "highScore" => $item->high_score,
                    ]),
                "min" => 0,
                "max" => 2,
            ],
        ];

        return Inertia::render('Modules/Views/CalidadAgua', [
            'title' => 'Monitoreo de Calidad del Agua',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ],
            "bands" => $bands
        ]);
    }

    public function getDataParametros(Request $request)
    {
        // ======================
        // 1) Obtener piscigranjas activas con sus piscinas
        // ======================
        $piscigranjasQuery = Piscigranja::where('activo', true);

        if ($request->has('piscigranja_id') && $request->piscigranja_id !== 'T') {
            $piscigranjasQuery->where('id', $request->piscigranja_id);
        }

        $piscigranjas = $piscigranjasQuery->get();

        // ======================
        // 2) Obtener el último parámetro global filtrado
        // ======================
        $parametroQuery = ParametroAgua::with(['piscina.piscigranja'])
            ->latest('fecha_medicion');

        // Filtro por piscigranja
        if ($request->has('piscigranja_id') && $request->piscigranja_id !== 'T') {
            $parametroQuery->whereHas('piscina.piscigranja', function($q) use ($request) {
                $q->where('id', $request->piscigranja_id)
                ->where('activo', true);
            });
        } else {
            // si no hay filtro de piscigranja, aseguramos solo activas
            $parametroQuery->whereHas('piscina.piscigranja', function($q) {
                $q->where('activo', true);
            });
        }

        // Filtro por piscina
        if ($request->has('piscina_id') && $request->piscina_id !== 'T') {
            $parametroQuery->where('piscina_id', $request->piscina_id);
        }

        $ultimo = $parametroQuery->first();

        // ======================
        // 3) Respuesta JSON
        // ======================
        return response()->json([
            'piscigranjas' => $piscigranjas,
            'parametros' => [
                'temperatura'    => $ultimo?->temperatura ?? 0,
                'ph'             => $ultimo?->ph ?? 0,
                'oxigeno'        => $ultimo?->oxigeno_disuelto ?? 0,
                'nitrato'        => $ultimo?->ion_nitrato ?? 0,
                'fecha_medicion' => $ultimo?->fecha_medicion?->format('d/m/Y H:i:s'),
                'fecha_registro' => $ultimo?->created_at?->format('d/m/Y H:i:s'),
                'piscina' => $ultimo?->piscina ? [
                    'id'            => $ultimo->piscina->id,
                    'nombre'        => $ultimo->piscina->nombre,
                    'estado'        => $ultimo->piscina->estado ?? null,
                ] : null,
                'piscigranja' => $ultimo?->piscina?->piscigranja ? [
                    'id'            => $ultimo->piscina->piscigranja->id,
                    'nombre'        => $ultimo->piscina->piscigranja->nombre,
                    'activo'        => $ultimo->piscina->piscigranja->activo,
                ] : null,
            ]
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(CalidadAgua $calidadAgua)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CalidadAgua $calidadAgua)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CalidadAgua $calidadAgua)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CalidadAgua $calidadAgua)
    {
        //
    }
}
