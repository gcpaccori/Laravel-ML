<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Campania;
use App\Models\Biometria;
use Illuminate\Http\Request;
use App\Models\CampaniaEtapa;
use App\Models\CampaniaEspecie;
use App\Helpers\DataTableHelper;
use App\DataTables\BiometriaDataTable;

class BiometriaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new BiometriaDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Modules/Views/Biometrias', [
            'title' => 'Gestionar Biometrias',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(BiometriaDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $reg = Biometria::create([
            "campania_etapa_id"                      => $request->campania_etapa_id,
            "fecha_muestreo"                         => $request->fecha_muestreo,
            // "numero_muestreo"                        => $request->numero_muestreo,
            "peso_inicial_gr"                        => $request->peso_inicial_gr,
            "peso_final_gr"                          => $request->peso_final_gr,
            "tamanio_inicial_cm"                     => $request->tamanio_inicial_cm,
            "tamanio_final_cm"                       => $request->tamanio_final_cm,
            "biomasa_inicial_kg"                     => $request->biomasa_inicial_kg,
            "biomasa_final_kg"                       => $request->biomasa_final_kg,
            "tasa_supervivencia_porcentaje"          => $request->tasa_supervivencia_porcentaje,
            "tasa_crecimiento_especifico_porcentaje" => $request->tasa_crecimiento_especifico_porcentaje,
            "observaciones"                          => $request->observaciones
        ]);

        return response()->json([
            'message' => 'Registro creado correctamente.',
            'data' => $reg
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( string $id )
    {
        $biometria = Biometria::find($id);
        return response()->json( $biometria );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Biometria $id)
    {
        $id->update([
            "campania_etapa_id"                      => $request->campania_etapa_id,
            "fecha_muestreo"                         => $request->fecha_muestreo,
            // "numero_muestreo"                        => $request->numero_muestreo,
            "peso_inicial_gr"                        => $request->peso_inicial_gr,
            "peso_final_gr"                          => $request->peso_final_gr,
            "tamanio_inicial_cm"                     => $request->tamanio_inicial_cm,
            "tamanio_final_cm"                       => $request->tamanio_final_cm,
            "biomasa_inicial_kg"                     => $request->biomasa_inicial_kg,
            "biomasa_final_kg"                       => $request->biomasa_final_kg,
            "tasa_supervivencia_porcentaje"          => $request->tasa_supervivencia_porcentaje,
            "tasa_crecimiento_especifico_porcentaje" => $request->tasa_crecimiento_especifico_porcentaje,
            "observaciones"                          => $request->observaciones
        ]);

        return response()->json([
            'message' => 'Registro actualizado correctamente.',
            'data' => $id
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Biometria $id)
    {
        $id->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro elimino correctamente.',
        ]);
    }

    public function showCampania ( string $piscigranja_id )
    {
        $campania = Campania::where('piscigranja_id', $piscigranja_id)->get();

        return $campania;
    }

    public function showEspecie ( string $campania_id )
    {
        $especie = CampaniaEspecie::with('especie')->where('campania_id', $campania_id)->get();
        return $especie;
    }

    public function showEtapa ( string $campania_especie_id )
    {
        $etapa = CampaniaEtapa::with(['etapa', 'piscina'])->where('campania_especie_id', $campania_especie_id)->get();
        return $etapa;
    }
}
