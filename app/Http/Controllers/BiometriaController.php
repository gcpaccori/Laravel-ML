<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\Campania;
use App\Models\Biometria;
use Illuminate\Http\Request;
use App\Models\CampaniaEtapa;
use App\Models\CampaniaEspecie;
use App\Helpers\DataTableHelper;
use App\Models\ParametrosProduccion;
use App\DataTables\BiometriaDataTable;
use App\Http\Requests\BiometriaRequest;

class BiometriaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new BiometriaDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Modules/Views/Biometrias', [
            'title' => 'Gestionar Biometrias',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(BiometriaDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BiometriaRequest $request)
    {
        $data = $this->calculateBiometriaData($request->all());

        $biometria = Biometria::create([
            "campania_etapa_id"                      => $data['campania_etapa_id'],
            "fecha_muestreo"                         => $data['fecha_muestreo'],
            "cantidad_peces_inicial"                 => $data['cantidad_peces_inicial'],
            "cantidad_peces_final"                   => $data['cantidad_peces_final'],
            "peso_inicial_gr"                        => $data['peso_inicial_gr'],
            "peso_final_gr"                          => $data['peso_final_gr'],
            "tamanio_inicial_cm"                     => $data['tamanio_inicial_cm'],
            "tamanio_final_cm"                       => $data['tamanio_final_cm'],
            "biomasa_inicial_kg"                     => $data['biomasa_inicial_kg'],
            "biomasa_final_kg"                       => $data['biomasa_final_kg'],
            "tasa_supervivencia_porcentaje"          => $data['tasa_supervivencia_porcentaje'],
            "tasa_crecimiento_especifico_porcentaje" => $data['tasa_crecimiento_especifico_porcentaje'],
            "observaciones"                          => $data['observaciones']
        ]);

        return response()->json([
            'message' => 'Biometría registrada con éxito',
            'data' => $biometria,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( string $id )
    {
        $biometria = Biometria::find($id);
        return response()->json( $biometria );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BiometriaRequest $request, Biometria $id)
    {
        $data = $this->calculateBiometriaData($request->all());

        $id->update([
            "campania_etapa_id"                      => $data['campania_etapa_id'],
            "fecha_muestreo"                         => $data['fecha_muestreo'],
            "cantidad_peces_inicial"                 => $data['cantidad_peces_inicial'],
            "cantidad_peces_final"                   => $data['cantidad_peces_final'],
            "peso_inicial_gr"                        => $data['peso_inicial_gr'],
            "peso_final_gr"                          => $data['peso_final_gr'],
            "tamanio_inicial_cm"                     => $data['tamanio_inicial_cm'],
            "tamanio_final_cm"                       => $data['tamanio_final_cm'],
            "biomasa_inicial_kg"                     => $data['biomasa_inicial_kg'],
            "biomasa_final_kg"                       => $data['biomasa_final_kg'],
            "tasa_supervivencia_porcentaje"          => $data['tasa_supervivencia_porcentaje'],
            "tasa_crecimiento_especifico_porcentaje" => $data['tasa_crecimiento_especifico_porcentaje'],
            "observaciones"                          => $data['observaciones']
        ]);

        return response()->json([
            'message' => 'Biometría actualizada con éxito',
            'data' => $id,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Biometria $id)
    {
        $id->delete();

        return response()->json([
            'success' => true,
            'message' => 'Registro elimino correctamente.',
        ]);
    }

    public function showCampania ( string $piscigranja_id )
    {
        $campania = Campania::where('piscigranja_id', $piscigranja_id)->get();

        return $campania;
    }

    public function showEspecie ( string $campania_id )
    {
        $especie = CampaniaEspecie::with('especie')->where('campania_id', $campania_id)->get();
        return $especie;
    }

    public function showEtapa ( string $campania_especie_id )
    {
        $etapa = CampaniaEtapa::with(['etapa', 'piscina'])->where('campania_especie_id', $campania_especie_id)->get();
        return $etapa;
    }

    public function showParametrosEtapa ( string $campania_etapa_id )
    {
        $etapa = ParametrosProduccion::where('campania_etapa_id', $campania_etapa_id)->first();
        return $etapa;
    }

    /**
     * Calcula los campos derivados de la biometría
     */
    private function calculateBiometriaData(array $data): array
    {
        $cantidadInicial = $data['cantidad_peces_inicial'] ?? 0;
        $cantidadFinal   = $data['cantidad_peces_final'] ?? 0;
        $pesoInicial     = $data['peso_inicial_gr'] ?? 0;
        $pesoFinal       = $data['peso_final_gr'] ?? 0;

        // 1. Biomasa Inicial (Kg) = N° Peces Inicial * Peso Inicial / 1000
        $data['biomasa_inicial_kg'] = ($cantidadInicial > 0 && $pesoInicial > 0)
            ? round(($cantidadInicial * $pesoInicial) / 1000, 4)
            : 0;

        // 2. Biomasa Final (Kg) = N° Peces Final * Peso Final / 1000
        $data['biomasa_final_kg'] = ($cantidadFinal > 0 && $pesoFinal > 0)
            ? round(($cantidadFinal * $pesoFinal) / 1000, 4)
            : 0;

        // 3. Tasa Supervivencia (%) = (N° Peces Final / N° Peces Inicial) * 100
        $data['tasa_supervivencia_porcentaje'] = ($cantidadInicial > 0 && $cantidadFinal > 0)
            ? round(($cantidadFinal / $cantidadInicial) * 100, 4)
            : 0;

        // 4. Tasa Crecimiento Específico (%) = (Peso Final - Peso Inicial) / Días
        $diasMuestreo = $this->showParametrosEtapa($data['campania_especie_id'] ?? null);
        $dias = $diasMuestreo?->dias_muestreo ?? 0;

        $data['tasa_crecimiento_especifico_porcentaje'] = ($pesoInicial > 0 && $pesoFinal > 0 && $dias > 0)
            ? round(($pesoFinal - $pesoInicial) / $dias, 4)
            : 0;

        return $data;
    }
}
