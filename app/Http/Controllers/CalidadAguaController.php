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
        $config = [
            'temperatura' => [
                'label' => 'Temperatura',
                'unit'  => '°C',
            ],
            'ph' => [
                'label' => 'Grado de Acidez',
                'unit'  => 'pH',
            ],
            'oxigeno_disuelto' => [
                'label' => 'Oxígeno Disuelto',
                'unit'  => 'mg/L',
            ],
            'ion_nitrato' => [
                'label' => 'Ion de Nitrato',
                'unit'  => 'mg/L',
            ],
        ];

        $parametros = ParametroBanda::orderBy('parametro')
            ->orderBy('low_score')
            ->get()
            ->groupBy('parametro');


        $bands = [];

        foreach ($config as $parametro => $item) {

            $items = $parametros[$parametro] ?? [];

            $bands[$parametro] = [
                'label' => $item['label'],
                'unit'  => $item['unit'],

                'min' => $items->min('low_score'),
                'max' => $items->max('high_score'),

                'bands' => $items->map(fn ($band) => [
                    'title'     => $band->title,
                    'color'     => $band->color,
                    'lowScore'  => $band->low_score,
                    'highScore' => $band->high_score,
                ])->values(),
            ];
        }

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
        $piscigranjasQuery = Piscigranja::with('piscinas')->where('activo', true);

        if ($request->has('piscigranja_id') && $request->piscigranja_id !== 'T') {
            $piscigranjasQuery->where('id', $request->piscigranja_id);
        }

        if ($request->has('piscina_id') && $request->piscina_id !== 'T') {
            $piscigranjasQuery->whereHas('piscinas', function($query) use ($request) {
                $query->where('id', $request->piscina_id);
            });

            // Filtrar también el with para traer solo la piscina específica
            $piscigranjasQuery->with(['piscinas' => function($query) use ($request) {
                $query->where('id', $request->piscina_id);
            }]);
        } else {
            $piscigranjasQuery->with('piscinas');
        }

        $piscigranjas = $piscigranjasQuery->get();

        // ======================
        // 2) Obtener el último parámetro global filtrado
        // ======================
        $parametroQuery = ParametroAgua::with(['piscina.piscigranja'])
            // ->latest('fecha_medicion');
            ->latest('created_at');

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
                'oxigeno_disuelto'        => $ultimo?->oxigeno_disuelto ?? 0,
                'ion_nitrato'        => $ultimo?->ion_nitrato ?? 0,
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
