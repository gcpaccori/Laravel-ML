<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Especie;
use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use App\DataTables\EspecieDataTable;
use App\Http\Requests\EspecieRequest;

class EspecieController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new EspecieDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Modules/Views/Especies', [
            'title' => 'Gestionar Especies',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Gestionar Especies']
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(EspecieDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(EspecieRequest $request)
    {
        $reg = Especie::create( [
            "nombre" => $request->nombre,
            "descripcion" => $request->descripcion
        ] );

        return response()->json([
            'message' => 'Registro creado correctamente.',
            'data' => $reg
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Especie $id)
    {
        return response()->json($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(EspecieRequest $request, Especie $id)
    {
        $id->update( [
            "nombre" => $request->nombre,
            "descripcion" => $request->descripcion
        ] );

        return response()->json([
            'message' => 'Registro actualizado correctamente.',
            'data' => $id
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Especie $id)
    {
        $id->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro eliminado correctamente.',
        ]);
    }

    public function options()
    {
        $data = Especie::all();
        return response()->json($data);
    }
}
