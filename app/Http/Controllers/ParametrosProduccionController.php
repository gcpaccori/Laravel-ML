<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\ParametrosProduccion;
use App\Http\Requests\ParametroProduccionRequest;

class ParametrosProduccionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ParametroProduccionRequest $request)
    {
        $data = $this->calculateData($request->all());

        $parametro = ParametrosProduccion::create([
            "campania_etapa_id"          => $data['campania_etapa_id'],
            "dias_alimentacion"          => $data['dias_alimentacion'],
            "dias_muestreo"              => $data['dias_muestreo'],
            "foto_periodo_horas_dia"     => $data['foto_periodo_horas_dia'],
            "foto_periodo_horas_noche"   => $data['foto_periodo_horas_noche'],
            "numero_muestreos"           => $data['numero_muestreos'],             // (dias_alimentacion / dias_muestreo)
            "cantidad_alimento_total_kg" => $data['cantidad_alimento_total_kg'],
            "racion_diaria_gr"           => $data['racion_diaria_gr'],             // (cantidad_alimento_total_kg / dias_alimentacion)*1000
            "frecuencia_diaria"          => $data['frecuencia_diaria'],            // numero de veces
            "cantidad_por_frecuencia_gr" => $data['cantidad_por_frecuencia_gr']    // (racion_diaria_gr / frecuencia_diaria)
        ]);

        return response()->json([
            'message' => 'Parámetros de producción registrados con éxito',
            'data'    => $parametro,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ParametroProduccionRequest $request, ParametrosProduccion $id)
    {
        $data = $this->calculateData($request->all());

        $id->update([
            "campania_etapa_id"          => $data['campania_etapa_id'],
            "dias_alimentacion"          => $data['dias_alimentacion'],
            "dias_muestreo"              => $data['dias_muestreo'],
            "foto_periodo_horas_dia"     => $data['foto_periodo_horas_dia'],
            "foto_periodo_horas_noche"   => $data['foto_periodo_horas_noche'],
            "numero_muestreos"           => $data['numero_muestreos'],             // (dias_alimentacion / dias_muestreo)
            "cantidad_alimento_total_kg" => $data['cantidad_alimento_total_kg'],
            "racion_diaria_gr"           => $data['racion_diaria_gr'],             // (cantidad_alimento_total_kg / dias_alimentacion)*1000
            "frecuencia_diaria"          => $data['frecuencia_diaria'],            // numero de veces
            "cantidad_por_frecuencia_gr" => $data['cantidad_por_frecuencia_gr']    // (racion_diaria_gr / frecuencia_diaria)
        ]);

        return response()->json([
            'message' => 'Parámetros de producción actualizados con éxito',
            'data'    => $id,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ParametrosProduccion $parametrosProduccion)
    {
        //
    }

    private function calculateData(array $data): array
    {
        $diasAlimentacion = $data['dias_alimentacion'] ?? 0;
        $diasMuestreo     = $data['dias_muestreo'] ?? 0;
        $alimentoKg       = $data['cantidad_alimento_total_kg'] ?? 0;
        $frecuencia       = $data['frecuencia_diaria'] ?? 0;

        // 1. N° muestreos
        $data['numero_muestreos'] = ($diasAlimentacion > 0 && $diasMuestreo > 0)
            ? round($diasAlimentacion / $diasMuestreo)
            : 0;

        // 2. Ración diaria (g/día) = (alimento_total_kg / dias_alimentacion) * 1000
        $data['racion_diaria_gr'] = ($alimentoKg > 0 && $diasAlimentacion > 0)
            ? round(($alimentoKg / $diasAlimentacion) * 1000, 6)
            : 0;

        // 3. Cantidad por frecuencia (g) = racion_diaria / frecuencia
        $data['cantidad_por_frecuencia_gr'] = ($data['racion_diaria_gr'] > 0 && $frecuencia > 0)
            ? round($data['racion_diaria_gr'] / $frecuencia, 6)
            : 0;

        return $data;
    }
}
