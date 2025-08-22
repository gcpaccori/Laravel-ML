<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Sistema;
use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use Illuminate\Support\Facades\DB;
use App\DataTables\SistemasDataTable;
use App\Http\Requests\SistemaRequest;
use App\Http\Resources\SistemaResource;

class SistemaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new SistemasDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);
        return Inertia::render('Seguridad/Sistema', [
            'title' => 'Gestionar Sistemas',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Sistema de Seguridad'],
                ['label' => 'Sistemas'],
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(SistemasDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(SistemaRequest $request)
    {
        $sistema = DB::transaction(function () use ($request) {
            return Sistema::create([
                'name' => $request->name,
                'icon' => $request->icon,
                'url' => $request->url,
                'order' => $request->order ?? 0,
            ]);
        });

        return response()->json([
            'message' => 'Sistema creado correctamente.',
            'data' => new SistemaResource($sistema)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Sistema $id)
    {
        return response()->json($id);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(SistemaRequest $request, Sistema $id)
    {
        $id->update($request->validated());

        return response()->json([
            'message' => 'Sistema actualizado correctamente.',
            'data' => $id
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $sistema = Sistema::findOrFail($id);
        $sistema->delete();

        return response()->json(['message' => 'Sistema eliminado correctamente']);
    }

    public function options()
    {
        $data = Sistema::all();
        return response()->json([
            'data' => SistemaResource::collection($data)
        ]);
    }
}
