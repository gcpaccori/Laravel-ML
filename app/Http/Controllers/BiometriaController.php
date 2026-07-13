<?php

namespace App\Http\Controllers;

use App\DataTables\BiometriaDataTable;
use App\Exports\BiometriaExport;
use App\Helpers\DataTableHelper;
use App\Http\Requests\BiometriaRequest;
use App\Models\Biometria;
use App\Models\Campania;
use App\Models\CampaniaEspecie;
use App\Models\CampaniaEtapa;
use App\Models\ParametrosProduccion;
use App\Services\BiometriaCalculoService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;

class BiometriaController extends Controller
{
    public $anchoLongitud;
    public $anchoPeso;

    public function __construct( private BiometriaCalculoService $calculoService) {
        $this->anchoLongitud = 2;
        $this->anchoPeso     = 5;
    }

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

    public function show( Biometria  $id )
    {
        $id->load([
            'detalles',
            'campaniaEtapa.etapa',
            'campaniaEtapa.piscina',
            'campaniaEtapa.campaniaEspecie.especie',
            'campaniaEtapa.campaniaEspecie.campania.piscigranja',
        ]);

        return Inertia::render('Modules/Views/BiometriaShow', [
            'title' => 'Visualizar Biométria',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Gestionar Biométrias', 'route' => 'produccion.biometrias.index'],
                ['label' => 'Visualizar Biométria']
            ],
            'biometria' => $id,
            'anchoLongitud' => $this->anchoLongitud,
            'anchoPeso' => $this->anchoPeso,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(BiometriaRequest $request)
    {

        $validated = $request->all();

        $reg = DB::transaction(function () use ($validated) {
            $campaniaEtapa = CampaniaEtapa::with('campaniaEspecie')->findOrFail($validated['campania_etapa_id']);

            // Última biometría de la etapa = "anterior" para el nuevo registro
            $ultimaBiometria = Biometria::where('campania_etapa_id', $validated['campania_etapa_id'])
                ->orderByDesc('fecha_muestreo')->orderByDesc('id')->first();

            // Regla de negocio: la nueva fecha_muestreo debe ser posterior a la última
            if ($ultimaBiometria && $validated['fecha_muestreo'] <= $ultimaBiometria->fecha_muestreo->toDateString()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La fecha de muestreo debe ser posterior al último registro de esta etapa.',
                ]);
            }

            $calculados = $this->calculoService->calcular($validated, $ultimaBiometria, $campaniaEtapa);

            $biometria = Biometria::create([
                'campania_etapa_id'           => $validated['campania_etapa_id'],
                'fecha_muestreo'              => $validated['fecha_muestreo'],
                'total_alimento_consumido_kg' => $validated['total_alimento_consumido_kg'],
                'cantidad_peces_actuales'     => $validated['cantidad_peces_actuales'],
                'observaciones'               => $validated['observaciones'] ?? null,
                ...$calculados,
            ]);

            $biometria->detalles()->createMany($validated['detalles']);

            return $biometria->fresh()->load('detalles');
        });


        return response()->json([
            'message' => 'Biometría registrada con éxito',
            'data' => $reg,
        ]);
    }

