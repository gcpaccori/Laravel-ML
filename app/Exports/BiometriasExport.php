<?php

namespace App\Exports;

use App\Models\Biometria;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BiometriasExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithCustomCsvSettings
{
    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Biometria::with([
                'campaniaEtapa.piscina',
                'campaniaEtapa.etapa',
                'campaniaEtapa.campaniaEspecie.especie',
                'campaniaEtapa.campaniaEspecie.campania.piscigranja'
            ]);

        // Aplicar filtros dinámicos (ajusta según tus nombres de campos)
        if (!empty($this->filters['piscigranja_id'])) {
            $query->whereHas('campaniaEtapa.campaniaEspecie.campania', function ($q) {
                $q->where('piscigranja_id', $this->filters['piscigranja_id']);
            });
        }

        if (!empty($this->filters['campania_id'])) {
            $query->whereHas('campaniaEtapa.campaniaEspecie', function ($q) {
                $q->where('campania_id', $this->filters['campania_id']);
            });
        }

        if (!empty($this->filters['especie_id'])) {
            $query->whereHas('campaniaEtapa.campaniaEspecie', function ($q) {
                $q->where('especie_id', $this->filters['especie_id']);
            });
        }

        if (!empty($this->filters['rango_fecha']) && is_array($this->filters['rango_fecha']) && count($this->filters['rango_fecha']) === 2) {
            $query->whereBetween('fecha_muestreo', [$this->filters['rango_fecha'][0], $this->filters['rango_fecha'][1]]);
        }

        return $query->orderByDesc('fecha_muestreo');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Piscigranja',
            'Campaña',
            'Especie',
            'Etapa',
            'Piscina',
            'Fecha Inicial',
            'Fecha Muestreo',
            'Muestras (Cant.)',
            'Muestreo (%)',
            'Días',
            'Peces Inic.',
            'Peces Act.',
            'Biomasa Inic. (kg)',
            'Biomasa Fin. (kg)',
            'Long. Prom. (cm)',
            'Peso Prom. (g)',
            'Crecimiento (g/día)',
            'Alimento Consumido (kg)',
            'FCA',
            'Supervivencia (%)',
            'Observaciones'
        ];
    }

    public function map($row): array
    {
        return [
            $row->id,
            $row->nombre_piscigranja,
            $row->nombre_campania,
            $row->nombre_especie,
            $row->nombre_etapa,
            $row->nombre_piscina,
            $row->fecha_inicial?->format('Y-m-d') ?? '-',
            $row->fecha_muestreo?->format('Y-m-d') ?? '-',
            $row->cantidad_muestreo ?? 0,
            $row->muestreo_porcentaje,
            $row->tiempo_dias,
            $row->cantidad_peces_iniciales,
            $row->cantidad_peces_actuales,
            $row->bi_kg,
            $row->bf_kg,
            $row->prom_longitud_cm,
            $row->prom_peso_g,
            $row->tasa_crecimiento_g_dia,
            $row->total_alimento_consumido_kg,
            $row->conversion_alimenticia,
            $row->tasa_supervivencia_porcentaje ?? 0,
            $row->observaciones ?? ''
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

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
