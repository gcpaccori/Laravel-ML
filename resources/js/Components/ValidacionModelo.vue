<script setup>
// Validacion de un modelo contra lo que midieron los sensores.
//
// Cada grafico enfrenta lo que el modelo dice con lo que se observo, sobre
// datos limpios, y ensena la recta de regresion y su R2. Es la prueba de si un
// modelo sirve en esta piscigranja o solo en el papel donde se publico, asi
// que los resultados se muestran tal cual, tambien cuando no ajustan.
import { computed } from "vue";
import ChartFisheye from "@/Components/ChartFisheye.vue";

const props = defineProps({
    codigo: { type: String, required: true },
    datos: { type: Object, default: null },
    limpieza: { type: Object, default: null },
    numero: { type: [Number, String], default: 4 },
});

const AZUL = "#2563eb";
const MORADO = "#7c3aed";
const NARANJA = "#ea580c";
const GRIS = "#94a3b8";
const VERDE = "#0d9488";
const ES_CALIDAD = computed(() => props.codigo === "WATER_QUALITY_INDEX_ICA");

// Coma decimal y espacio de miles, como en los informes del proyecto.
const espacio = (texto) => texto.replace(/\u202f/g, "\u00a0");
const fmt = (v, d = 2) => (v === null || v === undefined || v === "" || Number.isNaN(Number(v))
    ? "-"
    : espacio(Number(v).toLocaleString("fr-FR", { minimumFractionDigits: 0, maximumFractionDigits: d })));
const fmtFijo = (v, d = 2) => (v === null || v === undefined || Number.isNaN(Number(v))
    ? "-"
    : espacio(Number(v).toLocaleString("fr-FR", { minimumFractionDigits: d, maximumFractionDigits: d })));
const fechaCorta = (iso) => (iso ? new Date(`${iso}T00:00:00`).toLocaleDateString("es-PE", { day: "numeric", month: "short" }) : "");

// Calidad del ajuste en palabras, para quien no lee R2 de corrido.
const lecturaR2 = (r2) => {
    if (r2 === null || r2 === undefined) return { texto: "sin calcular", tono: "gris" };
    if (r2 >= 0.8) return { texto: "ajuste fuerte", tono: "bien" };
    if (r2 >= 0.5) return { texto: "ajuste moderado", tono: "medio" };
    if (r2 >= 0.1) return { texto: "ajuste débil", tono: "flojo" };
    if (r2 >= 0) return { texto: "casi sin relación", tono: "mal" };
    return { texto: "acierta menos que el promedio", tono: "mal" };
};
const lecturaAcierto = (a) => {
    if (a === null || a === undefined) return { texto: "sin calcular", tono: "gris" };
    if (a >= 0.8) return { texto: "clasifica bien", tono: "bien" };
    if (a >= 0.6) return { texto: "clasificación aceptable", tono: "medio" };
    return { texto: "clasifica mal", tono: "mal" };
};

const rango = (puntos, eje) => {
    const vals = (puntos ?? []).map((p) => Number(p[eje])).filter(Number.isFinite);
    if (!vals.length) return [0, 1];
    return [Math.min(...vals), Math.max(...vals)];
};

// Dos puntos bastan para trazar la recta de regresion sobre el rango de x.
const rectaSobre = (recta, x0, x1) => {
    if (!recta) return [];
    const y = (x) => recta.pendiente * x + recta.intercepto;
    return [[x0, Number(y(x0).toFixed(4))], [x1, Number(y(x1).toFixed(4))]];
};

const ejeValor = (nombre, extra = {}) => ({
    type: "value",
    name: nombre,
    nameLocation: "middle",
    nameTextStyle: { color: "#4b5563", fontSize: 12, fontWeight: 600 },
    axisLabel: { color: "#6b7280", formatter: (v) => fmt(v, 2) },
    splitLine: { lineStyle: { color: "#f1f5f9" } },
    ...extra,
});

const base = (ejeX, ejeY) => ({
    grid: { top: 24, left: 40, right: 24, bottom: 64, containLabel: true },
    tooltip: { trigger: "item" },
    legend: { bottom: 0, textStyle: { fontSize: 11 } },
    xAxis: ejeValor(ejeX, { nameGap: 30, scale: true }),
    yAxis: ejeValor(ejeY, { nameGap: 44, scale: true }),
});