    public function create()
    {
        return Inertia::render('Modules/Form/BiometriaForm', [
            'title' => 'Registrar Biométrias',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Gestionar Biométrias', 'route' => 'produccion.biometrias.index'],
                ['label' => 'Registrar Biométrias']
            ],
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit( string $id )
    {
        $biometria = Biometria::with(['detalles','campaniaEtapa'])->findOrFail($id);
        return Inertia::render('Modules/Form/BiometriaForm', [
            'title' => 'Editar Biométrias',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard'],
                ['label' => 'Gestionar Biométrias', 'route' => 'produccion.biometrias.index'],
                ['label' => 'Editar Biométrias']
            ],
            'dataForm' => $biometria
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(BiometriaRequest $request, Biometria $id)
    {
        // Regla clave: solo se edita si es el último registro de la cadena
        if (!$id->esUltimoRegistro()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede editar este registro porque existen biometrías posteriores en la etapa.',
            ]);
        }

        $validated = $request->all();

        $reg = DB::transaction(function () use ($validated, $id) {

            $campaniaEtapa = $id->campaniaEtapa()->with('campaniaEspecie')->first();
            $anterior      = $id->anterior(); // sigue siendo el mismo anterior, no cambia

            // Si cambia fecha_muestreo, validar que siga siendo posterior al anterior
            if ($anterior && $validated['fecha_muestreo'] <= $anterior->fecha_muestreo->toDateString()) {
                return response()->json([
                    'success' => false,
                    'message' => 'La fecha de muestreo debe ser posterior al registro anterior.',
                ]);
            }

            $calculados = $this->calculoService->calcular($validated, $anterior, $campaniaEtapa);

            $id->update([
                'fecha_muestreo'              => $validated['fecha_muestreo'],
                'total_alimento_consumido_kg' => $validated['total_alimento_consumido_kg'],
                'cantidad_peces_actuales'     => $validated['cantidad_peces_actuales'],
                'observaciones'               => $validated['observaciones'] ?? null,
                ...$calculados,
            ]);

            $id->detalles()->delete();
            $id->detalles()->createMany($validated['detalles']);

            return $id->fresh()->load('detalles');
        });

        return response()->json([
            'message' => 'Biometría actualizada con éxito',
            'data' => $reg,
        ]);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Biometria $id)
    {

        if (!$id->esUltimoRegistro()) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar este registro porque existen biometrías posteriores en la etapa.',
            ]);
        }

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
        $etapa = ParametrosProduccion::with('campaniaEtapa.campaniaEspecie')->where('campania_etapa_id', $campania_etapa_id)->first();
        return $etapa;
    }

    public function exportarExcel(Biometria $biometria)
    {
        $biometria->load([
            'detalles',
            'campaniaEtapa.etapa',
            'campaniaEtapa.piscina',
            'campaniaEtapa.campaniaEspecie.especie',
            'campaniaEtapa.campaniaEspecie.campania.piscigranja',
        ]);

        $nombreArchivo = "biometria-{$biometria->id}-" . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(new BiometriaExport($biometria), $nombreArchivo);
    }


    public function exportarPdf(Biometria $biometria)
    {
        $biometria->load([
            'detalles',
            'campaniaEtapa.etapa',
            'campaniaEtapa.piscina',
            'campaniaEtapa.campaniaEspecie.especie',
            'campaniaEtapa.campaniaEspecie.campania.piscigranja',
        ]);

        $distribucionLongitud = self::calcularDistribucion(
            $biometria->detalles->pluck('longitud_cm')->toArray(),
            $this->anchoLongitud
        );

        $distribucionPeso = self::calcularDistribucion(
            $biometria->detalles->pluck('peso_g')->toArray(),
            $this->anchoPeso
        );

        $pdf = Pdf::loadView('exports.biometria-pdf', [
            'biometria'            => $biometria,
            'campaniaEspecie'      => $biometria->campaniaEtapa->campaniaEspecie,
            'distribucionLongitud' => $distribucionLongitud,
            'distribucionPeso'     => $distribucionPeso,
        ])->setPaper('a4', 'portrait');

        $nombreArchivo = "biometria-{$biometria->id}-" . now()->format('Ymd_His') . '.pdf';

        return $pdf->download($nombreArchivo);
    }

    public static function calcularDistribucion(array $valores, float $ancho): array
    {
        $datos = array_values(array_filter($valores, fn ($v) => $v !== null));

        if (empty($datos)) {
            return [];
        }

        $min = floor(min($datos) / $ancho) * $ancho;
        $max = ceil(max($datos) / $ancho) * $ancho;

        $bins = [];
        for ($inicio = $min; $inicio < $max; $inicio += $ancho) {
            $fin = round($inicio + $ancho, 4);
            $cantidad = count(array_filter($datos, fn ($v) => $v >= $inicio && $v < $fin));

            $bins[] = [
                'rango'      => sprintf('≥%s a <%s', self::formatearNum($inicio), self::formatearNum($fin)),
                'cantidad'   => $cantidad,
                'porcentaje' => round(($cantidad / count($datos)) * 100, 2),
            ];
        }

        return $bins;
    }

    private static function formatearNum(float $n): string
    {
        return floor($n) == $n ? (string) intval($n) : (string) round($n, 2);
    }

}
