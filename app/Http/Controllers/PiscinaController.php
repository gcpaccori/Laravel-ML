<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Piscina;
use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use App\DataTables\PiscinaDataTable;
use App\Http\Requests\PiscinaRequest;

class PiscinaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new PiscinaDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Modules/Views/Piscinas', [
            'title' => 'Gestionar Piscinas',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(PiscinaDataTable $dataTable)
    {
        return $dataTable->ajax();
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(PiscinaRequest $request)
    {
        $piscina = Piscina::create( [
            "piscigranja_id" => $request->piscigranja_id,
            "nombre" => $request->nombre,
            "descripcion" => $request->descripcion,
            "superficie_m2" => $request->superficie_m2,
            "profundidad_m" => $request->profundidad_m,
            "volumen_m3" => $request->volumen_m3,
            "estado" => $request->estado
        ] );

        return response()->json([
            'message' => 'Registro creado correctamente.',
            'data' => $piscina
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Piscina $id)
    {
        return response()->json($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PiscinaRequest $request, Piscina $id)
    {
        $id->update( [
            "piscigranja_id" => $request->piscigranja_id,
            "nombre" => $request->nombre,
            "descripcion" => $request->descripcion,
            "superficie_m2" => $request->superficie_m2,
            "profundidad_m" => $request->profundidad_m,
            "volumen_m3" => $request->volumen_m3,
            "estado" => $request->estado
        ] );

        return response()->json([
            'message' => 'Registro actualizado correctamente.',
            'data' => $id
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Piscina $id)
    {
        if ( $id->parametrosAguas()->exists() ) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar. Este registro tiene datos relacionados.',
            ]);
        }

        $id->delete();

        return response()->json([
            'success' => true,
            'message' => 'Piscina eliminado correctamente.',
        ]);
    }

    public function options()
    {
        $data = Piscina::all();
        return response()->json($data);
    }
}
