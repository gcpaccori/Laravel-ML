<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class AlimentacionExport implements WithEvents, WithTitle
{
    protected array $datos;

    public function __construct(array $datos)
    {
        $this->datos = $datos;
    }

    public function title(): string
    {
        return 'Tabla Alimentación BFT';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $tabla = $this->datos['tabla'] ?? [];
                $horarios = $this->datos['horarios'] ?? [];
                $meses = $this->datos['meses'] ?? [];
                $titleTabla = $this->datos['titleTabla'] ?? '';

                $numHorarios = count($horarios);
                $totalColsCount = 10 + $numHorarios;
                $lastColLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($totalColsCount);

                $row = 1;

                // -----------------------------------------------------------------
                // 1. TÍTULOS PRINCIPALES
                // -----------------------------------------------------------------
                $sheet->setCellValue("A{$row}", 'TABLA DE ALIMENTACIÓN CALCULADO BFT');
                $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
                $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(14);
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $row++;

                if (!empty($titleTabla)) {
                    $sheet->setCellValue("A{$row}", $titleTabla);
                    $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $row++;
                }

                if (!empty($tabla['responsable'])) {
                    $sheet->setCellValue("A{$row}", 'Responsable: ' . $tabla['responsable']);
                    $sheet->mergeCells("A{$row}:{$lastColLetter}{$row}");
                    $sheet->getStyle("A{$row}")->getFont()->setSize(10)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('555555'));
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $row++;
                }

                $row++; // Espacio en blanco

                // -----------------------------------------------------------------
                // 2. CAJA DE INFORMACIÓN
                // -----------------------------------------------------------------
                $infoStartRow = $row;
                $infoBoxes = [
                    ['POBLACIÓN INICIAL', ($tabla['poblacion_inicial'] ?? 0) . ' peces'],
                    ['MORTALIDAD', ($tabla['mortalidad_porcentaje'] ?? 0) . '%'],
                    ['N° SEMANAS', $tabla['numero_semanas'] ?? 0],
                    ['SEMANAS / MES', $tabla['semanas_por_mes'] ?? 0],
                ];

                // Distribuimos las 5 cajas horizontalmente en el total de columnas disponibles
                $colIndex = 1;
                $colsPerBox = max(2, (int) floor($totalColsCount / 5));

                foreach ($infoBoxes as $i => $box) {
                    $startCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($colIndex);

                    // Si es el último cuadro, toma las columnas sobrantes
                    if ($i === 3) {
                        $endColIndex = $totalColsCount;
                    } else {
                        $endColIndex = $colIndex + $colsPerBox - 1;
                    }
                    $endCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($endColIndex);

                    $sheet->setCellValue("{$startCol}{$row}", $box[0]);
                    $sheet->setCellValue("{$startCol}" . ($row + 1), $box[1]);

                    $sheet->mergeCells("{$startCol}{$row}:{$endCol}{$row}");
                    $sheet->mergeCells("{$startCol}" . ($row + 1) . ":{$endCol}" . ($row + 1));

                    $colIndex = $endColIndex + 1;
                }

                $infoEndRow = $row + 1;
                if (!empty($tabla['observaciones'])) {
                    $infoEndRow++;
                    $sheet->setCellValue("A{$infoEndRow}", 'Observaciones: ' . $tabla['observaciones']);
                    $sheet->mergeCells("A{$infoEndRow}:{$lastColLetter}{$infoEndRow}");
                    $sheet->getStyle("A{$infoEndRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }

                // Estilo para la caja de información
                $infoRange = "A{$infoStartRow}:{$lastColLetter}{$infoEndRow}";
                $sheet->getStyle($infoRange)->applyFromArray([
                    'fill' => [
                        'fillType' => Fill::FILL_SOLID,
                        'startColor' => ['rgb' => 'F8F9FA'],
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => 'D1D5DB'],
                        ],
                    ],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                ]);

                // Negrita en las etiquetas superiores de la info box
                for ($c = 1; $c <= $totalColsCount; $c++) {
                    $colName = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($c);
                    $sheet->getStyle("{$colName}{$infoStartRow}")->getFont()->setBold(true)->setSize(9);
                }

                $row = $infoEndRow + 2; // Espacio en blanco antes de la tabla principal

                // -----------------------------------------------------------------
                // 3. ENCABEZADOS DE LA TABLA
                // -----------------------------------------------------------------
                $headerStartRow = $row;
                $headers = [
                    'Mes', 'Sem.', "Ganancia\npeso (g)", 'Población', "Biomasa\n(Kg)",
                    "T.A\n(%)", "Consumo\ndiario (Kg)", "Consumo\nsemanal (Kg)",
                    "Tipo de\nalimento", "Consumo\nmensual (Kg)"
                ];

                foreach ($headers as $index => $text) {
                    $colName = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($index + 1);
                    $sheet->setCellValue("{$colName}{$row}", $text);
                    $sheet->mergeCells("{$colName}{$row}:{$colName}" . ($row + 1));
                }

                // Frecuencia de alimentación diaria (Encabezado superior con colspan)
                $frecStartCol = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(11);
                $frecEndCol = $lastColLetter;

                $sheet->setCellValue("{$frecStartCol}{$row}", 'Frecuencia de alimentación diaria (g)');
                if ($numHorarios > 1) {
                    $sheet->mergeCells("{$frecStartCol}{$row}:{$frecEndCol}{$row}");
                }

                // Sub-encabezados de Horarios
                foreach ($horarios as $hIndex => $horario) {
                    $colName = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(11 + $hIndex);
                    $sheet->setCellValue("{$colName}" . ($row + 1), $horario['hora'] ?? '');
                }

                $headerEndRow = $row + 1;
                $headerRange = "A{$headerStartRow}:{$lastColLetter}{$headerEndRow}";

                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 9],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1F3864']],
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                        'wrapText' => true,
                    ],
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'FFFFFF']],
                    ],
                ]);

                $row = $headerEndRow + 1;

                // -----------------------------------------------------------------
                // 4. CUERPO DE DATOS
                // -----------------------------------------------------------------
                $dataStartRow = $row;

                foreach ($meses as $mes) {
                    $semanas = $mes['semanas'] ?? [];
                    $totalSemanas = count($semanas);
                    $mesStartRow = $row;

                    foreach ($semanas as $i => $semana) {
                        // Mes (Solo en la primera semana del bloque)
                        if ($i === 0) {
                            $mesNum = str_pad($mes['numero_mes'] ?? 1, 2, '0', STR_PAD_LEFT);
                            $sheet->setCellValue("A{$row}", "Mes {$mesNum}");
                        }

                        // Datos numéricos puros para permitir fórmulas en Excel
                        $sheet->setCellValue("B{$row}", $semana['numero_semana'] ?? '');
                        $sheet->setCellValue("C{$row}", $semana['ganancia_peso_g'] ?? 0);
                        $sheet->setCellValue("D{$row}", $semana['poblacion'] ?? 0);
                        $sheet->setCellValue("E{$row}", $semana['biomasa_kg'] ?? 0);
                        $sheet->setCellValue("F{$row}", ($semana['tasa_alimentacion_porcentaje'] ?? 0) / 100); // Para formato %
                        $sheet->setCellValue("G{$row}", $semana['consumo_diario_kg'] ?? 0);
                        $sheet->setCellValue("H{$row}", $semana['consumo_semanal_kg'] ?? 0);

                        // Tipo de Alimento y Consumo Mensual (Agrupados por mes)
                        if ($i === 0) {
                            $sheet->setCellValue("I{$row}", $mes['tipo_alimento'] ?? '-');
                            $sheet->setCellValue("J{$row}", $mes['consumo_mensual_kg'] ?? 0);
                        }

                        // Frecuencias por Horario
                        $frecuencias = $semana['frecuencias'] ?? [];
                        foreach ($frecuencias as $fIndex => $frecuencia) {
                            $colName = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(11 + $fIndex);
                            $sheet->setCellValue("{$colName}{$row}", $frecuencia['gramos'] ?? 0);
                        }

                        $row++;
                    }

                    $mesEndRow = $row - 1;

                    // Aplicar mergeCells agrupando las semanas del mes
                    if ($totalSemanas > 1) {
                        $sheet->mergeCells("A{$mesStartRow}:A{$mesEndRow}");
                        $sheet->mergeCells("I{$mesStartRow}:I{$mesEndRow}");
                        $sheet->mergeCells("J{$mesStartRow}:J{$mesEndRow}");
                    }

                    // Estilo exclusivo para el encabezado lateral de "Mes XX"
                    $sheet->getStyle("A{$mesStartRow}:A{$mesEndRow}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['rgb' => '1F3864']],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBE4F3']],
                    ]);
                }

                $dataEndRow = $row - 1;

                // -----------------------------------------------------------------
                // 5. FORMATO Y BORDES GENERALES
                // -----------------------------------------------------------------
                $dataRange = "A{$dataStartRow}:{$lastColLetter}{$dataEndRow}";

                $sheet->getStyle($dataRange)->applyFromArray([
                    'alignment' => [
                        'horizontal' => Alignment::HORIZONTAL_CENTER,
                        'vertical' => Alignment::VERTICAL_CENTER,
                    ],
                    'borders' => [
                        'allBorders' => [
                            'borderStyle' => Border::BORDER_THIN,
                            'color' => ['rgb' => '888888'],
                        ],
                    ],
                ]);

                // Formatos numéricos nativos de Excel
                $sheet->getStyle("D{$dataStartRow}:D{$dataEndRow}")
                    ->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("F{$dataStartRow}:F{$dataEndRow}")
                    ->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_PERCENTAGE_00);
                $sheet->getStyle("C{$dataStartRow}:C{$dataEndRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("E{$dataStartRow}:H{$dataEndRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');
                $sheet->getStyle("J{$dataStartRow}:J{$dataEndRow}")
                    ->getNumberFormat()->setFormatCode('#,##0.00');

                // -----------------------------------------------------------------
                // 6. AUTO-AJUSTAR ANCHO DE COLUMNAS
                // -----------------------------------------------------------------
                for ($col = 1; $col <= $totalColsCount; $col++) {
                    $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
                    $sheet->getColumnDimension($colLetter)->setAutoSize(true);
                }
            },
        ];
    }
}
