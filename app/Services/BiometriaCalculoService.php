<?php

namespace App\Services;

use App\Models\Biometria;
use App\Models\CampaniaEtapa;
use Carbon\Carbon;

class BiometriaCalculoService
{
    /**
     * Calcula todos los campos derivados de una biometría.
     *
     * @param array $datos Datos crudos del formulario (incluye 'detalles')
     * @param Biometria|null $anterior Biometría anterior en la cadena (null si es la primera)
     * @param CampaniaEtapa $campaniaEtapa Necesaria si es la primera (para leer campaniaEspecie)
     */
    public function calcular(array $datos, ?Biometria $anterior, CampaniaEtapa $campaniaEtapa): array
    {
        // 1. Promedios desde detalles
        $pesos           = array_column($datos['detalles'], 'peso_g');
        $longitudes      = array_column($datos['detalles'], 'longitud_cm');
        $promPesoG       = count($pesos) ? round(array_sum($pesos) / count($pesos), 4) : 0.0;
        $promLongitudCm  = count($longitudes) ? round(array_sum($longitudes) / count($longitudes), 4) : 0.0;
        $poblacionActual = $datos['cantidad_peces_actuales'];

        // 2. Iniciales: heredados del anterior, o de campaniaEspecie si es el primero
        if ($anterior) {
            $fechaInicial           = $anterior->fecha_muestreo;
            $cantidadPecesIniciales = $anterior->cantidad_peces_actuales;
            // $cantidadPecesIniciales = $anterior->cantidad_peces_iniciales;
            $biKg                   = $anterior->bf_kg;
        } else {
            $campaniaEspecie = $campaniaEtapa->campaniaEspecie;

            if (!$campaniaEspecie) {
                throw new \RuntimeException('La etapa no tiene una campaña-especie asociada.');
            }

            $fechaInicial           = $campaniaEspecie->fecha_siembra;
            $cantidadPecesIniciales = $campaniaEspecie->cantidad_siembra;
            $biKg = ($campaniaEspecie->cantidad_siembra && $campaniaEspecie->peso_inicial_gr)
                ? round(($campaniaEspecie->cantidad_siembra * $campaniaEspecie->peso_inicial_gr) / 1000, 4)
                : 0.0;
        }

        // 3. Biomasa final = peces actuales ó peces iniciales * peso promedio actual
        $bfKg = $promPesoG > 0
            ? round(($poblacionActual * $promPesoG) / 1000, 4)
            : 0.0;

        // 4. Tiempo transcurrido
        $tiempoDias = ($fechaInicial && $datos['fecha_muestreo'])
            ? Carbon::parse($fechaInicial)->diffInDays(Carbon::parse($datos['fecha_muestreo']))
            : 0;

        // 5. Tasa de crecimiento (g/día)
        $biomasaGanada = $bfKg - $biKg;
        $tasaCrecimientoGDia = $tiempoDias > 0
            ? round( (($biomasaGanada / $tiempoDias) / $poblacionActual) * 1000 , 4)
            : 0.0;

        // 6. Conversión alimenticia (FCA)
        $conversionAlimenticia = $biomasaGanada > 0
            ? round($datos['total_alimento_consumido_kg'] / $biomasaGanada, 4)
            : 0.0;

        // 7. Supervivencia
        $tasaSupervivencia = $cantidadPecesIniciales > 0
            ? round(($poblacionActual / $cantidadPecesIniciales) * 100, 4)
            : null;

        // 8. % de muestreo
        $cantidadMuestreo = $datos['cantidad_muestreo'] ?? count($datos['detalles']);
        $muestreoPorcentaje = $poblacionActual > 0
            ? round(($cantidadMuestreo * 100) / $poblacionActual , 4)
            : 0.0;

        return [
            'fecha_inicial'                 => $fechaInicial,
            'cantidad_peces_iniciales'      => $cantidadPecesIniciales,
            'cantidad_muestreo'             => $cantidadMuestreo,
            'muestreo_porcentaje'           => $muestreoPorcentaje,
            'tiempo_dias'                   => $tiempoDias,
            'bi_kg'                         => $biKg,
            'bf_kg'                         => $bfKg,
            'prom_longitud_cm'              => $promLongitudCm,
            'prom_peso_g'                   => $promPesoG,
            'tasa_crecimiento_g_dia'        => $tasaCrecimientoGDia,
            'conversion_alimenticia'        => $conversionAlimenticia,
            'tasa_supervivencia_porcentaje' => $tasaSupervivencia,
        ];
    }
}
