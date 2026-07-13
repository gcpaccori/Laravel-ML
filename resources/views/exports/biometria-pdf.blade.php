<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Biometría #{{ $biometria->id }}</title>
    <style>
        @page { margin: 24px; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #222; }
        h1 { font-size: 16px; margin: 0 0 4px 0; }
        h2 { font-size: 13px; margin: 16px 0 6px 0; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
        .subtitulo { color: #666; font-size: 11px; margin: 0 0 12px 0; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
        th, td { border: 1px solid #ddd; padding: 4px 6px; text-align: left; font-size: 10px; }
        th { background-color: #f2f2f2; }
        .grid-2 { width: 100%; }
        .grid-2 td { border: none; vertical-align: top; padding: 0; }
        .badge { display: inline-block; padding: 2px 6px; border-radius: 3px; color: #fff; font-size: 10px; }
        .badge-success { background-color: #67C23A; }
        .badge-warning { background-color: #E6A23C; }
        .badge-danger { background-color: #F56C6C; }
        .bar-row td { border: none; padding: 2px 0; }
        .bar-track { background: #eee; border-radius: 3px; height: 12px; width: 100%; position: relative; }
        .bar-fill { background: #409EFF; height: 12px; border-radius: 3px; }
        .bar-fill.peso { background: #67C23A; }
        .text-right { text-align: right; }
    </style>
</head>
<body>

    <h1>Reporte de Biometría #{{ $biometria->id }}</h1>
    <p class="subtitulo">
        {{ $biometria->nombre_piscigranja }} / {{ $biometria->nombre_campania }} /
        {{ $biometria->nombre_especie }} / {{ $biometria->nombre_etapa }}
        &nbsp;—&nbsp; Fecha de muestreo: {{ optional($biometria->fecha_muestreo)->format('d/m/Y') }}
    </p>

    <h2>Información de la Campaña</h2>
    <table>
        <tr>
            <th>Fecha de siembra</th>
            <td>{{ optional($campaniaEspecie?->fecha_siembra)->format('d/m/Y') ?? '-' }}</td>
            <th>Cantidad sembrada</th>
            <td>{{ $campaniaEspecie?->cantidad_siembra ?? '-' }}</td>
        </tr>
        <tr>
            <th>Peso inicial (g)</th>
            <td>{{ $campaniaEspecie?->peso_inicial_gr ?? '-' }}</td>
            <th>Peso final (g)</th>
            <td>{{ $campaniaEspecie?->peso_final_gr ?? '-' }}</td>
        </tr>
    </table>

    <h2>Datos de la Biometría</h2>
    <table class="grid-2">
        <tr>
            <td style="width: 50%; padding-right: 8px;">
                <table>
                    <tr><th colspan="2">Muestreo</th></tr>
                    <tr><td>Fecha inicial</td><td>{{ optional($biometria->fecha_inicial)->format('d/m/Y') }}</td></tr>
                    <tr><td>Fecha de muestreo</td><td>{{ optional($biometria->fecha_muestreo)->format('d/m/Y') }}</td></tr>
                    <tr><td>Tiempo transcurrido</td><td>{{ $biometria->tiempo_dias }} días</td></tr>
                    <tr><td>Cantidad muestreada</td><td>{{ $biometria->cantidad_muestreo }}</td></tr>
                    <tr><td>% de muestreo</td><td>{{ $biometria->muestreo_porcentaje }}%</td></tr>
                </table>
            </td>
            <td style="width: 50%; padding-left: 8px;">
                <table>
                    <tr><th colspan="2">Población</th></tr>
                    <tr><td>Peces iniciales</td><td>{{ $biometria->cantidad_peces_iniciales }}</td></tr>
                    <tr><td>Peces actuales</td><td>{{ $biometria->cantidad_peces_actuales }}</td></tr>
                    <tr>
                        <td>Tasa de supervivencia</td>
                        <td>
                            @php
                                $sup = $biometria->tasa_supervivencia_porcentaje;
                                $clase = $sup >= 85 ? 'badge-success' : ($sup >= 60 ? 'badge-warning' : 'badge-danger');
                            @endphp
                            <span class="badge {{ $clase }}">{{ $sup }}%</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <tr>
            <td style="width: 50%; padding-right: 8px; padding-top: 8px;">
                <table>
                    <tr><th colspan="2">Biomasa y alimentación</th></tr>
                    <tr><td>Biomasa inicial (kg)</td><td>{{ $biometria->bi_kg }}</td></tr>
                    <tr><td>Biomasa final (kg)</td><td>{{ $biometria->bf_kg }}</td></tr>
                    <tr><td>Alimento consumido (kg)</td><td>{{ $biometria->total_alimento_consumido_kg }}</td></tr>
                    <tr><td>Conversión alimenticia (FCA)</td><td>{{ $biometria->conversion_alimenticia }}</td></tr>
                </table>
            </td>
            <td style="width: 50%; padding-left: 8px; padding-top: 8px;">
                <table>
                    <tr><th colspan="2">Crecimiento</th></tr>
                    <tr><td>Peso promedio (g)</td><td>{{ $biometria->prom_peso_g }}</td></tr>
                    <tr><td>Longitud promedio (cm)</td><td>{{ $biometria->prom_longitud_cm }}</td></tr>
                    <tr><td>Tasa de crecimiento (g/día)</td><td>{{ $biometria->tasa_crecimiento_g_dia }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if($biometria->observaciones)
        <h2>Observaciones</h2>
        <p>{{ $biometria->observaciones }}</p>
    @endif

    <h2>Distribución Porcentual del Crecimiento en Longitud (cm)</h2>
    <table>
        <thead>
            <tr><th style="width: 30%;">Rango</th><th style="width: 15%;">Cantidad</th><th style="width: 15%;">%</th><th>Gráfico</th></tr>
        </thead>
        <tbody>
            @forelse($distribucionLongitud as $fila)
                <tr class="bar-row">
                    <td>{{ $fila['rango'] }}</td>
                    <td class="text-right">{{ $fila['cantidad'] }}</td>
                    <td class="text-right">{{ $fila['porcentaje'] }}%</td>
                    <td>
                        <div class="bar-track">
                            <div class="bar-fill" style="width: {{ $fila['porcentaje'] }}%;"></div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">Sin datos suficientes.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Distribución Porcentual del Crecimiento en Peso (g)</h2>
    <table>
        <thead>
            <tr><th style="width: 30%;">Rango</th><th style="width: 15%;">Cantidad</th><th style="width: 15%;">%</th><th>Gráfico</th></tr>
        </thead>
        <tbody>
            @forelse($distribucionPeso as $fila)
                <tr class="bar-row">
                    <td>{{ $fila['rango'] }}</td>
                    <td class="text-right">{{ $fila['cantidad'] }}</td>
                    <td class="text-right">{{ $fila['porcentaje'] }}%</td>
                    <td>
                        <div class="bar-track">
                            <div class="bar-fill peso" style="width: {{ $fila['porcentaje'] }}%;"></div>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4">Sin datos suficientes.</td></tr>
            @endforelse
        </tbody>
    </table>

    <h2>Detalle de Muestras ({{ $biometria->detalles->count() }})</h2>
    <table>
        <thead>
            <tr><th>N° Pez</th><th>Peso (g)</th><th>Longitud (cm)</th></tr>
        </thead>
        <tbody>
            @foreach($biometria->detalles as $i => $d)
                <tr>
                    <td>{{ $d->numero ?? '-' }}</td>
                    <td>{{ $d->peso_g }}</td>
                    <td>{{ $d->longitud_cm }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

</body>
</html>
