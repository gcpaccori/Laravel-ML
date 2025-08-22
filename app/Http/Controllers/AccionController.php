<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Accion;
use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use App\Helpers\PermissionHelper;
use App\DataTables\AccionsDataTable;
use App\Http\Requests\AccionRequest;
use App\Http\Resources\AccionResource;

class AccionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new AccionsDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Seguridad/Accions', [
            'title' => 'Gestionar Acciones / Botones',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Sistema de Seguridad'],
                ['label' => 'Acciones'],
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(AccionsDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(AccionRequest $request)
    {
        $accion = Accion::create($request->all());

        return response()->json([
            'message' => 'Acción creada correctamente.',
            'data' => new AccionResource($accion)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Accion $id)
    {
        return response()->json( new AccionResource($id) );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(AccionRequest $request, Accion $id)
    {
        $id->update($request->all());

        PermissionHelper::syncPermisosDesdeAccionModulo();

        return response()->json([
            'message' => 'Acción actualizada correctamente.',
            'data' => $id
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Accion $id)
    {
        $id->delete();
        PermissionHelper::syncPermisosDesdeAccionModulo();
        return response()->json(['message' => 'Acción eliminada correctamente']);
    }

    public function options()
    {
        $data = Accion::all();
        return response()->json([
            'data' => AccionResource::collection($data)
        ]);
    }
}
