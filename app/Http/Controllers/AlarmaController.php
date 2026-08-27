<?php

namespace App\Http\Controllers;

use App\DataTables\AlarmaDataTable;
use App\Helpers\DataTableHelper;
use App\Models\Alarma;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AlarmaController extends Controller
{
    public function index(Request $request)
    {
        $datatable = new AlarmaDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Modules/Views/Alarmas/Index', [
            'filtros' => [
                'search' => $request->input('search'),
                'estado' => $request->input('estado'),
                'nivel' => $request->input('nivel'),
                'modulo' => $request->input('modulo'),
                'piscigranja_id' => $request->input('piscigranja_id'),
            ],
            'title' => 'Historial de Alertas',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(AlarmaDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    public function show(Alarma $alarma)
    {
        $alarma->load([
            'piscigranja:id,nombre',
            'piscina:id,nombre',
            'resueltaPor:id,name',
        ]);

        return Inertia::render('Modules/Views/Alarmas/Show', [
            'title' => 'Detalle de Alerta',
            'toolbar' => [
                ['label' => 'Alertas', 'route' => 'alarmas.index'],
                ['label' => 'Información completa de la alarma'],
            ],
            'alarma' => [
                'id' => $alarma->id,
                'titulo' => $alarma->titulo,
                'mensaje' => $alarma->mensaje,
                'modulo' => $alarma->modulo,
                'parametro' => $alarma->parametro,
                'nivel' => $alarma->nivel,
                'nivel_info' => Alarma::getNivel($alarma->nivel),
                'valor_detectado' => $alarma->valor_detectado,
                'estado' => $alarma->estado,
                'estado_info' => Alarma::getEstado($alarma->estado),
                'piscigranja' => $alarma->piscigranja,
                'piscina' => $alarma->piscina,
                'resuelta_por' => $alarma->resueltaPor,
                'resuelta_en' => $alarma->resuelta_en?->format('d/m/Y H:i'),
                'created_at' => $alarma->created_at?->format('d/m/Y H:i'),
            ],
        ]);
    }

    public function resolver(Alarma $alarma)
    {
        $alarma->update([
            'estado' => 'resuelta',
            'resuelta_en' => now(),
            'resuelta_por' => auth()->id(),
        ]);

        return response()->json([
            'message' => 'La alarma ha sido marcada como resuelta.',
            'success' => true
        ]);
    }

    public function alarmasDropdown(Request $request)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'alarmas' => [],
                'alarmas_activas' => 0,
            ]);
        }

        $alarmas = Alarma::with([
                'piscigranja:id,nombre',
                'piscina:id,nombre',
            ])
            ->where('estado', '!=', 'resuelta')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($alarma) => [
                'id' => $alarma->id,
                'titulo' => $alarma->titulo,
                'mensaje' => $alarma->mensaje,
                'nivel' => $alarma->nivel,
                'estado' => $alarma->estado,
                'modulo' => $alarma->modulo,
                'parametro' => $alarma->parametro,
                'valor_detectado' => $alarma->valor_detectado,
                'piscigranja' => $alarma->piscigranja?->nombre,
                'piscina' => $alarma->piscina?->nombre,
                'created_at' => $alarma->created_at?->diffForHumans(),
            ]);

        $alarmasActivas = Alarma::where('estado', 'activa')->count();

        return response()->json([
            'alarmas' => $alarmas,
            'alarmas_activas' => $alarmasActivas,
        ]);
    }

    public function alarmaStatistics(Request $request)
    {
        // $query = Alarma::query()
        //     ->with([
        //         'piscigranja:id,nombre',
        //         'piscina:id,nombre',
        //         'reconocidaPor:id,name',
        //     ])
        //     ->latest();

        // if ($request->filled('search')) {
        //     $search = $request->input('search');

        //     $query->where(function ($q) use ($search) {
        //         $q->where('titulo', 'like', "%{$search}%")
        //             ->orWhere('mensaje', 'like', "%{$search}%")
        //             ->orWhere('parametro', 'like', "%{$search}%")
        //             ->orWhere('modulo', 'like', "%{$search}%");
        //     });
        // }

        // if ($request->filled('estado')) {
        //     $query->where('estado', $request->input('estado'));
        // }

        // if ($request->filled('nivel')) {
        //     $query->where('nivel', $request->input('nivel'));
        // }

        // if ($request->filled('modulo')) {
        //     $query->where('modulo', $request->input('modulo'));
        // }

        // if ($request->filled('piscigranja_id')) {
        //     $query->where(
        //         'piscigranja_id',
        //         $request->input('piscigranja_id')
        //     );
        // }

        // $alarmas = $query
        //     ->paginate(15)
        //     ->withQueryString();


        // $estadisticas = [
        //     'total' => Alarma::count(),
        //     'activas' => Alarma::where('estado', 'activa')->count(),
        //     'reconocidas' => Alarma::where('estado', 'reconocida')->count(),
        //     'resueltas' => Alarma::where('estado', 'resuelta')->count(),
        //     'criticas' => Alarma::whereIn('nivel', [
        //         'critico',
        //         'emergencia',
        //     ])->where('estado', '!=', 'resuelta')->count(),
        // ];

        $estadisticas = Alarma::selectRaw('
            count(*) as total,
            sum(case when estado = ? then 1 else 0 end) as activas,
            sum(case when estado = ? then 1 else 0 end) as resueltas,
            sum(case when nivel in (?, ?) and estado != ? then 1 else 0 end) as criticas',
            ['activa', 'resuelta', 'critico', 'emergencia', 'resuelta'])->first()->toArray();

        return response()->json([
            // 'alarmas' => $alarmas,
            'estadisticas' => $estadisticas,
        ]);
    }
}
