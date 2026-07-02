<?php

namespace App\Http\Controllers;

use App\DataTables\ParametroAguaDataTable;
use App\Exports\ParametroAguaExport;
use App\Helpers\DataTableHelper;
use App\Models\ParametroAgua;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class HistorialAguaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $datatable = new ParametroAguaDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Modules/Views/HistorialAgua', [
            'title' => 'Historial de Parámetros del Agua',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Historial de Parámetros del Agua']
            ],
            'columns' => $columns,
        ]);
    }

    public function datatable(ParametroAguaDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    public function getChartData(Request $request)
    {
        $tipoTiempo = $request->tipo_tiempo ?? 'D'; // D=diario, M=mensual, Y=anual

        $config          = $this->getParametrosConfig();
        $axisDefinitions = $this->getAxisDefinitions();
        $axisIndexMap    = $this->buildAxisIndexMap($config);
        $yAxis           = $this->buildYAxis($config, $axisDefinitions);

        $query   = $this->buildParametrosQuery($request, $tipoTiempo);
        $grouped = $tipoTiempo === 'D'
            ? $this->fetchRawData($query, $config)
            : $this->fetchAggregatedData($query, $config, $tipoTiempo);

        [$labels, $series, $tooltips] = $this->buildChartDatasets(
            $grouped, $config, $axisIndexMap, $tipoTiempo
        );

        return response()->json([
            'chart' => [
                'tooltip' => [
                    'trigger'   => 'axis',
                    'textStyle' => ['fontSize' => 12],
                    'data'      => $tooltips,
                ],
                'legend' => ['top' => 40],
                'grid'   => ['top' => 100, 'bottom' => 60, 'left' => 60, 'right' => 60],
                'xAxis'  => ['type' => 'category', 'data' => $labels],
                'yAxis'  => $yAxis,
                'series' => $series,
            ],
        ]);
    }

    /**
     * Config de parámetros a graficar.
     * TODO: reemplazar por consulta a BD (con Cache::remember).
     */

    private function getParametrosConfig(): Collection
    {
        return collect([
            'temperatura' => [
                'label'      => 'Temperatura',
                'unit'       => '°C',
                'axis_group' => 'principal',
            ],
            'ph' => [
                'label'      => 'Grado de Acidez',
                'unit'       => 'pH',
                'axis_group' => 'principal',
            ],
            'oxigeno_disuelto' => [
                'label'      => 'Oxígeno Disuelto',
                'unit'       => 'mg/L',
                'axis_group' => 'principal',
            ],
            'ion_nitrato' => [
                'label'      => 'Ion Nitrato',
                'unit'       => 'mg/L',
                'axis_group' => 'nitrato',
            ],
        ]);
    }

    /**
     * Definición visual de cada grupo de eje.
     */
    private function getAxisDefinitions(): array
    {
        return [
            'principal' => [
                'name' => 'Temperatura / Grado de Acidez / Oxígeno Disuelto',
                'type' => 'value',
                'min'  => 0,
                'max'  => 100,
            ],
            'nitrato' => [
                'name'     => 'Ion Nitrato',
                'type'     => 'log',
                'position' => 'right',
                'min'      => 0.1,
                'max'      => 20000,
                'logBase'  => 10,
            ],
        ];
    }

    /**
     * Mapa axis_group => yAxisIndex, calculado por orden de aparición.
     */
    private function buildAxisIndexMap(Collection $config): Collection
    {
        return $config->pluck('axis_group')->unique()->values()->flip();
    }

    private function buildYAxis(Collection $config, array $axisDefinitions): Collection
    {
        return $config->pluck('axis_group')
            ->unique()
            ->values()
            ->map(fn ($group) => $axisDefinitions[$group] ?? ['type' => 'value'])
            ->values();
    }

    /**
     * Query base con los filtros de piscigranja, piscina y rango de tiempo.
     */
    private function buildParametrosQuery(Request $request, string $tipoTiempo)
    {
        $piscigranjaId = $request->piscigranja_id;
        $piscinaId     = $request->piscina_id;

        return ParametroAgua::with(['piscina.piscigranja'])
            ->when($piscigranjaId !== 'T', fn ($q) =>
                $q->whereHas('piscina', fn ($q2) => $q2->where('piscigranja_id', $piscigranjaId))
            )
            ->when($piscinaId !== 'T', fn ($q) =>
                $q->where('piscina_id', $piscinaId)
            )
            ->when($tipoTiempo === 'D' && $request->filled('fecha'), fn ($q) =>
                $q->whereDate('created_at', $request->fecha)
            )
            ->when($tipoTiempo === 'M' && $request->filled('mes'), function ($q) use ($request) {
                $q->whereYear('created_at', substr($request->mes, 0, 4))
                ->whereMonth('created_at', substr($request->mes, 5, 2));
            })
            ->when($tipoTiempo === 'Y' && $request->filled('anio'), fn ($q) =>
                $q->whereYear('created_at', $request->anio)
            );
    }

    /**
     * Datos crudos (vista diaria), sin agregación.
     */
    private function fetchRawData($query, \Illuminate\Support\Collection $config): \Illuminate\Support\Collection
    {
        return $query
            ->orderBy('created_at')
            ->get()
            ->map(function ($item) use ($config) {
                $row = [
                    'fecha'       => $item->fecha_medicion,
                    'piscina'     => $item->piscina->nombre ?? '',
                    'piscigranja' => $item->piscina->piscigranja->nombre ?? '',
                ];

                foreach ($config as $campo => $cfg) {
                    $row[$campo] = $item->$campo;
                }

                return $row;
            });
    }

    /**
     * Promedios agregados en SQL (vista mensual/anual).
     */
    private function fetchAggregatedData($query, \Illuminate\Support\Collection $config, string $tipoTiempo): \Illuminate\Support\Collection
    {
        $formatoFecha = $tipoTiempo === 'M' ? '%Y-%m-%d' : '%Y-%m';

        $selects = $config->keys()
            ->map(fn ($campo) => "ROUND(AVG($campo), 2) as $campo")
            ->implode(', ');

        return $query
            ->selectRaw("DATE_FORMAT(created_at, '{$formatoFecha}') as periodo, {$selects}")
            ->groupBy('periodo')
            ->orderBy('periodo')
            ->get()
            ->map(function ($row) use ($tipoTiempo) {
                $row->fecha = $tipoTiempo === 'M'
                    ? Carbon::createFromFormat('Y-m-d', $row->periodo)->startOfDay()
                    : Carbon::createFromFormat('Y-m', $row->periodo)->startOfMonth();

                return collect($row->toArray());
            });
    }

    private function buildChartDatasets( Collection $grouped, Collection $config, Collection $axisIndexMap, string $tipoTiempo): array {
        $formato = match ($tipoTiempo) {
            'D' => 'H:i',
            'M' => 'd M Y',
            'Y' => 'M Y',
        };

        $seriesData = $config->keys()->mapWithKeys(fn ($campo) => [$campo => []])->all();
        $labels     = [];
        $tooltips   = [];

        foreach ($grouped as $item) {
            $labels[] = $item['fecha']->translatedFormat($formato);

            $tooltipItems = [];
            foreach ($config as $campo => $cfg) {
                $valor = $item[$campo] ?? null;
                $seriesData[$campo][] = $valor;

                $tooltipItems[] = [
                    'field' => $campo,
                    'label' => $cfg['label'],
                    'value' => number_format($valor, 2) . ' ' . $cfg['unit'],
                    'unit'  => $cfg['unit'],
                ];
            }

            $title = $item['fecha']->translatedFormat(
                $tipoTiempo === 'D' ? 'l, d M Y H:i:s' : 'l, d M Y'
            );

            if ($tipoTiempo === 'D') {
                $title .= " | {$item['piscigranja']} | {$item['piscina']}";
            }

            $tooltips[] = [
                'title' => $title,
                'items' => $tooltipItems,
            ];
        }

        $series = $config
            ->map(fn ($cfg, $campo) => [
                'name'       => "{$cfg['label']} ({$cfg['unit']})",
                'type'       => 'line',
                'smooth'     => true,
                'data'       => $seriesData[$campo],
                'yAxisIndex' => $axisIndexMap[$cfg['axis_group']],
            ])
            ->values();

        return [$labels, $series, $tooltips];
    }

    public function export_csv( Request $request )
    {
        $fechaHora = Carbon::now()->format('Y-m-d_H-i-s'); // 2025-09-30_07-15-30
        $nombreArchivo = "parametros_agua_{$fechaHora}.csv";
        return Excel::download(new ParametroAguaExport($request), $nombreArchivo, \Maatwebsite\Excel\Excel::CSV);
    }

    public function export_excel(Request $request)
    {
        $fechaHora = now()->format('Y-m-d_H-i-s'); // 2025-09-30_07-15-30
        $nombreArchivo = "parametros_agua_{$fechaHora}.xlsx"; // Cambiado a XLSX

        return Excel::download(new ParametroAguaExport($request), $nombreArchivo, \Maatwebsite\Excel\Excel::XLSX);
    }
}
