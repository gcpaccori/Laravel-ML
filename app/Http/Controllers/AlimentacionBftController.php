<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAlimentacionBftRequest;
use App\Models\AlimentacionTabla;
use App\Models\CampaniaEspecie;
use App\Services\AlimentacionBftService;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AlimentacionBftController extends Controller
{
    public function __construct(private readonly AlimentacionBftService $service)
    {
    }

    public function index()
    {
        return Inertia::render('Modules/Views/AlimentacionTabla', [
            'title' => 'Gestionar Biometrias',
            'toolbar' => [
                ['label' => 'Inicio', 'route' => 'dashboard']
            ]
        ]);
    }

    /**
     * Muestra el formulario (si aún no existe tabla) o la tabla ya
     * calculada para esa campaña_especie.
     */
    public function show(CampaniaEspecie $campaniaEspecie): Response
    {
        $tabla = AlimentacionTabla::with(['horarios', 'meses.semanas'])
            ->where('campania_especie_id', $campaniaEspecie->id)
            ->first();

        return Inertia::render('Modules/Form/AlimentacionTabla', [
            'campaniaEspecie' => $campaniaEspecie->only(['id', 'campania_id', 'especie_id']),
            'tabla' => $tabla ? $this->service->tablaParaVista($tabla) : null,
        ]);
    }

    /**
     * Recibe el formulario (parámetros base + semanas + meses + horarios),
     * dispara el cálculo completo y persiste todo en una transacción.
     */
    public function store(StoreAlimentacionBftRequest $request, CampaniaEspecie $campaniaEspecie): HttpResponse
    {
        $data = $request->validated();

        $tabla = AlimentacionTabla::updateOrCreate(
            ['campania_especie_id' => $campaniaEspecie->id],
            [
                'titulo' => $data['titulo'] ?? null,
                'responsable' => $data['responsable'] ?? null,
                'poblacion_inicial' => $data['poblacion_inicial'],
                'mortalidad_porcentaje' => $data['mortalidad_porcentaje'],
                'numero_semanas' => $data['numero_semanas'],
                'semanas_por_mes' => $data['semanas_por_mes'],
                'observaciones' => $data['observaciones'] ?? null,
            ]
        );

        $this->service->generar($tabla, $data['horarios'], $data['semanas'], $data['meses']);

        return redirect()
            ->route('campana-especie.alimentacion-bft.show', $campaniaEspecie)
            ->with('success', 'Tabla de alimentación BFT generada correctamente.');
    }

    /**
     * Genera y descarga el PDF de la tabla ya calculada.
     */
    public function pdf(CampaniaEspecie $campaniaEspecie): HttpResponse
    {
        $tabla = AlimentacionTabla::with(['horarios', 'meses.semanas'])
            ->where('campania_especie_id', $campaniaEspecie->id)
            ->firstOrFail();

        $datos = $this->service->tablaParaVista($tabla);

        $pdf = Pdf::loadView('exports.alimentacion-bft', $datos)
            ->setPaper('a4', 'landscape');

        return $pdf->download("tabla-alimentacion-bft-campana-especie-{$campaniaEspecie->id}.pdf");
    }
}
