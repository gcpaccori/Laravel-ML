<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Http\Request;
use App\Models\ParametroAgua;
use App\Helpers\DataTableHelper;
use Illuminate\Support\Facades\DB;
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

    public function getChartData( Request $request )
    {
        $piscigranjaId = $request->piscigranja_id;
        $piscinaId = $request->piscina_id;

        // Traer datos ordenados por fecha
            $query = ParametroAgua::query()
                ->with(['piscina.piscigranja'])
                ->orderBy('fecha_medicion', 'asc');

            if ( $piscigranjaId !== 'T' ) {
                $query->whereHas('piscina', function($q) use ( $piscigranjaId ) {
                    $q->where('piscigranja_id',  $piscigranjaId );
                });
            }

            if ( $piscinaId !== 'T') {
                $query->where('piscina_id',  $piscinaId);
            }

            $parametros = $query->get();

        // Agrupar por fecha (solo día) y calcular promedio
        // $parametros = ParametroAgua::select(
        //         DB::raw('DATE(fecha_medicion) as fecha_medicion'),
        //         DB::raw('AVG(temperatura) as temperatura'),
        //         DB::raw('AVG(ph) as ph'),
        //         DB::raw('AVG(oxigeno_disuelto) as oxigeno_disuelto'),
        //         DB::raw('AVG(ion_nitrato) as ion_nitrato')
        //     )
        //     ->groupBy(DB::raw('DATE(fecha_medicion)'))
        //     ->orderBy(DB::raw('DATE(fecha_medicion)'), 'asc')
        //     ->get();

        // Eje X corto (para mostrar en el gráfico)
        $labels = $parametros->map(function ($item) {
            return $item->fecha_medicion
                ? $item->fecha_medicion->format('d/m/Y')
                : null;
        });

        // Fechas completas (para usar en el tooltip)
        $tooltips = $parametros->map(function ($item) {
            return $item->fecha_medicion
                ? $item->fecha_medicion->translatedFormat('l, d M Y H:i:s')
                : null;
        });

        $series = [
            [
                'name' => 'Temperatura (°C)',
                'type' => 'line',
                'smooth' => true,
                'data' => $parametros->pluck('temperatura'),
            ],
            [
                'name' => 'pH',
                'type' => 'line',
                'smooth' => true,
                'data' => $parametros->pluck('ph'),
            ],
            [
                'name' => 'Oxígeno disuelto (mg/L)',
                'type' => 'line',
                'smooth' => true,
                'data' => $parametros->pluck('oxigeno_disuelto'),
            ],
            [
                'name' => 'Ion Nitrato (mg/L)',
                'type' => 'line',
                'smooth' => true,
                'data' => $parametros->pluck('ion_nitrato'),
            ],
        ];

        return response()->json([
            'labels' => $labels,   // Para el eje X
            'tooltips' => $tooltips, // Para el título del tooltip
            'series' => $series,
        ]);

    }


// public function getChartData(Request $request)
// {
//     $filtro = $request->input('filtro', 'dia'); // 'dia', 'mes', 'anio'
//     $piscinaId = $request->input('piscina_id');

//     $query = ParametroAgua::query()
//         ->when($piscinaId && $piscinaId !== 'T', function($q) use ($piscinaId) {
//             $q->where('piscina_id', $piscinaId);
//         });

//     if ($filtro === 'dia') {
//         // Último día (todas las mediciones por tiempo)
//         $fecha = Carbon::now()->startOfDay();
//         $data = $query->whereDate('fecha_medicion', $fecha)
//             ->orderBy('fecha_medicion')
//             ->get()
//             ->map(function($item) {
//                 return [
//                     'fecha' => $item->fecha_medicion->format('d M Y H:i'),
//                     'tooltip' => $item->fecha_medicion->isoFormat('dddd, D MMM YYYY HH:mm:ss'),
//                     'temperatura' => (float) $item->temperatura,
//                     'ph' => (float) $item->ph,
//                     'oxigeno' => (float) $item->oxigeno_disuelto,
//                     'nitrato' => (float) $item->ion_nitrato,
//                 ];
//             });

//     } elseif ($filtro === 'mes') {
//         // Promedio por día del mes actual
//         $fecha = Carbon::now();
//         $data = $query->select(
//                 DB::raw('DATE(fecha_medicion) as fecha'),
//                 DB::raw('AVG(temperatura) as temperatura'),
//                 DB::raw('AVG(ph) as ph'),
//                 DB::raw('AVG(oxigeno_disuelto) as oxigeno'),
//                 DB::raw('AVG(ion_nitrato) as nitrato')
//             )
//             ->whereMonth('fecha_medicion', $fecha->month)
//             ->whereYear('fecha_medicion', $fecha->year)
//             ->groupBy(DB::raw('DATE(fecha_medicion)'))
//             ->orderBy('fecha')
//             ->get()
//             ->map(function($item) {
//                 $fecha = Carbon::parse($item->fecha);
//                 return [
//                     'fecha' => $fecha->format('d M Y'),
//                     'tooltip' => $fecha->isoFormat('dddd, D MMM YYYY'),
//                     'temperatura' => (float) $item->temperatura,
//                     'ph' => (float) $item->ph,
//                     'oxigeno' => (float) $item->oxigeno,
//                     'nitrato' => (float) $item->nitrato,
//                 ];
//             });

//     } else { // año
//         // Promedio por mes del año actual
//         $fecha = Carbon::now();
//         $data = $query->select(
//                 DB::raw('MONTH(fecha_medicion) as mes'),
//                 DB::raw('AVG(temperatura) as temperatura'),
//                 DB::raw('AVG(ph) as ph'),
//                 DB::raw('AVG(oxigeno_disuelto) as oxigeno'),
//                 DB::raw('AVG(ion_nitrato) as nitrato')
//             )
//             ->whereYear('fecha_medicion', $fecha->year)
//             ->groupBy(DB::raw('MONTH(fecha_medicion)'))
//             ->orderBy('mes')
//             ->get()
//             ->map(function($item) use ($fecha) {
//                 $f = Carbon::createFromDate($fecha->year, $item->mes, 1);
//                 return [
//                     'fecha' => $f->format('M Y'),
//                     'tooltip' => $f->isoFormat('MMMM YYYY'),
//                     'temperatura' => (float) $item->temperatura,
//                     'ph' => (float) $item->ph,
//                     'oxigeno' => (float) $item->oxigeno,
//                     'nitrato' => (float) $item->nitrato,
//                 ];
//             });
//     }

//     return response()->json([
//         'data' => $data
//     ]);
// }
}
