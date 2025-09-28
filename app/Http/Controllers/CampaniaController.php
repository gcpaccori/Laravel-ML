<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Campania;
use Illuminate\Http\Request;
use App\Models\CampaniaEspecie;
use App\Helpers\DataTableHelper;
use Illuminate\Support\Facades\DB;
use App\DataTables\CampaniaDataTable;
use App\Http\Requests\CampaniaRequest;

class CampaniaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new CampaniaDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Modules/Views/Campanias', [
            'title' => 'Gestionar Campañas',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Gestionar Campañas'],
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(CampaniaDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CampaniaRequest $request)
    {
        $reg = DB::transaction(function () use ( $request ) {
            // Crear la campaña
            $campania = Campania::create([
                "piscigranja_id"        => $request->piscigranja_id,
                "nombre"                => $request->nombre,
                "sistema_crianza"       => $request->sistema_crianza,
                "fecha_inicio"          => $request->fecha_inicio,
                "fecha_fin_estimada"    => $request->fecha_fin_estimada,
                "fecha_fin_real"        => $request->fecha_fin_real,
                "estado"                => $request->estado
            ]);

            // Crear especies en campañas
            if ( $request->has('especies') ) {
                foreach ($request->especies as $especie) {
                    $campania->especies()->create([
                        'especie_id'            => $especie['especie_id'],
                        'cantidad_siembra'      => $especie['cantidad_siembra'],
                        'fecha_siembra'         => $especie['fecha_siembra'],
                        'cantidad_cosechada'    => $especie['cantidad_cosechada'],
                        'peso_inicial_gr'       => $especie['peso_inicial_gr'],
                        'peso_final_gr'         => $especie['peso_final_gr'],
                    ]);
                }
            }

            return $campania;
        });

        return response()->json([
            'message' => 'Registro creado correctamente.',
            'data' => $reg
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $campania = Campania::with('especies.especie')->findOrFail($id);
        return response()->json( $campania );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CampaniaRequest $request, Campania $id)
    {
        $reg = DB::transaction(function () use ($request, $id) {
            // Actualizar la campaña
            $id->update([
                "piscigranja_id"        => $request->piscigranja_id,
                "nombre"                => $request->nombre,
                "sistema_crianza"       => $request->sistema_crianza,
                "fecha_inicio"          => $request->fecha_inicio,
                "fecha_fin_estimada"    => $request->fecha_fin_estimada,
                "fecha_fin_real"        => $request->fecha_fin_real,
                "estado"                => $request->estado
            ]);

            // Actualizar o crear especies en campañas
            if ($request->has('especies') && is_array($request->especies)) {
                foreach ($request->especies as $especieData) {
                    $id->especies()->updateOrCreate(
                        [
                            'campania_id' => $id->id,
                            'especie_id' => $especieData['especie_id']
                        ],
                        [
                            'cantidad_siembra'      => $especieData['cantidad_siembra'],
                            'fecha_siembra'         => $especieData['fecha_siembra'],
                            'cantidad_cosechada'    => $especieData['cantidad_cosechada'] ?? null,
                            'peso_inicial_gr'       => $especieData['peso_inicial_gr'] ?? null,
                            'peso_final_gr'         => $especieData['peso_final_gr'] ?? null,
                        ]
                    );
                }
            }

            return $id;
        });

        return response()->json([
            'message' => 'Registro actualizado correctamente.',
            'data' => $reg
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Campania $id)
    {
        if ( $id->especies()->exists() ) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar. Este registro tiene datos relacionados.',
            ]);
        }

        $id->delete();

        return response()->json([
            'success' => true,
            'message' => 'Campaña eliminada correctamente.',
        ]);
    }
}
