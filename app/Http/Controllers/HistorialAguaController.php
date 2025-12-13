<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\ParametroAgua;
use App\Helpers\DataTableHelper;
use Illuminate\Support\Facades\DB;
use App\Exports\ParametroAguaExport;
use Maatwebsite\Excel\Facades\Excel;
use App\DataTables\ParametroAguaDataTable;

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
        $piscigranjaId = $request->piscigranja_id;
        $piscinaId = $request->piscina_id;
        $tipoTiempo = $request->tipo_tiempo ?? 'D'; // D=diario, M=mensual, Y=anual

        $query = ParametroAgua::with(['piscina.piscigranja'])
            ->orderBy('created_at', 'asc');

        if ($piscigranjaId !== 'T') {
            $query->whereHas('piscina', function ($q) use ($piscigranjaId) {
                $q->where('piscigranja_id', $piscigranjaId);
            });
        }

        if ($piscinaId !== 'T') {
            $query->where('piscina_id', $piscinaId);
        }

        // Filtro por rango de tiempo
        if ($tipoTiempo === 'D' && $request->filled('fecha')) {
            $query->whereDate('created_at', $request->fecha);
        } elseif ($tipoTiempo === 'M' && $request->filled('mes')) {
            $query->whereYear('created_at', substr($request->mes, 0, 4))
                ->whereMonth('created_at', substr($request->mes, 5, 2));
        } elseif ($tipoTiempo === 'Y' && $request->filled('anio')) {
            $query->whereYear('created_at', $request->anio);
        }

        $parametros = $query->get();

        // Agrupación dinámica según filtro
        if ($tipoTiempo === 'D') {
            // Sin agrupar, muestra variación en el día
            $grouped = $parametros->map(function ($item) {
                return [
                    'fecha' => $item->fecha_medicion,
                    'temperatura' => $item->temperatura,
                    'ph' => $item->ph,
                    'oxigeno_disuelto' => $item->oxigeno_disuelto,
                    'ion_nitrato' => $item->ion_nitrato,
                    'piscina' => $item->piscina->nombre ?? '',
                    'piscigranja' => $item->piscina->piscigranja->nombre ?? '',
                ];
            });
        } elseif ($tipoTiempo === 'M') {
            // Agrupar por día y promediar
            $grouped = $parametros->groupBy(function ($item) {
                return $item->fecha_medicion->format('Y-m-d');
            })->map(function ($items) {
                return [
                    'fecha' => $items->first()->fecha_medicion->startOfDay(),
                    'temperatura' => $items->avg('temperatura'),
                    'ph' => $items->avg('ph'),
                    'oxigeno_disuelto' => $items->avg('oxigeno_disuelto'),
                    'ion_nitrato' => $items->avg('ion_nitrato'),
                ];
            });
        } else { // Y = anual
            // Agrupar por mes y promediar
            $grouped = $parametros->groupBy(function ($item) {
                return $item->fecha_medicion->format('Y-m');
            })->map(function ($items) {
                return [
                    'fecha' => $items->first()->fecha_medicion->startOfMonth(),
                    'temperatura' => $items->avg('temperatura'),
                    'ph' => $items->avg('ph'),
                    'oxigeno_disuelto' => $items->avg('oxigeno_disuelto'),
                    'ion_nitrato' => $items->avg('ion_nitrato'),
                ];
            });
        }

        // Preparar datos para el gráfico
        $labels = $grouped->map(fn($item) =>
            $item['fecha']->translatedFormat(
                $tipoTiempo === 'Y' ? 'M Y' : ($tipoTiempo === 'M' ? 'd M Y' : 'H:i')
            )
        )->values();

        $tooltips = $grouped->map(function ($item) use ($tipoTiempo) {
            $base = $item['fecha']->translatedFormat(
                $tipoTiempo === 'D' ? 'l, d M Y H:i:s' : 'l, d M Y'
            );

            if ($tipoTiempo === 'D') {
                $base .= " | {$item['piscigranja']} | {$item['piscina']}";
            }

            return $base;
        })->values();

        $series = [
            [
                'name' => 'Temperatura (°C)',
                'type' => 'line',
                'smooth' => true,
                'data' => $grouped->pluck('temperatura')->values(),
            ],
            [
                'name' => 'pH',
                'type' => 'line',
                'smooth' => true,
                'data' => $grouped->pluck('ph')->values(),
            ],
            [
                'name' => 'Oxígeno disuelto (mg/L)',
                'type' => 'line',
                'smooth' => true,
                'data' => $grouped->pluck('oxigeno_disuelto')->values(),
            ],
            [
                'name' => 'Ion Nitrato (mg/L)',
                'type' => 'line',
                'smooth' => true,
                'data' => $grouped->pluck('ion_nitrato')->values(),
            ],
        ];

        return response()->json([
            'labels' => $labels,
            'tooltips' => $tooltips,
            'series' => $series,
        ]);
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
