<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ficha de Biometría - {{ $biometria->nombre_piscigranja }} - {{ $biometria->nombre_campania }}</title>
    <style>
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #333;
            margin: 20px;
        }
        h1, h2, h3 {
            text-align: center;
            margin: 0;
        }
        h1 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        h2 {
            font-size: 15px;
            margin-top: 20px;
            border-bottom: 1px solid #999;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
        }
        th, td {
            border: 1px solid #999;
            padding: 4px 6px;
            text-align: center;
        }
        th {
            background: #f2f2f2;
        }
        .section {
            margin-bottom: 10px;
        }
        .right {
            text-align: right;
        }
        .center {
            text-align: center;
        }
        .small {
            font-size: 11px;
        }
    </style>
</head>
<body>

    <h1>FICHA DE BIOMETRÍA</h1>
    <h3>{{ $biometria->nombre_piscigranja }}</h3>

    <div class="right small">
        <p>Fecha de emisión: {{ now()->format('d/m/Y H:i') }}</p>
    </div>

    {{-- === 1. DATOS DE CAMPAÑA === --}}
    <div class="section">
        <h2>1. DATOS DE CAMPAÑA</h2>
        <table>
            <tr>
                <th>Campaña</th>
                <td>{{ $biometria->nombre_campania }}</td>
                <th>Especie</th>
                <td>{{ $biometria->nombre_especie }}</td>
            </tr>
            <tr>
                <th>Piscigranja</th>
                <td>{{ $biometria->nombre_piscigranja }}</td>
                <th>Piscina</th>
                <td>{{ $biometria->nombre_piscina }}</td>
            </tr>
            <tr>
                <th>Etapa</th>
                <td>{{ $biometria->nombre_etapa }}</td>
                <th>Área (m²)</th>
                <td>{{ $biometria->campaniaEtapa->area_piscigranja_m2 }}</td>
            </tr>
            <tr>
                <th>Volumen (m³)</th>
                <td>{{ $biometria->campaniaEtapa->volumen_piscigranja_m3 }}</td>
                <th>Altura (m)</th>
                <td>{{ $biometria->campaniaEtapa->altura_piscigranja_m }}</td>
            </tr>
        </table>
    </div>

    {{-- === 2. DATOS DE BIOMETRÍA === --}}
    <div class="section">
        <h2>2. DATOS DE BIOMETRÍA</h2>
        <table>
            <tr>
                <th>Fecha Muestreo</th>
                <td>{{ \Carbon\Carbon::parse($biometria->fecha_muestreo)->format('d/m/Y') }}</td>
                <th>Cant. Muestreo</th>
                <td>{{ $biometria->cantidad_muestreo }}</td>
            </tr>
            <tr>
                <th>N° Peces Inicial</th>
                <td>{{ $biometria->cantidad_peces_inicial }}</td>
                <th>N° Peces Final</th>
                <td>{{ $biometria->cantidad_peces_final }}</td>
            </tr>
            <tr>
                <th>Peso Inicial (gr)</th>
                <td>{{ $biometria->peso_inicial_gr }}</td>
                <th>Peso Final (gr)</th>
                <td>{{ $biometria->peso_final_gr }}</td>
            </tr>
            <tr>
                <th>Tamaño Inicial (cm)</th>
                <td>{{ $biometria->tamanio_inicial_cm }}</td>
                <th>Tamaño Final (cm)</th>
                <td>{{ $biometria->tamanio_final_cm }}</td>
            </tr>
            <tr>
                <th>Biomasa Inicial (kg)</th>
                <td>{{ $biometria->biomasa_inicial_kg }}</td>
                <th>Biomasa Final (kg)</th>
                <td>{{ $biometria->biomasa_final_kg }}</td>
            </tr>
            <tr>
                <th>Tasa Supervivencia (%)</th>
                <td>{{ $biometria->tasa_supervivencia_porcentaje }}</td>
                <th>Tasa Crecimiento Específico (%)</th>
                <td>{{ $biometria->tasa_crecimiento_especifico_porcentaje }}</td>
            </tr>
        </table>
    </div>

    {{-- === 4. OBSERVACIONES === --}}
    <div class="section">
        <h2>4. OBSERVACIONES</h2>
        <p>{{ $biometria->observaciones ?? 'Sin observaciones.' }}</p>
    </div>
</body>
</html>
