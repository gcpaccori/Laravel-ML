<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Campania;
use Illuminate\Http\Request;
use App\Models\CampaniaEtapa;
use App\Http\Requests\CampaniaEtapaRequest;

class CampaniaEtapaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {

    }

    /**
     * Show the form for creating a new resource.
     */
    public function create( Request $request )
    {
        // Recibir el parámetro del POST
        $campania_id = $request->campania_id;
        $campania = Campania::with(['piscigranja', 'especies.especie'])->find(  $campania_id );
        if ( empty($campania) ) {
            return Inertia::render('errors/Error403', [
                'title' => 'Error 404',
                'toolbar' => [
                    ['label' => 'Inicio', 'route' => 'dashboard'],
                    ['label' => 'Campañas', 'route' => 'sispiscis.campanias.index'],
                ],
                'menssage' => 'La campaña no existe o la ruta no es correcta.',
                'redirect' => [ 'route' => 'sispiscis.campanias.index', 'text' => 'Regresar a campañas' ]
            ]);
        }

        return Inertia::render('Modules/Views/EtapasCampania', [
            'title' => 'Registro de las Etapas de la Campaña',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Campañas', 'route' => 'sispiscis.campanias.index'],
                ['label' => $campania->nombre .' - '. $campania?->piscigranja->nombre]
            ],
            'campania' => $campania
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(CampaniaEtapaRequest $request)
    {
        $reg = CampaniaEtapa::create([
            "campania_especie_id" => $request->campania_especie_id,
            "etapa_id" => $request->etapa_id,
            "piscina_id" => $request->piscina_id,
            "fecha_inicio" => $request->fecha_inicio,
            "fecha_fin" => $request->fecha_fin,
            "cantidad_inicial" => $request->cantidad_inicial,
            "cantidad_final" => $request->cantidad_final,
            "peso_promedio_gr" => $request->peso_promedio_gr,
            "estado" => $request->estado,
        ]);

        return response()->json([
            'message' => 'Registro creado correctamente.',
            'data' => $reg
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }

    public function options( string $campania_especie_id )
    {
        $data = CampaniaEtapa::with(['etapa', 'piscina'])->where('campania_especie_id', $campania_especie_id)->get();
        return response()->json($data);
    }
}
