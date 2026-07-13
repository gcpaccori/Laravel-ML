<?php

namespace App\Exports;

use App\Http\Controllers\BiometriaController;
use App\Models\Biometria;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class BiometriaExport implements WithMultipleSheets
{
    public function __construct(private Biometria $biometria) {}

    public function sheets(): array
    {
        return [
            'Resumen'    => new BiometriaResumenSheet($this->biometria),
            'Detalles'   => new BiometriaDetallesSheet($this->biometria),
            'Dist. Peso' => new BiometriaDistribucionSheet($this->biometria, 'peso'),
            'Dist. Long' => new BiometriaDistribucionSheet($this->biometria, 'longitud'),
        ];
    }
}

class BiometriaResumenSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private Biometria $biometria) {}

    public function title(): string
    {
        return 'Resumen';
    }

    public function headings(): array
    {
        return ['Campo', 'Valor'];
    }

    public function array(): array
    {
        $b = $this->biometria;
        $ce = $b->campaniaEtapa;

        return [
            ['Piscigranja', $b->nombre_piscigranja],
            ['Campaña', $b->nombre_campania],
            ['Especie', $b->nombre_especie],
            ['Etapa', $b->nombre_etapa],
            ['Piscina', $b->nombre_piscina],
            ['Fecha inicial', optional($b->fecha_inicial)->format('d/m/Y')],
            ['Fecha de muestreo', optional($b->fecha_muestreo)->format('d/m/Y')],
            ['Tiempo transcurrido (días)', $b->tiempo_dias],
            ['Cantidad muestreada', $b->cantidad_muestreo],
            ['% de muestreo', $b->muestreo_porcentaje . '%'],
            ['Peces iniciales', $b->cantidad_peces_iniciales],
            ['Peces actuales', $b->cantidad_peces_actuales],
            ['Tasa de supervivencia', $b->tasa_supervivencia_porcentaje . '%'],
            ['Biomasa inicial (kg)', $b->bi_kg],
            ['Biomasa final (kg)', $b->bf_kg],
            ['Alimento consumido (kg)', $b->total_alimento_consumido_kg],
            ['Conversión alimenticia (FCA)', $b->conversion_alimenticia],
            ['Peso promedio (g)', $b->prom_peso_g],
            ['Longitud promedio (cm)', $b->prom_longitud_cm],
            ['Tasa de crecimiento (g/día)', $b->tasa_crecimiento_g_dia],
            ['Observaciones', $b->observaciones],
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

class BiometriaDetallesSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private Biometria $biometria) {}

    public function title(): string
    {
        return 'Detalles';
    }

    public function headings(): array
    {
        return ['N°', 'Peso (g)', 'Longitud (cm)'];
    }

    public function array(): array
    {
        return $this->biometria->detalles
            ->map(fn ($d) => [$d->numero, $d->peso_g, $d->longitud_cm])
            ->toArray();
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}

class BiometriaDistribucionSheet implements FromArray, WithHeadings, WithStyles, WithTitle
{
    public function __construct(private Biometria $biometria, private string $tipo) {}

    public function title(): string
    {
        return $this->tipo === 'peso' ? 'Dist. Peso' : 'Dist. Longitud';
    }

    public function headings(): array
    {
        return ['Rango', 'Cantidad', 'Porcentaje (%)'];
    }

    public function array(): array
    {
        $valores = $this->tipo === 'peso'
            ? $this->biometria->detalles->pluck('peso_g')->toArray()
            : $this->biometria->detalles->pluck('longitud_cm')->toArray();

        $ancho = $this->tipo === 'peso' ? 10 : 1;

        $distribucion = BiometriaController::calcularDistribucion($valores, $ancho);

        return array_map(
            fn ($fila) => [$fila['rango'], $fila['cantidad'], $fila['porcentaje']],
            $distribucion
        );
    }

    public function styles(Worksheet $sheet)
    {
        return [1 => ['font' => ['bold' => true]]];
    }
}
