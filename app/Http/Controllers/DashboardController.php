<?php

namespace App\Http\Controllers;

use App\Models\Biometria;
use App\Models\Campania;
use App\Models\CampaniaEtapa;
use App\Models\Piscigranja;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        return Inertia::render('Dashboard', [
            'title'   => 'Dashboard General',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ]
        ]);
    }

    public function getData(Request $request)
    {
        $filtros = $request->all();

        // ==================== BIOMETRÍAS FILTRADAS ====================
        $biometrias = Biometria::query()
            ->with([
                'campaniaEtapa.etapa',
                'campaniaEtapa.piscina',
                'campaniaEtapa.campaniaEspecie.especie',
                'campaniaEtapa.campaniaEspecie.campania.piscigranja',
            ])
            ->when($filtros['campania_etapa_id'] ?? null, fn ($q, $v) => $q->where('campania_etapa_id', $v))
            ->when($filtros['campania_id'] ?? null, function ($q, $v) {
                $q->whereHas('campaniaEtapa.campaniaEspecie', fn ($q2) => $q2->where('campania_id', $v));
            })
            ->when($filtros['piscigranja_id'] ?? null, function ($q, $v) {
                $q->whereHas('campaniaEtapa.campaniaEspecie.campania', fn ($q2) => $q2->where('piscigranja_id', $v));
            })
            ->when($filtros['fecha_inicio'] ?? null, fn ($q, $v) => $q->whereDate('fecha_muestreo', '>=', $v))
            ->when($filtros['fecha_fin'] ?? null, fn ($q, $v) => $q->whereDate('fecha_muestreo', '<=', $v))
            ->orderBy('fecha_muestreo')
            ->orderBy('id')
            ->get();

        // Agrupadas por etapa para poder calcular "último registro" y "serie histórica" de cada una
        $porEtapa = $biometrias->groupBy('campania_etapa_id');

        // ==================== KPIs ====================
        $ultimasPorEtapa = $porEtapa->map(fn (Collection $items) => $items->last());

        $kpis = [
            'total_etapas_activas'        => $ultimasPorEtapa->count(),
            'total_peces_actuales'        => (int) $ultimasPorEtapa->sum('cantidad_peces_actuales'),
            'biomasa_actual_kg'           => round($ultimasPorEtapa->sum('bf_kg'), 2),
            'alimento_consumido_total_kg' => round($biometrias->sum('total_alimento_consumido_kg'), 2),
            'fca_promedio'                => $this->promedio($ultimasPorEtapa->pluck('conversion_alimenticia')),
            'supervivencia_promedio'      => $this->promedio($ultimasPorEtapa->pluck('tasa_supervivencia_porcentaje')),
            'tasa_crecimiento_promedio'   => $this->promedio($ultimasPorEtapa->pluck('tasa_crecimiento_g_dia')),
        ];

        // ==================== EVOLUCIÓN EN EL TIEMPO (una serie por etapa) ====================
        $evolucion = $porEtapa->map(function (Collection $items) {
            $primero = $items->first();
            $etapa = $primero->campaniaEtapa;

            return [
                'etapa_id'     => $primero->campania_etapa_id,
                'etapa_nombre' => $this->nombreEtapaCorto($etapa),
                'puntos'       => $items->map(fn ($b) => [
                    'fecha'                        => $b->fecha_muestreo->format('Y-m-d'),
                    'prom_peso_g'                  => $b->prom_peso_g,
                    'prom_longitud_cm'             => $b->prom_longitud_cm,
                    'tasa_supervivencia_porcentaje' => $b->tasa_supervivencia_porcentaje,
                    'conversion_alimenticia'       => $b->conversion_alimenticia,
                    'bf_kg'                         => $b->bf_kg,
                ])->values(),
            ];
        })->values();

        // ==================== TABLA COMPARATIVA POR ETAPA ====================
        $comparativaEtapas = $ultimasPorEtapa->map(function (Biometria $b) {
            $etapa = $b->campaniaEtapa;

            return [
                'etapa_id'              => $b->campania_etapa_id,
                'etapa_nombre'          => $this->nombreEtapaCorto($etapa),
                'piscigranja'           => $etapa?->campaniaEspecie?->campania?->piscigranja?->nombre ?? '-',
                'campania'              => $etapa?->campaniaEspecie?->campania?->nombre ?? '-',
                'especie'               => $etapa?->campaniaEspecie?->especie?->nombre ?? '-',
                'piscina'               => $etapa?->piscina?->nombre ?? '-',
                'peces_actuales'        => $b->cantidad_peces_actuales,
                'biomasa_actual_kg'     => $b->bf_kg,
                'alimento_consumido_kg' => $b->total_alimento_consumido_kg,
                'fca'                   => $b->conversion_alimenticia,
                'supervivencia'         => $b->tasa_supervivencia_porcentaje,
                'tasa_crecimiento'      => $b->tasa_crecimiento_g_dia,
                'ultima_fecha_muestreo' => $b->fecha_muestreo->format('d/m/Y'),
            ];
        })->sortByDesc('ultima_fecha_muestreo')->values();

        // ==================== OPCIONES DE FILTROS (para los selects en cascada) ====================


        return response()->json([
            'kpis'              => $kpis,
            'evolucion'         => $evolucion,
            'comparativaEtapas' => $comparativaEtapas,
        ]);
    }

    private function promedio(Collection $valores): ?float
    {
        $valores = $valores->filter(fn ($v) => $v !== null);

        return $valores->isEmpty() ? null : round($valores->avg(), 2);
    }

    private function nombreEtapaCorto(?CampaniaEtapa $etapa): string
    {
        if (!$etapa) {
            return '-';
        }

        $campania      = $etapa->campaniaEspecie?->campania?->nombre ?? '';
        $nombreEtapa   = $etapa->etapa?->nombre ?? '';
        $nompreEspecie = $etapa?->campaniaEspecie?->especie?->nombre ?? '-';

        return trim("{$campania} · {$nombreEtapa} ({$nompreEspecie})", ' ·');
    }
}
