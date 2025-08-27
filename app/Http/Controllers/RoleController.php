<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Sistema;
use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use App\DataTables\RoleDataTable;
use App\Http\Requests\RoleRequest;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Resources\RoleResource;

class RoleController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new RoleDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);
        return Inertia::render('Seguridad/Role', [
            'title' => 'Gestionar Roles',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Sistema de Seguridad'],
                ['label' => 'Roles']
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(RoleDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RoleRequest $request)
    {
        $role = DB::transaction(function () use ($request) {
            $role = Role::create([
                'name' => $request->name,
                'active' => $request->active ?? true,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($request->permisos);

            return $role;

        });

        return response()->json([
            'message' => 'Rol creado correctamente.',
            'data' => new RoleResource($role)
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $role = Role::findOrFail($id);
        $response = new RoleResource( $role );
        return response()->json($response);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(RoleRequest $request, string $id)
    {
        $role = DB::transaction(function () use ($request, $id) {
            $role = Role::findOrFail($id);
            $role->update([
                'name' => $request->name,
                'active' => $request->active ?? true,
                'guard_name' => 'web',
            ]);

            $role->syncPermissions($request->permisos);

            return $role;

        });

        return response()->json([
            'message' => 'Rol actualizado correctamente.',
            'data' => new RoleResource($role)
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {

        DB::transaction(function () use ($id) {
            // LA ELIMINACION YA ES DE FORMA AUTOMATICA POR LA RELACION
            // $role->permissions()->detach();
            $role = Role::findOrFail($id);

            if (in_array($role->name, ['Administrador'])) {
                abort(403, 'No se puede eliminar este rol del sistema.');
            }

            $role->delete();
        });

        return response()->json([
            'message' => 'Rol eliminado correctamente.'
        ]);
    }

    public function estructuraPermisos()
    {
        $sistemas = Sistema::with([
            'modulos' => function ($q) {
                $q->whereNull('cod_father') // solo módulos raíz
                ->with([
                    'children.acciones' => function ($q2) {
                        $q2->whereHas('modulos'); // acciones asociadas a accion_modulo
                    },
                    'acciones' => function ($q2) {
                        $q2->whereHas('modulos');
                    }
                ]);
            }
        ])->get();

        $data = $sistemas->map(function ($sistema) {
            return [
                'id' => $sistema->url,
                'label' => $sistema->name,
                'children' => $sistema->modulos->map(function ($modulo) {
                    $moduloData = [
                        'id' => 'modulo_'. $modulo->url,
                        'label' => $modulo->name,
                        'children' => [],
                    ];

                    // Acciones del módulo padre
                    if ($modulo->acciones->isNotEmpty()) {
                        $moduloData['children'] = array_merge($moduloData['children'], $modulo->acciones->map(function ($accion) use ($modulo) {
                            return [
                                'id' => 'permiso_' . strtolower($modulo->url) . '.' . strtolower($accion->name),
                                'label' => $accion->accion,
                            ];
                        })->toArray());
                    }

                    // Submódulos
                    if ($modulo->children->isNotEmpty()) {
                        foreach ($modulo->children as $submodulo) {
                            $subData = [
                                'id' => 'modulo_' . $submodulo->url,
                                'label' => $submodulo->name,
                                'children' => $submodulo->acciones->map(function ($accion) use ($submodulo) {
                                    return [
                                        'id' => 'permiso_' . strtolower($submodulo->url) . '.' . strtolower($accion->name),
                                        'label' => $accion->accion,
                                    ];
                                })->toArray()
                            ];
                            $moduloData['children'][] = $subData;
                        }
                    }

                    return $moduloData;
                })->toArray()
            ];
        });

        return response()->json($data);
    }

    public function options()
    {
        $data = Role::all();
        return response()->json([
            'data' => RoleResource::collection($data)
        ]);
    }
}
