<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Piscina;
use App\Models\Piscigranja;
use Illuminate\Http\Request;
use App\Helpers\DataTableHelper;
use Illuminate\Support\Facades\DB;
use App\DataTables\PiscigranjaDataTable;
use App\Http\Requests\PiscigranjaRequest;

class PiscigranjaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new PiscigranjaDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Modules/Views/Piscigranjas', [
            'title' => 'Gestionar Piscigranjas',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(PiscigranjaDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(PiscigranjaRequest $request)
    {
        try {
            $piscigranja = DB::transaction(function () use ( $request ) {
                // Crear la piscigranja
                $piscigranja = Piscigranja::create([
                    'nombre' => $request->nombre,
                    'departamento_id' => $request->departamento_id,
                    'provincia_id' => $request->provincia_id,
                    'distrito_id' => $request->distrito_id,
                    'direccion' => $request->direccion,
                    'latitud' => $request->latitud,
                    'longitud' => $request->longitud,
                    'descripcion' => $request->descripcion,
                    'propietario' => $request->propietario,
                    'telefono_contacto' => $request->telefono_contacto,
                    'email_contacto' => $request->email_contacto,
                    'activo' => $request->activo,
                ]);

                // Guardar las piscinas si vienen en el request
                if ($request->has('piscinas')) {
                    foreach ($request['piscinas'] as $piscinaData) {
                        $piscigranja->piscinas()->create([
                            'nombre'         => $piscinaData['nombre'],
                            'descripcion'    => $piscinaData['descripcion'],
                            'superficie_m2'  => $piscinaData['superficie_m2'],
                            'profundidad_m'  => $piscinaData['profundidad_m'],
                            'estado'         => $piscinaData['estado'],
                        ]);
                    }
                }

                return $piscigranja->load('piscinas');
            });

            return response()->json([
                'message' => 'Registro creado correctamente.',
                'data' => $piscigranja
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }

    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $piscigranja = Piscigranja::with('piscinas')->findOrFail($id);
        return response()->json( $piscigranja );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(PiscigranjaRequest $request, Piscigranja $id)
    {
        try {
            $piscigranja = DB::transaction(function () use ($request, $id) {
                // Actualizar la piscigranja
                $id->update([
                    'nombre'           => $request->nombre,
                    'departamento_id'  => $request->departamento_id,
                    'provincia_id'     => $request->provincia_id,
                    'distrito_id'      => $request->distrito_id,
                    'direccion'        => $request->direccion,
                    'latitud'          => $request->latitud,
                    'longitud'         => $request->longitud,
                    'descripcion'      => $request->descripcion,
                    'propietario'      => $request->propietario,
                    'telefono_contacto'=> $request->telefono_contacto,
                    'email_contacto'   => $request->email_contacto,
                    'activo'           => $request->activo,
                ]);

                // Manejo de las piscinas
                if ($request->has('piscinas')) {
                    $piscinaIds = [];

                    foreach ($request['piscinas'] as $piscinaData) {
                        if (isset($piscinaData['id'])) {
                            // Si existe el ID → actualizar
                            $piscina = $id->piscinas()->find($piscinaData['id']);
                            if ($piscina) {
                                $piscina->update([
                                    'nombre'        => $piscinaData['nombre'],
                                    'descripcion'   => $piscinaData['descripcion'],
                                    'superficie_m2' => $piscinaData['superficie_m2'],
                                    'profundidad_m' => $piscinaData['profundidad_m'],
                                    'estado'        => $piscinaData['estado'],
                                ]);
                                $piscinaIds[] = $piscina->id;
                            }
                        } else {
                            // Si no existe ID → crear
                            $newPiscina = $id->piscinas()->create([
                                'nombre'        => $piscinaData['nombre'],
                                'descripcion'   => $piscinaData['descripcion'],
                                'superficie_m2' => $piscinaData['superficie_m2'],
                                'profundidad_m' => $piscinaData['profundidad_m'],
                                'estado'        => $piscinaData['estado'],
                            ]);
                            $piscinaIds[] = $newPiscina->id;
                        }
                    }

                    // // Eliminar piscinas que no vinieron en el request
                    // $id->piscinas()->whereNotIn('id', $piscinaIds)->delete();
                }

                return $id->load('piscinas');
            });

            return response()->json([
                'message' => 'Registro actualizado correctamente.',
                'data' => $piscigranja
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Piscigranja $id)
    {
        // Verificar si tiene piscinas o campañas relacionadas
        if ( $id->piscinas()->exists() || $id->campanias()->exists() ) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar. Este registro tiene datos relacionados.',
            ]);
        }
        // Eliminar
        $id->delete();

        return response()->json([
            'success' => true,
            'message' => 'Encuestado eliminado correctamente.',
        ]);
    }

    public function options()
    {
        $data = Piscigranja::with('piscinas')->get();
        return response()->json([
            'data' => $data
        ]);
    }

    public function getPiscinas( string $id )
    {
        $piscina = Piscina::where('piscigranja_id', $id)->get();
        return response()->json($piscina);
    }
}
