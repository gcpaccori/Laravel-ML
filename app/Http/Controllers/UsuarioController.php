<?php

namespace App\Http\Controllers;

use App\Models\User;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use App\DataTables\UsersDataTable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Http\Requests\UsuarioRequest;
use App\Http\Resources\UsuarioResource;

class UsuarioController extends Controller
{
    // Renderiza la vista con Vue e Inertia
    public function index()
    {
        $datatable = new UsersDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Seguridad/Usuario', [
            'title' => 'Gestionar Usuarios',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Sistema de Seguridad'],
                ['label' => 'Usuarios']
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    // Endpoint para DataTables server-side
    public function datatable(UsersDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    public function store(UsuarioRequest $request)
    {
        $user = DB::transaction(function () use ($request) {
            $user = User::create([
               'name' => $request->name,
               'email' => $request->email,
               "password" => Hash::make($request->password)
            ]);

            $user->roles()->sync($request->role_id);

           return $user;

        });

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'data' => new UsuarioResource($user)
        ]);
    }

    public function edit(string $id)
    {
        $user = User::findOrFail( $id );
        $response = new UsuarioResource($user);
        return response()->json($response);
    }

    public function update(UsuarioRequest $request, string $id)
    {
        $user = DB::transaction(function () use ($request, $id) {
            $user = User::findOrFail($id);
            $data = [
                'name' => $request->name,
                'email' => $request->email,
            ];

            // Solo actualizar la contraseña si se envía
            if ($request->filled('password')) {
                $data['password'] = Hash::make($request->password);
            }

            $user->update($data);
            $user->roles()->sync($request->role_id);
            return $user;
        });

        return response()->json([
            'message' => 'Usuario actualizado correctamente.',
            'data' => new UsuarioResource($user)
        ]);
    }

    public function destroy(User $id)
    {
        $id->delete();
        return response()->json(['message' => 'Usuario eliminado correctamente']);
    }

    public function options()
    {
        $data = User::all();
        return response()->json([
            'data' => UsuarioResource::collection($data)
        ]);
    }
}
