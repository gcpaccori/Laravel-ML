<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ $tabla['titulo'] ?? 'Tabla de Alimentación BFT' }}</title>
    <style>
        /* DomPDF: usar solo CSS soportado (sin flexbox/grid) */
        body {
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 9px;
            color: #222;
        }
        h1 {
            font-size: 14px;
            text-align: center;
            margin: 0 0 2px 0;
        }
        .subtitle {
            text-align: center;
            font-size: 10px;
            color: #555;
            margin-bottom: 10px;
        }
        .info-box table {
            border: none;
            width: auto;
        }
        .info-box td {
            border: none;
            padding: 2px 10px 8px 0;
            text-align: left;
            font-size: 10px;
        }
        table.datos {
            width: 100%;
            border-collapse: collapse;
        }
        table.datos th,
        table.datos td {
            border: 1px solid #999;
            padding: 3px 4px;
            text-align: center;
        }
        table.datos thead th {
            background: #1F3864;
            color: #fff;
            font-size: 8px;
        }
        table.datos tbody td {
            font-size: 8.5px;
        }
        td.mes-header {
            background: #dbe4f3;
            font-weight: bold;
        }
        .footer {
            margin-top: 16px;
            font-size: 8px;
            color: #777;
            text-align: right;
        }
    </style>
</head>
<body>
    <h1>{{ $tabla['titulo'] ?? 'TABLA DE ALIMENTACIÓN CALCULADO BFT' }}</h1>

    @if(!empty($tabla['responsable']))
        <div class="subtitle">{{ $tabla['responsable'] }}</div>
    @endif

    <div class="info-box">
        <table>
            <tr>
                <td><strong>Población inicial:</strong> {{ number_format($tabla['poblacion_inicial']) }}</td>
                <td><strong>Mortalidad:</strong> {{ $tabla['mortalidad_porcentaje'] }}%</td>
                <td><strong>N&deg; de semanas:</strong> {{ $tabla['numero_semanas'] }}</td>
                <td><strong>Semanas por mes:</strong> {{ $tabla['semanas_por_mes'] }}</td>
            </tr>
            @if(!empty($tabla['observaciones']))
                <tr>
                    <td colspan="4"><strong>Observaciones:</strong> {{ $tabla['observaciones'] }}</td>
                </tr>
            @endif
        </table>
    </div>

    <table class="datos">
        <thead>
            <tr>
                <th rowspan="2">Mes</th>
                <th rowspan="2">Semana</th>
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
        Generado el {{ now()->format('d/m/Y H:i') }} &mdash; SISMAPISCIS 2025
    </div>
</body>
</html>