// ---------- lo que el modelo espera frente a lo medido ----------
const nubePrevistoMedido = (d, nombrePuntos, unidad) => {
    if (!d?.puntos?.length) return null;
    const [x0, x1] = rango(d.puntos, 0);
    const [y0, y1] = rango(d.puntos, 1);
    const lo = Math.max(0, Math.floor(Math.min(x0, y0)));
    const hi = Math.ceil(Math.max(x1, y1));
    const opciones = base(d.eje_x, d.eje_y);
    return {
        ...opciones,
        xAxis: { ...opciones.xAxis, min: lo, max: hi, scale: false },
        yAxis: { ...opciones.yAxis, min: lo, max: hi, scale: false },
        tooltip: { trigger: "item", formatter: (p) => (p.seriesType === "scatter" ? `Medido ${fmt(p.value[0])} ${unidad}<br/>Esperado ${fmt(p.value[1])} ${unidad}` : p.seriesName) },
        series: [
            { name: nombrePuntos, type: "scatter", symbolSize: 5, color: AZUL, itemStyle: { opacity: 0.45 }, data: d.puntos },
            { name: "Predicción perfecta (esperado = medido)", type: "line", showSymbol: false, color: GRIS, lineStyle: { type: "dashed", width: 1.5 }, data: [[lo, lo], [hi, hi]] },
            { name: "Recta de regresión", type: "line", showSymbol: false, color: NARANJA, lineStyle: { width: 2.5 }, data: rectaSobre(d.recta, lo, hi) },
        ],
    };
};
const graficoProyeccion = computed(() => nubePrevistoMedido(props.datos, "Cada hora de prueba", "mg/L"));
const graficoEstadoPrevisto = computed(() => nubePrevistoMedido(props.datos, "Cada hora de prueba", "mg/L"));

// Ecuacion de la recta ajustada, escrita como se lee en el grafico.
const ecuacionRecta = (recta, y, x) => {
    if (!recta) return "";
    const signo = recta.intercepto < 0 ? "−" : "+";
    return `${y} = ${fmtFijo(recta.pendiente, 3)} × ${x} ${signo} ${fmtFijo(Math.abs(recta.intercepto), 3)}`;
};

// ---------- estado de oxigeno: ciclo diario ----------
const graficoCiclo = computed(() => {
    const c = props.datos?.ciclo;
    if (!c?.curva?.length) return null;
    const opciones = base("Hora del día", "Oxígeno respecto a su nivel de 24 h (mg/L)");
    // Eje acotado a la franja central de las horas medidas, para que unas pocas
    // horas extremas no aplasten el ciclo.
    const valores = c.puntos.map((p) => Math.abs(p[1])).sort((x, y) => x - y);
    const tope = Math.max(1, Math.ceil((valores[Math.floor(valores.length * 0.98)] ?? 1) * 2) / 2);
    return {
        ...opciones,
        yAxis: { ...opciones.yAxis, min: -tope, max: tope, scale: false },
        xAxis: { ...opciones.xAxis, min: 0, max: 24, interval: 3, scale: false, axisLabel: { color: "#6b7280", formatter: (v) => `${String(v).padStart(2, "0")}:00` } },
        tooltip: { trigger: "item", formatter: (p) => (p.seriesName.startsWith("Promedio")
            ? `${String(Math.floor(p.value[0])).padStart(2, "0")}:00 a ${String(Math.floor(p.value[0]) + 1).padStart(2, "0")}:00<br/>${p.value[1] > 0 ? "+" : ""}${fmt(p.value[1])} mg/L respecto a su nivel`
            : p.seriesName) },
        series: [
            { name: "Cada hora medida", type: "scatter", symbolSize: 3, color: GRIS, itemStyle: { opacity: 0.35 }, data: c.puntos },
            { name: "Promedio de cada hora", type: "scatter", symbolSize: 9, color: AZUL, itemStyle: { borderColor: "#fff", borderWidth: 1.5 }, data: c.por_hora.map((h) => [h[0], h[1]]) },
            { name: "Ciclo ajustado (seno y coseno)", type: "line", showSymbol: false, smooth: true, color: NARANJA, lineStyle: { width: 2.5 }, data: c.curva },
        ],
    };
});

