<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ParametrosProduccion;
use App\Http\Requests\ParametroProduccionRequest;

class ParametrosProduccionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
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
    public function store(ParametroProduccionRequest $request)
    {
        $reg = ParametrosProduccion::create([
            "campania_etapa_id"          => $request->campania_etapa_id,
            "dias_alimentacion"          => $request->dias_alimentacion,
            "dias_muestreo"              => $request->dias_muestreo,
            "numero_muestreos"           => $request->numero_muestreos, // (dias_alimentacion / dias_muestreo)
            "cantidad_alimento_total_kg" => $request->cantidad_alimento_total_kg,
            "racion_diaria_gr"           => $request->racion_diaria_gr, // (cantidad_alimento_total_kg / dias_alimentacion)*1000
            "frecuencia_diaria"          => $request->frecuencia_diaria, // numero de veces
            "cantidad_por_frecuencia_gr" => $request->cantidad_por_frecuencia_gr // (racion_diaria_gr / frecuencia_diaria)
        ]);

        return response()->json([
            'message' => 'Registro creado correctamente.',
            'data'    => $reg
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ParametrosProduccion $parametrosProduccion)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ParametroProduccionRequest $request, ParametrosProduccion $id)
    {
        $id->update([
            "campania_etapa_id"          => $request->campania_etapa_id,
            "dias_alimentacion"          => $request->dias_alimentacion,
            "dias_muestreo"              => $request->dias_muestreo,
            "numero_muestreos"           => $request->numero_muestreos, // (dias_alimentacion / dias_muestreo)
            "cantidad_alimento_total_kg" => $request->cantidad_alimento_total_kg,
            "racion_diaria_gr"           => $request->racion_diaria_gr, // (cantidad_alimento_total_kg / dias_alimentacion)*1000
            "frecuencia_diaria"          => $request->frecuencia_diaria, // numero de veces
            "cantidad_por_frecuencia_gr" => $request->cantidad_por_frecuencia_gr // (racion_diaria_gr / frecuencia_diaria)
        ]);

        return response()->json([
            'message' => 'Registro actualizado correctamente.',
            'data'    => $id
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ParametrosProduccion $parametrosProduccion)
    {
        //
    }
}
