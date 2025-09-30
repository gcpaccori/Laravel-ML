<?php

namespace App\Exports;

use App\Models\ParametroAgua;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

class ParametroAguaExport implements FromQuery, WithHeadings, WithMapping, WithCustomCsvSettings
{
    protected $request;

    public function __construct($request)
    {
        $this->request = $request;
    }

    public function query()
    {
        $query = ParametroAgua::with(['piscina.piscigranja'])
            ->orderBy('fecha_medicion', 'desc');

        // 🔹 Aplicar filtros como en DataTable
        $piscigranjaId = $this->request->piscigranja_id;
        $piscinaId = $this->request->piscina_id;
        $tipoTiempo = $this->request->tipo_tiempo ?? 'D';

        if ($piscigranjaId && $piscigranjaId !== 'T') {
            $query->whereHas('piscina', function ($q) use ($piscigranjaId) {
                $q->where('piscigranja_id', $piscigranjaId);
            });
        }

        if ($piscinaId && $piscinaId !== 'T') {
            $query->where('piscina_id', $piscinaId);
        }

        if ($tipoTiempo === 'D' && $this->request->filled('fecha')) {
            $query->whereDate('fecha_medicion', $this->request->fecha);
        } elseif ($tipoTiempo === 'M' && $this->request->filled('mes')) {
            $mes = $this->request->mes;
            $query->whereYear('fecha_medicion', substr($mes, 0, 4))
                  ->whereMonth('fecha_medicion', substr($mes, 5, 2));
        } elseif ($tipoTiempo === 'Y' && $this->request->filled('anio')) {
            $query->whereYear('fecha_medicion', $this->request->anio);
        }

        return $query;
    }

    public function headings(): array
    {
        return [
            'Código',
            'Piscigranja',
            'Piscina',
            'Temperatura (°C)',
            'pH',
            'Oxígeno Disuelto (mg/L)',
            'Ion Nitrato (mg/L)',
            'Fecha Medición',
            'Fecha Creación',
        ];
    }

    public function map($s): array
    {
        return [
            $s->id,
            $s->piscina?->piscigranja?->nombre ?? '-',
            $s->piscina?->nombre ?? '-',
            $s->temperatura,
            $s->ph,
            $s->oxigeno_disuelto,
            $s->ion_nitrato,
            $s->fecha_medicion ? $s->fecha_medicion->format('d/m/Y H:i:s') : '-',
            $s->created_at ? $s->created_at->format('d/m/Y H:i:s') : '-',
        ];
    }

    // 🔹 Configuración CSV con BOM
    public function getCsvSettings(): array
    {
        return [
            'delimiter' => ',',
            'enclosure' => '"',
            'line_ending' => PHP_EOL,
            'use_bom' => true, // Esto agrega el BOM para UTF-8
        ];
    }
}
