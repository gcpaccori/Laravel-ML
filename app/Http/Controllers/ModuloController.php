<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Accion;
use App\Models\Modulo;
use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use App\Helpers\PermissionHelper;
use Illuminate\Support\Facades\DB;
use App\DataTables\ModuloDataTable;
use App\Http\Requests\ModuloRequest;
use App\Http\Resources\ModuloResource;
use Spatie\Permission\Models\Permission;

class ModuloController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new ModuloDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Seguridad/Modulo', [
            'title' => 'Gestionar Módulos',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Sistema de Seguridad'],
                ['label' => 'Módulos']
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(ModuloDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ModuloRequest $request)
    {
        $modulo = DB::transaction(function () use ($request) {
            $modulo = Modulo::create([
               'sistema_id' => $request->sistema_id,
               'name' => $request->name,
               'cod_father' => $request->cod_father,
               'url' => $request->url,
               'icon' => $request->icon,
               'order' => $request->order ?? 0,
               'active' => $request->active,
           ]);

           // Agregar acciones
           if ($request->filled('acciones')) {
                $accionesIds = collect($request->acciones)->pluck('id')->toArray();
                $modulo->acciones()->sync($accionesIds);
           }

           PermissionHelper::syncPermisosDesdeAccionModulo();
           return $modulo;

        });

        return response()->json([
            'message' => 'Módulo creado correctamente.',
            'data' => new ModuloResource($modulo)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $modulo = Modulo::findOrFail( $id );
        $response = new ModuloResource($modulo);
        return response()->json($response);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ModuloRequest $request, string $id)
    {
        $modulo = DB::transaction(function () use ($request, $id) {
            $modulo = Modulo::findOrFail($id);
            $modulo->update([
                'sistema_id' => $request->sistema_id,
                'name' => $request->name,
                'cod_father' => $request->cod_father,
                'url' => $request->url,
                'icon' => $request->icon,
                'order' => $request->order ?? 0,
                'active' => $request->active,
            ]);

            // Sincronizar acciones
            if ($request->filled('acciones')) {
                $accionesIds = collect($request->acciones)->pluck('id')->toArray();
                $modulo->acciones()->sync($accionesIds);
            } else {
                $modulo->acciones()->detach(); // Elimina todas si no se envió ninguna
            }

            PermissionHelper::syncPermisosDesdeAccionModulo();
            return $modulo;
        });

        return response()->json([
            'message' => 'Módulo actualizado correctamente.',
            'data' => new ModuloResource($modulo)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $modulo = Modulo::findOrFail($id);
        DB::transaction(function () use ($modulo) {
            // Limpia relaciones con acciones
            $modulo->acciones()->detach();
            // Elimina el módulo
            $modulo->delete();
            PermissionHelper::syncPermisosDesdeAccionModulo();
        });

        return response()->json([
            'message' => 'Módulo eliminado correctamente.'
        ]);
    }

    public function showPadre( string $id )
    {
        $padre = Modulo::where('sistema_id', $id)->whereNull('cod_father')->get();
        return response()->json($padre);
    }
}