// ---------- estado de oxigeno: por que la temperatura no entra ----------
const PALETA_MESES = ["#0ea5e9", "#dc2626", "#16a34a", "#2563eb", "#d97706", "#9333ea", "#0f766e", "#be185d"];
const graficoTemperatura = computed(() => {
    const d = props.datos?.temperatura;
    if (!d?.puntos?.length) return null;
    const [x0, x1] = rango(d.puntos, 0);
    const opciones = base(d.eje_x, d.eje_y);
    const meses = d.por_mes ?? [];
    return {
        ...opciones,
        grid: { ...opciones.grid, bottom: 84 },
        yAxis: { ...opciones.yAxis, min: 0, scale: false },
        tooltip: { trigger: "item", formatter: (p) => (p.seriesType === "scatter" ? `${p.seriesName}<br/>${fmt(p.value[0], 1)} °C<br/>${fmt(p.value[1])} mg/L` : p.seriesName) },
        series: [
            ...meses.map((m, i) => ({
                name: m.nombre,
                type: "scatter",
                symbolSize: 4,
                color: PALETA_MESES[i % PALETA_MESES.length],
                itemStyle: { opacity: 0.45 },
                data: d.puntos.filter((p) => p[2] === m.mes).map((p) => [p[0], p[1]]),
            })),
            { name: "Oxígeno de saturación (Benson-Krause)", type: "line", showSymbol: false, smooth: true, color: MORADO, lineStyle: { width: 2.5 }, data: d.curva_saturacion ?? [] },
            { name: "Recta de regresión con la temperatura", type: "line", showSymbol: false, color: NARANJA, lineStyle: { width: 2.5, type: "dashed" }, data: rectaSobre(d.recta, x0, x1) },
        ],
    };
});

// ---------- crecimiento: predicho frente a medido, por tramo ----------
const etiquetaTramo = (desde, hasta) => `${fechaCorta(desde)} a ${fechaCorta(hasta)}`;
const graficoCrecimientoValidacion = computed(() => {
    const d = props.datos;
    if (!d?.puntos_validacion?.length) return null;
    const todos = [...d.puntos_validacion, ...(d.puntos_soderberg ?? [])];
    const hi = Math.ceil(Math.max(...todos.map((p) => Math.max(p[0], p[1]))) * 5) / 5 + 0.2;
    const opciones = base(d.eje_x, d.eje_y);
    const aPunto = (p) => ({ value: [p[0], p[1]], name: etiquetaTramo(p[2], p[3]) });
    return {
        ...opciones,
        xAxis: { ...opciones.xAxis, min: 0, max: hi, scale: false },
        yAxis: { ...opciones.yAxis, min: 0, max: hi, scale: false },
        tooltip: { trigger: "item", formatter: (p) => (p.seriesType === "scatter" ? `${p.seriesName}<br/>${p.name}<br/>Creció ${fmt(p.value[0])} mm/día<br/>Predice ${fmt(p.value[1])} mm/día` : p.seriesName) },
        series: [
            { name: "Predicción perfecta (predice = creció)", type: "line", showSymbol: false, color: GRIS, lineStyle: { type: "dashed", width: 1.5 }, data: [[0, 0], [hi, hi]] },
            { name: "Söderberg sola", type: "scatter", symbol: "circle", symbolSize: 12, color: MORADO, itemStyle: { color: "#fff", borderColor: MORADO, borderWidth: 2 }, data: (d.puntos_soderberg ?? []).map(aPunto) },
            {
                name: "Con factor de oxígeno (el modelo)",
                type: "scatter",
                symbolSize: 14,
                color: VERDE,
                itemStyle: { borderColor: "#fff", borderWidth: 2 },
                label: { show: true, position: "right", fontSize: 10, color: "#4b5563", formatter: (p) => p.name },
                data: d.puntos_validacion.map(aPunto),
            },
        ],
    };
});

// ---------- crecimiento: relacion con la temperatura ----------
const graficoCrecimiento = computed(() => {
    const d = props.datos;
    if (!d?.linea_modelo?.length) return null;
    const [dv0, dv1] = d.dominio_validado ?? [21, 30];
    const tMin = d.linea_modelo[0][0];
    const tMax = d.linea_modelo[d.linea_modelo.length - 1][0];
    const opciones = base("Temperatura media del agua en el tramo (°C)", "Crecimiento diario en longitud (mm/día)");
    const puntos = d.puntos_temperatura ?? [];
    return {
        ...opciones,
        xAxis: { ...opciones.xAxis, min: tMin, max: tMax, interval: 5, scale: false },
        yAxis: { ...opciones.yAxis, min: 0, scale: false },
        tooltip: {
            trigger: "item",
            formatter: (p) => (p.seriesType === "scatter"
                ? `${p.seriesName}<br/>${p.name}<br/>${fmt(p.value[0], 1)} °C: ${fmt(p.value[1], 2)} mm/día`
                : `Recta de Söderberg<br/>${fmt(p.value[0], 0)} °C: ${fmt(p.value[1], 2)} mm/día`),
        },
        series: [
            {
                name: "Recta de Söderberg (solo temperatura)",
                type: "line",
                showSymbol: false,
                color: MORADO,
                lineStyle: { width: 2.5 },
                data: d.linea_modelo,
                markArea: {
                    silent: true,
                    itemStyle: { color: "rgba(124,58,237,0.07)" },
                    label: { show: true, position: "insideTop", fontSize: 10, color: MORADO, formatter: `rango validado ${dv0}-${dv1} °C` },
                    data: [[{ xAxis: dv0 }, { xAxis: dv1 }]],
                },
            },
            { name: "Con factor de oxígeno", type: "scatter", symbol: "diamond", symbolSize: 13, color: VERDE, data: puntos.map((p) => ({ value: [p[0], p[2]], name: etiquetaTramo(p[3], p[4]) })) },
            {
                name: "Crecimiento medido",
                type: "scatter",
                symbolSize: 13,
                color: NARANJA,
                itemStyle: { borderColor: "#fff", borderWidth: 2 },
                label: { show: true, position: "left", fontSize: 10, color: "#4b5563", formatter: (p) => p.name },
                data: puntos.map((p) => ({ value: [p[0], p[1]], name: etiquetaTramo(p[3], p[4]) })),
            },
        ],
    };
});

