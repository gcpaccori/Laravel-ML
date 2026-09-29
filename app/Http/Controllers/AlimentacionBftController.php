<?php

namespace App\Http\Controllers;

use App\DataTables\CampaniaEspecieDataTable;
use App\Exports\AlimentacionExport;
use App\Helpers\DataTableHelper;
use App\Http\Requests\StoreAlimentacionBftRequest;
use App\Models\AlimentacionTabla;
use App\Models\CampaniaEspecie;
use App\Services\AlimentacionBftService;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class AlimentacionBftController extends Controller
{
    public function __construct(private readonly AlimentacionBftService $service)
    {
    }

    public function index()
    {
        $datatable = new CampaniaEspecieDataTable();
        $columns = DataTableHelper::getColumnsFromDatatable($datatable);

        return Inertia::render('Modules/Views/AlimentacionTabla', [
            'title' => 'Gestionar Tablas de Alimentación',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Tablas de Alimentación']
            ],
            'columns' => $columns,
            'accionesGrilla' => DataTableHelper::getAccionesPermitidasEnMarco()
        ]);
    }

    public function datatable(CampaniaEspecieDataTable $dataTable)
    {
        return $dataTable->ajax();
    }

    public function show(CampaniaEspecie $campaniaEspecie)
    {
        $tabla = AlimentacionTabla::with(['horarios', 'meses.semanas'])
            ->where('campania_especie_id', $campaniaEspecie->id)
            ->first();

        return Inertia::render('Modules/Views/AlimentacionTablaShow', [
            'title' => 'Visualizar Tabla de Alimentación',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Gestionar Tablas de Alimentación', 'route' => 'produccion.alimentacionbfts.index'],
            ],
            'campaniaEspecie' => $campaniaEspecie,
            'tabla' => $tabla ? $this->service->tablaParaVista($tabla) : null,
            'titleTabla' => $this->titleTabla($campaniaEspecie)
        ]);
    }

    public function create(CampaniaEspecie $campaniaEspecie)
    {
        return Inertia::render('Modules/Form/AlimentacionTablaForm', [
            'title' => 'Generar Tabla de Alimentación',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Gestionar Tablas de Alimentación', 'route' => 'produccion.alimentacionbfts.index'],
                ['label' => 'Generar Tabla de Alimentación']
            ],
            'campaniaEspecie' => $campaniaEspecie,
            'titleTabla' => $this->titleTabla($campaniaEspecie)
        ]);
    }

    public function store(StoreAlimentacionBftRequest $request, CampaniaEspecie $campaniaEspecie)
    {
        $data = $request->validated();
        $tabla = AlimentacionTabla::updateOrCreate(
            ['campania_especie_id' => $campaniaEspecie->id],
            [
                'poblacion_inicial' => $data['poblacion_inicial'],
                'mortalidad_porcentaje' => $data['mortalidad_porcentaje'],
                'numero_semanas' => $data['numero_semanas'],
                'semanas_por_mes' => $data['semanas_por_mes'],
                'observaciones' => $data['observaciones'] ?? null,
            ]
        );

        $reg = $this->service->generar($tabla, $data['horarios'], $data['semanas'], $data['meses']);
        return response()->json([
            'message' => 'Tabla de alimentación BFT generada correctamente.',
            'data' => $reg,
        ]);
    }

    public function edit( CampaniaEspecie $campaniaEspecie )
    {
        $tabla = AlimentacionTabla::with(['horarios', 'meses.semanas'])
            ->where('campania_especie_id', $campaniaEspecie->id)
            ->first();

        return Inertia::render('Modules/Form/AlimentacionTablaForm', [
            'title' => 'Actualizar Tabla de Alimentación',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Gestionar Tablas de Alimentación', 'route' => 'produccion.alimentacionbfts.index'],
            ],
            'campaniaEspecie' => $campaniaEspecie,
            'dataForm' => $tabla ? $this->service->tablaParaVista($tabla) : null,
            'titleTabla' => $this->titleTabla($campaniaEspecie)
        ]);
    }

    public function pdf(CampaniaEspecie $campaniaEspecie)
    {
        $tabla = AlimentacionTabla::with(['horarios', 'meses.semanas'])
            ->where('campania_especie_id', $campaniaEspecie->id)
            ->firstOrFail();

        $datos = $this->service->tablaParaVista($tabla);
        $datos['titleTabla'] = $this->titleTabla($campaniaEspecie);

        $pdf = Pdf::loadView('exports.alimentacion-bft', $datos)
            ->setPaper('a4', 'landscape');

        // return $pdf->stream("tabla-alimentacion-bft-campana-especie-{$campaniaEspecie->id}.pdf");
        return $pdf->download("tabla-alimentacion-bft-campana-especie-{$campaniaEspecie->id}.pdf");
    }

    public function excel(CampaniaEspecie $campaniaEspecie)
    {
        $tabla = AlimentacionTabla::with(['horarios', 'meses.semanas'])
            ->where('campania_especie_id', $campaniaEspecie->id)
            ->firstOrFail();

        $datos = $this->service->tablaParaVista($tabla);
        $datos['titleTabla'] = $this->titleTabla($campaniaEspecie);

        return Excel::download(
            new AlimentacionExport($datos),
            "tabla-alimentacion-bft-campana-especie-{$campaniaEspecie->id}.xlsx"
        );
    }

    public function titleTabla( CampaniaEspecie $campaniaEspecie )
    {
        $piscigranja = $campaniaEspecie->campania->piscigranja->nombre ?? '-';
        $campania = $campaniaEspecie->campania->nombre ?? '-';
        $especie = $campaniaEspecie->especie->nombre ?? '-';
        return "{$piscigranja} • {$campania} • {$especie}";
    }
}
