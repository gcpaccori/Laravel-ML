<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\CalidadAgua;
use App\Models\Piscigranja;
use Illuminate\Http\Request;
use App\Models\ParametroAgua;

class CalidadAguaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        return Inertia::render('Modules/Views/CalidadAgua', [
            'title' => 'Monitoreo de Calidad del Agua',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ]
        ]);
    }

    public function getDataParametros( Request $request )
    {
        $parametros = Piscigranja::with(['piscinas' => function ($q) use ($request) {
                if ($request->has('piscina_id') && $request->piscina_id !== 'T') {
                    $q->where('id', $request->piscina_id);
                }
            }, 'piscinas.parametrosAguas' => function ($q) {
                // ordenamos por fecha para que el último quede primero
                $q->latest('created_at');
            }])
            ->where('activo', true);

        if ($request->has('piscigranja_id') && $request->piscigranja_id !== 'T') {
            $parametros->where('id', $request->piscigranja_id);
        }

        $piscigranjas = $parametros->get();

        // Buscar el último registro de parámetros de agua
        $ultimo = null;
        $piscinaDelUltimo = null;
        $piscigranjaDelUltimo = null;

        foreach ($piscigranjas as $pg) {
            foreach ($pg->piscinas as $piscina) {
                if ($piscina->parametrosAguas->isNotEmpty()) {
                    $registro = $piscina->parametrosAguas->first(); // ya está ordenado latest()
                    if (!$ultimo || $registro->created_at > $ultimo->created_at) {
                        $ultimo = $registro;
                        $piscinaDelUltimo = $piscina;
                        $piscigranjaDelUltimo = $pg;
                    }
                }
            }
        }

        return response()->json([
            'piscigranjas' => $piscigranjas,
            'parametros' => [
                'temperatura' => $ultimo?->temperatura ?? 0,
                'ph' => $ultimo?->ph ?? 0,
                'oxigeno' => $ultimo?->oxigeno_disuelto ?? 0,
                'nitrato' => $ultimo?->ion_nitrato ?? 0,
                'fecha_medicion' => $ultimo?->fecha_medicion?->format('d/m/Y H:i:s'),
                'fecha_registro' => $ultimo?->created_at?->format('d/m/Y H:i:s'),
                'piscina_id' => $ultimo?->piscina_id,
                'piscina' => [
                    'id' => $piscinaDelUltimo?->id,
                    'piscigranja_id' => $piscinaDelUltimo?->piscigranja_id,
                    'nombre' => $piscinaDelUltimo?->nombre,
                    'estado' => $piscinaDelUltimo?->estado ?? null,
                ],
                'piscigranja' => [
                    'id' => $piscigranjaDelUltimo?->id,
                    'nombre' => $piscigranjaDelUltimo?->nombre,
                    'activo' => $piscigranjaDelUltimo?->activo ?? null,
                ]
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