// ---------- calidad de agua: dependencia de cada variable ----------
const graficosDependencia = computed(() => (props.datos?.dependencias ?? []).map((dep) => {
    const [x0, x1] = rango(dep.puntos, 0);
    return {
        variable: dep.variable,
        recta: dep.recta,
        opciones: {
            grid: { top: 12, left: 30, right: 14, bottom: 40, containLabel: true },
            tooltip: { trigger: "item", formatter: (p) => (p.seriesType === "scatter" ? `${dep.variable}: ${fmt(p.value[0])}<br/>Puntaje: ${fmt(p.value[1], 1)}` : "Recta de regresión") },
            xAxis: ejeValor(dep.variable, { nameGap: 26, scale: true, splitLine: { show: false } }),
            yAxis: ejeValor("Puntaje de calidad", { nameGap: 34, scale: true }),
            series: [
                { type: "scatter", symbolSize: 4, color: VERDE, itemStyle: { opacity: 0.4 }, data: dep.puntos },
                { type: "line", showSymbol: false, color: NARANJA, lineStyle: { width: 2 }, data: rectaSobre(dep.recta, x0, x1) },
            ],
        },
    };
}));

const matriz = computed(() => props.datos?.matriz_confusion ?? null);
const totalFila = (fila) => fila.reduce((a, b) => a + b, 0);

const metrica = computed(() => {
    const d = props.datos;
    if (ES_CALIDAD.value) {
        return { etiqueta: "Acierto en lecturas de prueba", valor: d?.acierto_svm === null || d?.acierto_svm === undefined ? "-" : `${fmt(d.acierto_svm * 100, 1)} %`, juicio: lecturaAcierto(d?.acierto_svm) };
    }
    const etiqueta = {
        OXYGEN_STATUS_MODEL: "R² en horas de prueba",
        TILAPIA_GROWTH_TEMPERATURE: "R² del crecimiento entre biometrías",
    }[props.codigo] ?? "R² frente a lo medido";
    return { etiqueta, valor: fmtFijo(d?.r2), juicio: lecturaR2(d?.r2) };
});

const unidadError = computed(() => (props.codigo === "TILAPIA_GROWTH_TEMPERATURE" ? "mm/día" : "mg/L"));
const tramosConObservacion = computed(() => (props.datos?.intervalos ?? []).filter((i) => i.observacion && ((i.supervivencia_pct ?? 100) < 100 || (i.conversion_alimenticia ?? 0) > 3)));

const contrastados = computed(() => {
    const d = props.datos;
    if (ES_CALIDAD.value) return { etiqueta: "Lecturas con nitrato válido", valor: fmt(d?.lecturas, 0) };
    if (props.codigo === "TILAPIA_GROWTH_TEMPERATURE") return { etiqueta: "Intervalos entre biometrías", valor: fmt(d?.intervalos?.length, 0) };
    if (props.codigo === "OXYGEN_STATUS_MODEL") return { etiqueta: "Horas de prueba", valor: fmt(d?.puntos_totales, 0) };
    return { etiqueta: "Puntos contrastados", valor: fmt(d?.puntos_totales, 0) };
});
</script>

