<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Campania;
use Illuminate\Http\Request;
use App\Models\CampaniaEtapa;
use App\Http\Requests\CampaniaEtapaRequest;
use App\Http\Requests\CampaniaEtapaCloseRequest;

class CampaniaEtapaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index() {}

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Recibir el parámetro del POST
        $campania_id = $request->campania_id;
        $campania    = Campania::with(['piscigranja', 'especies.especie'])->find($campania_id);
        if (empty($campania)) {
            return Inertia::render('errors/Error403', [
                'title'   => 'Error 404',
                'toolbar' => [
                    ['label' => 'Inicio', 'route' => 'dashboard'],
                    ['label' => 'Campañas', 'route' => 'sispiscis.campanias.index'],
                ],
                'menssage' => 'La campaña no existe o la ruta no es correcta.',
                'redirect' => ['route' => 'sispiscis.campanias.index', 'text' => 'Regresar a campañas']
            ]);
        }

        return Inertia::render('Modules/Views/EtapasCampania', [
            'title'   => 'Registro de las Etapas de la Campaña',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Campañas', 'route' => 'sispiscis.campanias.index'],
                ['label' => $campania->nombre . ' - ' . $campania?->piscigranja->nombre]
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
            "campania_especie_id"    => $request->campania_especie_id,
            "etapa_id"               => $request->etapa_id,
            "piscina_id"             => $request->piscina_id,
            "area_piscigranja_m2"    => $request->area_piscigranja_m2,
            "volumen_piscigranja_m3" => $request->volumen_piscigranja_m3,
            "altura_piscigranja_m"   => $request->altura_piscigranja_m,
            "fecha_inicio"           => $request->fecha_inicio,
            "fecha_fin"              => $request->fecha_fin,
            "numero_peces_inicial"   => $request->numero_peces_inicial,
            "numero_peces_final"     => $request->numero_peces_final,
            "peso_inicial_gr"        => $request->peso_inicial_gr,
            "peso_final_gr"          => $request->peso_final_gr,
            "densidad_siembra"       => $request->densidad_siembra,
            "estado"                 => $request->estado,
        ]);

        return response()->json([
            'message' => 'Registro creado correctamente.',
            'data'    => $reg
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
        $campania_etapa = CampaniaEtapa::with('parametrosProduccion')->find( $id );
        return response()->json( $campania_etapa );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(CampaniaEtapaRequest $request, CampaniaEtapa $id)
    {
        $id->update([
            "campania_especie_id"    => $request->campania_especie_id,
            "etapa_id"               => $request->etapa_id,
            "piscina_id"             => $request->piscina_id,
            "area_piscigranja_m2"    => $request->area_piscigranja_m2,
            "volumen_piscigranja_m3" => $request->volumen_piscigranja_m3,
            "altura_piscigranja_m"   => $request->altura_piscigranja_m,
            "fecha_inicio"           => $request->fecha_inicio,
            "fecha_fin"              => $request->fecha_fin,
            "numero_peces_inicial"   => $request->numero_peces_inicial,
            "numero_peces_final"     => $request->numero_peces_final,
            "peso_inicial_gr"        => $request->peso_inicial_gr,
            "peso_final_gr"          => $request->peso_final_gr,
            "densidad_siembra"       => $request->densidad_siembra,
            "estado"                 => $request->estado,
        ]);

        return response()->json([
            'message' => 'Registro actualizado correctamente.',
            'data'    => $id
        ]);
    }

    public function updateClose(CampaniaEtapaCloseRequest $request, CampaniaEtapa $id)
    {
        $id->update([
            "fecha_fin"        => $request->fecha_fin,
            "cantidad_final"   => $request->cantidad_final,
            "peso_promedio_gr" => $request->peso_promedio_gr,
            "peso_promedio_gr" => $request->peso_promedio_gr,
            "estado"           => 'finalizada',
        ]);

        return response()->json([
            'message' => 'Etapa finalizada correctamente.',
            'data'    => $id
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(CampaniaEtapa $id)
    {
        $id->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro eliminado correctamente.',
        ]);
    }

    public function options(string $campania_especie_id)
    {
        $data = CampaniaEtapa::with(['etapa', 'piscina'])
            ->where('campania_especie_id', $campania_especie_id)
            ->orderBy('id', 'desc')
            ->get();
        return response()->json($data);
    }
}
