<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $tabla['titulo'] ?? 'Tabla de Alimentación BFT' }}</title>
    <style>
        @page {
            margin: 15px 20px;
        }
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #222;
        }
        h1 {
            font-size: 13px;
            text-align: center;
            margin: 0 0 2px 0;
            color: rgb(8, 8, 8)864;
            text-transform: uppercase;
        }
        .subtitle {
            text-align: center;
            font-size: 9.5px;
            color: #555;
            margin-bottom: 8px;
        }

        .info-box-wrapper {
            width: 100%;
            margin-bottom: 10px;
            background-color: #f8f9fa;
            border: 1px solid #d1d5db;
            border-radius: 4px;
        }
        table.info-box-table {
            width: 100%;
            border-collapse: collapse;
        }
        table.info-box-table td {
            border: none;
            padding: 5px 6px;
            text-align: center;
            font-size: 8.5px;
            width: 20%;
            border-right: 1px solid #e5e7eb;
        }
        table.info-box-table td:last-child {
            border-right: none;
        }
        table.info-box-table td strong {
            color: #1F3864;
            display: block;
            margin-bottom: 2px;
            font-size: 8px;
            text-transform: uppercase;
        }

        table.datos {
            width: 100%;
            border-collapse: collapse;
        }
        table.datos th,
        table.datos td {
            border: 1px solid #888;
            padding: 3px 2px;
            text-align: center;
        }
        table.datos thead th {
            background: #1F3864;
            color: #fff;
            font-size: 8px;
            font-weight: bold;
        }
        table.datos tbody td {
            font-size: 8px;
        }
        td.mes-header {
            background: #dbe4f3;
            font-weight: bold;
            color: #1F3864;
        }
        .footer {
            margin-top: 12px;
            font-size: 7.5px;
            color: #777;
            text-align: right;
        }
    </style>
</head>
<body>

    <h1>TABLA DE ALIMENTACIÓN CALCULADO BFT</h1>
    @if(!empty($titleTabla))
        <h1 style="margin-bottom: 10px" >{{ $titleTabla }}</h1>
    @endif

    @if(!empty($tabla['responsable']))
        <div class="subtitle"><strong>Responsable:</strong> {{ $tabla['responsable'] }}</div>
    @endif

    <div class="info-box-wrapper">
        <table class="info-box-table">
            <tr>
                <td>
                    <strong>Población Inicial</strong>
                    {{ number_format($tabla['poblacion_inicial']) }} peces
                </td>
                <td>
                    <strong>Mortalidad</strong>
                    {{ $tabla['mortalidad_porcentaje'] }}%
                </td>
                <td>
                    <strong>N&deg; Semanas</strong>
                    {{ $tabla['numero_semanas'] }}
                </td>
                <td>
                    <strong>Semanas / Mes</strong>
                    {{ $tabla['semanas_por_mes'] }}
                </td>
            </tr>
            @if(!empty($tabla['observaciones']))
                <tr>
                    <td colspan="4" style="text-align: left; background-color: #ffffff; border-top: 1px solid #d1d5db; padding: 4px 8px;">
                        <strong>Observaciones:</strong> {{ $tabla['observaciones'] }}
                    </td>
                </tr>
            @endif
        </table>
    </div>

    <table class="datos">
        <thead>
            <tr>
                <th rowspan="2">Mes</th>
                <th rowspan="2">Sem.</th>
                <th rowspan="2">Ganancia<br>peso (g)</th>
                <th rowspan="2">Población</th>
                <th rowspan="2">Biomasa<br>(Kg)</th>
                <th rowspan="2">T.A<br>(%)</th>
                <th rowspan="2">Consumo<br>diario (Kg)</th>
                <th rowspan="2">Consumo<br>semanal (Kg)</th>
                <th rowspan="2">Tipo de<br>alimento</th>
                <th rowspan="2">Consumo<br>mensual (Kg)</th>
                <th colspan="{{ count($horarios) }}">Frecuencia de alimentación diaria (g)</th>
            </tr>
            <tr>
                @foreach($horarios as $horario)
                    <th>{{ $horario['hora'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach($meses as $mes)
                @php $totalSemanas = count($mes['semanas']); @endphp
                @foreach($mes['semanas'] as $i => $semana)
                    <tr>
                        @if($i === 0)
                            <td class="mes-header" rowspan="{{ $totalSemanas }}">
                                Mes {{ str_pad($mes['numero_mes'], 2, '0', STR_PAD_LEFT) }}
                            </td>
                        @endif

                        <td>{{ $semana['numero_semana'] }}</td>
                        <td>{{ $semana['ganancia_peso_g'] }}</td>
                        <td>{{ number_format($semana['poblacion']) }}</td>
                        <td>{{ $semana['biomasa_kg'] }}</td>
                        <td>{{ $semana['tasa_alimentacion_porcentaje'] }}</td>
                        <td>{{ $semana['consumo_diario_kg'] }}</td>
                        <td>{{ $semana['consumo_semanal_kg'] }}</td>

                        @if($i === 0)
                            <td rowspan="{{ $totalSemanas }}">{{ $mes['tipo_alimento'] ?? '-' }}</td>
                            <td rowspan="{{ $totalSemanas }}">{{ $mes['consumo_mensual_kg'] }}</td>
                        @endif

                        @foreach($semana['frecuencias'] as $frecuencia)
                            <td>{{ $frecuencia['gramos'] }}</td>
                        @endforeach
                    </tr>
                @endforeach
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        Generado el {{ now()->format('d/m/Y H:i') }} &mdash; SISMAPISCIS
    </div>

</body>
</html>