<template>
    <section class="card card-flush mb-6 val">
        <div class="card-header">
            <div class="val__cab">
                <h4 class="card-title fw-bold text-dark fs-5 mb-0"><span class="val__num">{{ numero }}</span>Validación con datos reales</h4>
                <span v-if="datos?.que_se_compara" class="val__sub-cab">{{ datos.que_se_compara }}</span>
            </div>
        </div>

        <div class="card-body pt-0">
            <p v-if="!datos" class="text-gray-500 fs-7">Calculando la validación con todo el histórico del sensor...</p>
            <p v-else-if="!datos.disponible" class="text-gray-500 fs-7">{{ datos.motivo }}</p>

            <template v-else>
                <div class="val__resumen">
                    <div class="val__dato">
                        <small>Tipo de modelo</small>
                        <strong>{{ datos.tipo }}</strong>
                    </div>
                    <div class="val__dato val__dato--r2">
                        <small>{{ metrica.etiqueta }}</small>
                        <strong :class="'val__r2--' + metrica.juicio.tono">{{ metrica.valor }}</strong>
                        <span :class="'val__r2--' + metrica.juicio.tono">{{ metrica.juicio.texto }}</span>
                    </div>
                    <div v-if="datos.mae !== undefined" class="val__dato">
                        <small>Error medio</small>
                        <strong>{{ fmt(datos.mae, 3) }} {{ unidadError }}</strong>
                    </div>
                    <div v-if="ES_CALIDAD && datos.acierto_svm_guardado !== null && datos.acierto_svm_guardado !== undefined" class="val__dato">
                        <small>SVM que usan hoy las alarmas</small>
                        <strong>{{ fmt(datos.acierto_svm_guardado * 100, 1) }} %</strong>
                        <span class="val__dato-pie">entrenado con nitrato saturado</span>
                    </div>
                    <div class="val__dato">
                        <small>{{ contrastados.etiqueta }}</small>
                        <strong>{{ contrastados.valor }}</strong>
                    </div>
                </div>

                <p v-if="datos.formula" class="val__formula"><code>{{ datos.formula }}</code></p>
                <p v-if="datos.formula_delta" class="val__formula"><code>{{ datos.formula_delta }}</code></p>
                <p v-if="datos.fuente" class="text-gray-500 fs-8 mb-4">{{ datos.fuente }}</p>

                <!-- proyeccion de oxigeno -->
                <template v-if="codigo === 'SVM_OD_FORECAST_1H'">
                    <ChartFisheye v-if="graficoProyeccion" :options="graficoProyeccion" height="400px" />
                    <div class="val__pie">
                        <span>Recta de regresión <strong>{{ ecuacionRecta(datos.recta, "previsto", "medido") }}</strong></span>
                        <span>R² de la recta <strong>{{ fmtFijo(datos.recta?.r2) }}</strong></span>
                        <span>Correlación <strong>{{ fmtFijo(datos.recta?.pearson) }}</strong></span>
                    </div>
                    <div class="table-responsive mt-4">
                        <table class="table table-sm table-row-dashed align-middle mb-0">
                            <thead><tr class="text-gray-500 fs-8"><th>Método, sobre las mismas horas de prueba</th><th class="text-end">R²</th><th class="text-end">Error medio (mg/L)</th></tr></thead>
                            <tbody>
                                <tr v-for="(fila, i) in datos.comparacion" :key="fila.metodo" :class="{ 'val__fila-modelo': i === 0 }">
                                    <td class="fs-7">{{ fila.metodo }}</td>
                                    <td class="text-end fs-7 fw-bold">{{ fmtFijo(fila.r2) }}</td>
                                    <td class="text-end fs-7">{{ fmtFijo(fila.mae, 3) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="val__nota">{{ datos.reparto }}.</p>
                </template>

                <!-- estado de oxigeno -->
                <template v-else-if="codigo === 'OXYGEN_STATUS_MODEL'">
                    <p v-if="datos.coeficientes" class="val__nota mt-0 mb-3">
                        Coeficientes ajustados con el histórico: a = {{ fmtFijo(datos.coeficientes.a, 3) }}, b = {{ fmtFijo(datos.coeficientes.b, 3) }}, c = {{ fmtFijo(datos.coeficientes.c, 3) }} mg/L; h es la hora del día.
                    </p>
                    <p class="val__titulo">Oxígeno esperado frente a oxígeno medido</p>
                    <ChartFisheye v-if="graficoEstadoPrevisto" :options="graficoEstadoPrevisto" height="400px" />
                    <div class="val__pie">
                        <span>Recta de regresión <strong>{{ ecuacionRecta(datos.recta, "esperado", "medido") }}</strong></span>
                        <span>R² de la recta <strong>{{ fmtFijo(datos.recta?.r2) }}</strong></span>
                        <span>Correlación <strong>{{ fmtFijo(datos.recta?.pearson) }}</strong></span>
                    </div>
                    <div class="table-responsive mt-4">
                        <table class="table table-sm table-row-dashed align-middle mb-0">
                            <thead><tr class="text-gray-500 fs-8"><th>Método, sobre las mismas horas de prueba</th><th class="text-end">R²</th><th class="text-end">Error medio (mg/L)</th></tr></thead>
                            <tbody>
                                <tr v-for="(fila, i) in datos.comparacion" :key="fila.metodo" :class="{ 'val__fila-modelo': i === 0 }">
                                    <td class="fs-7">{{ fila.metodo }}</td>
                                    <td class="text-end fs-7 fw-bold">{{ fmtFijo(fila.r2) }}</td>
                                    <td class="text-end fs-7">{{ fmtFijo(fila.mae, 3) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="val__nota">{{ datos.reparto }}.</p>

                    <template v-if="graficoCiclo">
                        <p class="val__titulo mt-6">Ciclo diario: de qué hora depende el oxígeno</p>
                        <p class="val__nota mt-0 mb-3">Cada punto gris es una hora medida, como diferencia con el nivel de las 24 horas anteriores. Los puntos azules son el promedio de cada hora del día y la curva naranja es el seno y coseno ajustados. El eje muestra el 98 % central de las horas.</p>
                        <ChartFisheye :options="graficoCiclo" height="360px" />
                        <div class="val__pie">
                            <span>Máximo hacia las <strong>{{ datos.ciclo.hora_maximo }}</strong></span>
                            <span>Mínimo hacia las <strong>{{ datos.ciclo.hora_minimo }}</strong></span>
                            <span>Amplitud <strong>± {{ fmt(datos.ciclo.amplitud_mg_l) }} mg/L</strong></span>
                        </div>
                    </template>

                    <template v-if="graficoTemperatura">
                        <p class="val__titulo mt-6">Por qué la temperatura no entra en el modelo</p>
                        <p class="val__nota mt-0 mb-3">Oxígeno medido frente a la temperatura, coloreado por mes. La curva morada es lo máximo que admite el agua (Benson-Krause).</p>
                        <ChartFisheye :options="graficoTemperatura" height="400px" />
                        <div class="val__pie">
                            <span>Recta de regresión <strong>{{ ecuacionRecta(datos.temperatura.recta, "OD", "T") }}</strong></span>
                            <span>R² con la temperatura <strong class="val__r2--mal">{{ fmtFijo(datos.temperatura.recta?.r2) }}</strong></span>
                            <span>Saturación mediana <strong>{{ fmt(datos.temperatura.saturacion_mediana_pct, 0) }} %</strong></span>
                        </div>
                        <div v-if="datos.temperatura.por_mes?.length" class="table-responsive mt-3">
                            <table class="table table-sm table-row-dashed align-middle mb-0">
                                <thead><tr class="text-gray-500 fs-8"><th>Mes</th><th class="text-end">Lecturas limpias</th><th class="text-end">Temperatura media</th><th class="text-end">Oxígeno mediano</th><th class="text-end">Lecturas bajo 3 mg/L</th></tr></thead>
                                <tbody>
                                    <tr v-for="(m, i) in datos.temperatura.por_mes" :key="m.mes">
                                        <td class="fs-7"><span class="val__punto" :style="{ background: PALETA_MESES[i % PALETA_MESES.length] }"></span>{{ m.nombre }}</td>
                                        <td class="text-end fs-7">{{ fmt(m.lecturas, 0) }}</td>
                                        <td class="text-end fs-7">{{ fmt(m.temperatura_media, 1) }} °C</td>
                                        <td class="text-end fs-7 fw-bold">{{ fmt(m.oxigeno_mediana) }} mg/L</td>
                                        <td class="text-end fs-7">{{ fmt(m.pct_bajo_3, 0) }} %</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="val__nota">A temperaturas parecidas hubo meses con el oxígeno cerca de 2 mg/L y meses cerca de 5: el nivel lo marca la época, y por eso el modelo lo toma de las 24 horas previas.</p>
                    </template>
                </template>

                <!-- crecimiento -->
                <template v-else-if="codigo === 'TILAPIA_GROWTH_TEMPERATURE'">
                    <p class="val__titulo">Crecimiento que predice el modelo frente al medido</p>
                    <ChartFisheye v-if="graficoCrecimientoValidacion" :options="graficoCrecimientoValidacion" height="400px" />
                    <div class="table-responsive mt-4">
                        <table class="table table-sm table-row-dashed align-middle mb-0">
                            <thead><tr class="text-gray-500 fs-8"><th>Modelo, sobre los mismos tramos entre biometrías</th><th class="text-end">R²</th><th class="text-end">Error medio (mm/día)</th></tr></thead>
                            <tbody>
                                <tr v-for="(fila, i) in datos.comparacion" :key="fila.metodo" :class="{ 'val__fila-modelo': i === 0 }">
                                    <td class="fs-7">{{ fila.metodo }}</td>
                                    <td class="text-end fs-7 fw-bold">{{ fmtFijo(fila.r2) }}</td>
                                    <td class="text-end fs-7">{{ fmtFijo(fila.mae, 3) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="val__titulo mt-6">Relación con la temperatura</p>
                    <ChartFisheye v-if="graficoCrecimiento" :options="graficoCrecimiento" height="400px" />
                    <p class="val__nota mt-2">
                        La recta morada es Söderberg, que solo mira la temperatura. Los rombos verdes son lo que predice el modelo con el oxígeno real de cada tramo, y los puntos naranjas lo que creció el lote.
                        Por debajo de {{ fmt(datos.cero_de_la_recta_c, 1) }} °C la recta da valores negativos y un pez no encoge, por eso se dibuja en cero.
                    </p>

                    <div class="table-responsive mt-4">
                        <table class="table table-sm table-row-dashed align-middle mb-0">
                            <thead>
                                <tr class="text-gray-500 fs-8">
                                    <th>Tramo entre biometrías</th>
                                    <th class="text-end">Días</th>
                                    <th class="text-end">Temperatura</th>
                                    <th class="text-end">Oxígeno medio</th>
                                    <th class="text-end">δ medio</th>
                                    <th class="text-end">pH medio</th>
                                    <th class="text-end">Creció</th>
                                    <th class="text-end">Söderberg sola</th>
                                    <th class="text-end">Con δ</th>
                                    <th class="text-end">Supervivencia</th>
                                    <th class="text-end">Conversión</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="i in datos.intervalos" :key="i.desde">
                                    <td class="fs-7">{{ fechaCorta(i.desde) }} a {{ fechaCorta(i.hasta) }}</td>
                                    <td class="text-end fs-7">{{ i.dias }}</td>
                                    <td class="text-end fs-7">{{ fmt(i.temperatura_media, 1) }} °C</td>
                                    <td class="text-end fs-7">
                                        <template v-if="i.oxigeno_medio !== null">{{ fmt(i.oxigeno_medio) }} mg/L <span class="text-gray-500">({{ fmt(i.pct_oxigeno_bajo_3, 0) }} % bajo 3)</span></template>
                                        <span v-else class="text-gray-500">sin lecturas válidas</span>
                                    </td>
                                    <td class="text-end fs-7">{{ i.delta_medio === null ? "1 (supuesto)" : fmtFijo(i.delta_medio) }}</td>
                                    <td class="text-end fs-7">{{ fmt(i.ph_medio, 1) }}</td>
                                    <td class="text-end fs-7 fw-bold">{{ fmtFijo(i.observado_mm_dia) }}</td>
                                    <td class="text-end fs-7">{{ fmtFijo(i.soderberg_mm_dia) }}</td>
                                    <td class="text-end fs-7 fw-bold val__verde">{{ fmtFijo(i.corregido_mm_dia) }}</td>
                                    <td class="text-end fs-7">{{ i.supervivencia_pct === null ? "-" : fmt(i.supervivencia_pct, 1) + " %" }}</td>
                                    <td class="text-end fs-7" :class="{ 'val__r2--mal': (i.conversion_alimenticia ?? 0) > 3 }">{{ fmt(i.conversion_alimenticia, 1) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="val__nota">Crecimiento en mm/día. δ es el factor de oxígeno de cada día promediado en el tramo; sin lecturas válidas de oxígeno se toma 1. Supervivencia y conversión alimenticia son las que registró cada biometría.</p>
                    <div v-for="i in tramosConObservacion" :key="'obs' + i.hasta" class="val__observacion">
                        <strong>Observación de la biometría del {{ fechaCorta(i.hasta) }}:</strong> {{ i.observacion }}
                    </div>
                </template>

                <!-- calidad de agua -->
                <template v-else-if="ES_CALIDAD">
                    <p class="val__titulo">De qué variable depende la calidad del agua</p>
                    <p class="val__nota mt-0 mb-3">Cada punto es una lectura limpia: en el eje horizontal la variable, en el vertical el puntaje de calidad de esa lectura. La recta naranja es la regresión lineal simple.</p>
                    <div class="val__rejilla">
                        <div v-for="g in graficosDependencia" :key="g.variable" class="val__mini">
                            <div class="val__mini-cab">
                                <span>Regresión lineal simple</span>
                                <strong :class="'val__r2--' + lecturaR2(g.recta.r2).tono">R² {{ fmtFijo(g.recta.r2) }}</strong>
                            </div>
                            <ChartFisheye :options="g.opciones" height="220px" />
                        </div>
                    </div>

                    <template v-if="matriz">
                        <p class="val__titulo mt-6">Clase que asigna el SVM frente a la clase de referencia</p>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0 val__matriz">
                                <thead>
                                    <tr class="text-gray-500 fs-8">
                                        <th>Referencia \ SVM</th>
                                        <th v-for="e in matriz.etiquetas" :key="e" class="text-end">{{ e }}</th>
                                        <th class="text-end">Acierto</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="(fila, r) in matriz.filas" :key="matriz.etiquetas[r]">
                                        <td class="fs-7 fw-semibold">{{ matriz.etiquetas[r] }}</td>
                                        <td v-for="(n, c) in fila" :key="c" :class="['text-end fs-7', { 'val__diag': r === c }]">{{ fmt(n, 0) }}</td>
                                        <td class="text-end fs-7">{{ totalFila(fila) ? fmt((fila[r] / totalFila(fila)) * 100, 0) + " %" : "-" }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="val__nota">En verde, las lecturas en que el SVM coincide con la referencia. {{ datos.reparto }}.</p>
                    </template>
                    <p class="val__nota">{{ datos.origen_etiqueta }}</p>
                </template>

                <p class="val__lectura">{{ datos.lectura }}</p>
                <p v-if="limpieza" class="val__limpieza">
                    Datos: {{ fmt(limpieza.lecturas_limpias, 0) }} lecturas limpias de {{ fmt(limpieza.lecturas_crudas, 0) }}.
                    {{ limpieza.criterio }}
                    <template v-if="ES_CALIDAD"> Para este modelo, además, se apartan las lecturas con nitrato por encima de 100 mg/L.</template>
                </p>
            </template>
        </div>
    </section>
</template>

<style scoped>
.val__cab { display: flex; flex-direction: column; gap: 4px; padding: 6px 0; }
.val__num { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: #111827; color: #fff; font-size: 12px; margin-right: 10px; }
.val__sub-cab { font-size: 13px; color: #6b7280; padding-left: 32px; }
.val__resumen { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; }
.val__dato { flex: 1 1 150px; background: #f8fafc; border: 1px solid #eef2f7; border-radius: 8px; padding: 10px 12px; }
.val__dato small { display: block; font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #6b7280; margin-bottom: 3px; }
.val__dato strong { font-size: 14px; color: #1f2937; line-height: 1.35; display: block; }
.val__dato-pie { display: block; font-size: 11px; color: #6b7280; }
.val__dato--r2 strong { font-size: 22px; }
.val__dato--r2 span { font-size: 11px; font-weight: 600; }
.val__r2--bien { color: #15803d !important; }
.val__r2--medio { color: #0e7490 !important; }
.val__r2--flojo { color: #b45309 !important; }
.val__r2--mal { color: #b91c1c !important; }
.val__r2--gris { color: #6b7280 !important; }
.val__formula { margin: 0 0 4px; }
.val__formula code { font-size: 13px; color: #1f2937; background: #f1f5f9; padding: 4px 8px; border-radius: 5px; }
.val__titulo { font-size: 13px; font-weight: 700; color: #374151; margin: 4px 0 6px; }
.val__rejilla { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.val__mini { border: 1px solid #eef2f7; border-radius: 8px; padding: 8px 10px 2px; }
.val__mini-cab { display: flex; justify-content: space-between; align-items: baseline; font-size: 12px; color: #4b5563; }
.val__mini-cab strong { font-size: 12px; }
.val__pie { display: flex; flex-wrap: wrap; gap: 8px 18px; font-size: 12px; color: #6b7280; margin-top: 8px; }
.val__pie strong { color: #1f2937; }
.val__fila-modelo td { background: #f5f7ff; }
.val__verde { color: #0f766e; }
.val__observacion { font-size: 12px; line-height: 1.6; color: #7c2d12; background: #fff7ed; border: 1px solid #fed7aa; border-radius: 8px; padding: 10px 12px; margin-top: 10px; white-space: pre-line; }
.val__punto { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 8px; vertical-align: middle; }
.val__matriz th, .val__matriz td { border-bottom: 1px solid #f1f5f9; }
.val__diag { background: #ecfdf5; color: #047857; font-weight: 700; }
.val__lectura { font-size: 13px; line-height: 1.65; color: #374151; margin: 16px 0 6px; border-left: 3px solid #e5e7eb; padding-left: 12px; }
.val__nota { font-size: 12px; line-height: 1.6; color: #6b7280; margin: 8px 0 0; }
.val__limpieza { font-size: 11px; line-height: 1.55; color: #9ca3af; margin: 8px 0 0; }
@media (max-width: 900px) { .val__rejilla { grid-template-columns: 1fr; } }
</style>
