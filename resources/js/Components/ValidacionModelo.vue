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
});

const AZUL = "#2563eb";
const MORADO = "#7c3aed";
const NARANJA = "#ea580c";
const GRIS = "#94a3b8";
const VERDE = "#0d9488";

const fmt = (v, d = 3) => (v === null || v === undefined || Number.isNaN(Number(v)) ? "-" : Number(v).toLocaleString("es-PE", { maximumFractionDigits: d }));

// Calidad del ajuste en palabras, para quien no lee R2 de corrido.
const lecturaR2 = (r2) => {
    if (r2 === null || r2 === undefined) return { texto: "sin calcular", tono: "gris" };
    if (r2 >= 0.8) return { texto: "ajuste fuerte", tono: "bien" };
    if (r2 >= 0.5) return { texto: "ajuste moderado", tono: "medio" };
    if (r2 >= 0.1) return { texto: "ajuste debil", tono: "flojo" };
    if (r2 >= 0) return { texto: "casi sin relacion", tono: "mal" };
    return { texto: "peor que la media", tono: "mal" };
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

const base = (ejeX, ejeY) => ({
    grid: { top: 30, left: 12, right: 24, bottom: 46, containLabel: true },
    tooltip: { trigger: "item" },
    legend: { bottom: 0, textStyle: { fontSize: 11 } },
    xAxis: { type: "value", name: ejeX, nameLocation: "middle", nameGap: 28, scale: true, splitLine: { lineStyle: { color: "#f1f5f9" } } },
    yAxis: { type: "value", name: ejeY, nameLocation: "middle", nameGap: 44, scale: true, splitLine: { lineStyle: { color: "#f1f5f9" } } },
});

// ---------- proyeccion de oxigeno: previsto frente a medido ----------
const graficoProyeccion = computed(() => {
    const d = props.datos;
    if (!d?.puntos?.length) return null;
    const [x0, x1] = rango(d.puntos, 0);
    const [y0, y1] = rango(d.puntos, 1);
    const lo = Math.floor(Math.min(x0, y0));
    const hi = Math.ceil(Math.max(x1, y1));
    return {
        ...base(d.eje_x, d.eje_y),
        series: [
            { name: "Cada ventana de prueba", type: "scatter", symbolSize: 5, itemStyle: { color: AZUL, opacity: 0.45 }, data: d.puntos },
            { name: "Prediccion perfecta (y = x)", type: "line", showSymbol: false, lineStyle: { color: GRIS, type: "dashed", width: 1.5 }, data: [[lo, lo], [hi, hi]] },
            { name: `Regresion (R2 ${fmt(d.recta?.r2, 3)})`, type: "line", showSymbol: false, lineStyle: { color: NARANJA, width: 2.5 }, data: rectaSobre(d.recta, lo, hi) },
        ],
    };
});

// ---------- estado de oxigeno: OD medido frente a temperatura ----------
const graficoEstado = computed(() => {
    const d = props.datos;
    if (!d?.puntos?.length) return null;
    const [x0, x1] = rango(d.puntos, 0);
    return {
        ...base(d.eje_x, d.eje_y),
        series: [
            { name: "Lecturas limpias", type: "scatter", symbolSize: 4, itemStyle: { color: AZUL, opacity: 0.35 }, data: d.puntos },
            { name: "Saturacion teorica (Benson-Krause)", type: "line", showSymbol: false, smooth: true, lineStyle: { color: MORADO, width: 2.5 }, data: d.curva_saturacion ?? [] },
            { name: `Regresion del OD medido (R2 ${fmt(d.recta?.r2, 3)})`, type: "line", showSymbol: false, lineStyle: { color: NARANJA, width: 2.5 }, data: rectaSobre(d.recta, x0, x1) },
        ],
    };
});

// ---------- crecimiento: recta de Soderberg frente a intervalos medidos ----------
const graficoCrecimiento = computed(() => {
    const d = props.datos;
    if (!d?.linea_modelo?.length) return null;
    const [dv0, dv1] = d.dominio_validado ?? [21, 30];
    return {
        ...base(d.eje_x, d.eje_y),
        xAxis: { ...base(d.eje_x, d.eje_y).xAxis, min: 10, max: 40, scale: false },
        tooltip: {
            trigger: "item",
            formatter: (p) => (p.seriesName.startsWith("Medido")
                ? `Medido<br/>${fmt(p.value[0], 2)} C<br/>${fmt(p.value[1], 3)} mm/dia`
                : `${p.seriesName}<br/>${fmt(p.value[0], 1)} C: ${fmt(p.value[1], 3)} mm/dia`),
        },
        series: [
            {
                name: "Recta de Soderberg",
                type: "line",
                showSymbol: false,
                lineStyle: { color: MORADO, width: 2.5 },
                data: d.linea_modelo,
                markArea: {
                    silent: true,
                    itemStyle: { color: "rgba(124,58,237,0.07)" },
                    label: { show: true, position: "insideTop", fontSize: 10, color: MORADO, formatter: "rango validado" },
                    data: [[{ xAxis: dv0 }, { xAxis: dv1 }]],
                },
            },
            { name: "Medido entre biometrias", type: "scatter", symbolSize: 13, itemStyle: { color: NARANJA, borderColor: "#fff", borderWidth: 2 }, data: d.puntos },
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
            grid: { top: 14, left: 8, right: 14, bottom: 34, containLabel: true },
            tooltip: { trigger: "item" },
            xAxis: { type: "value", name: dep.variable, nameLocation: "middle", nameGap: 24, scale: true, splitLine: { show: false } },
            yAxis: { type: "value", name: "Nota", scale: true, splitLine: { lineStyle: { color: "#f1f5f9" } } },
            series: [
                { type: "scatter", symbolSize: 4, itemStyle: { color: VERDE, opacity: 0.4 }, data: dep.puntos },
                { type: "line", showSymbol: false, lineStyle: { color: NARANJA, width: 2 }, data: rectaSobre(dep.recta, x0, x1) },
            ],
        },
    };
}));

const graficoRegresionMultiple = computed(() => {
    const r = props.datos?.regresion_multiple;
    if (!r?.puntos?.length) return null;
    const [x0, x1] = rango(r.puntos, 0);
    const lo = Math.floor(x0);
    const hi = Math.ceil(x1);
    return {
        ...base("Nota de calidad calculada", "Nota que estima la regresion"),
        series: [
            { name: "Lecturas de prueba", type: "scatter", symbolSize: 4, itemStyle: { color: VERDE, opacity: 0.4 }, data: r.puntos },
            { name: "Estimacion perfecta (y = x)", type: "line", showSymbol: false, lineStyle: { color: GRIS, type: "dashed" }, data: [[lo, lo], [hi, hi]] },
        ],
    };
});

const r2Principal = computed(() => {
    if (props.codigo === "WATER_QUALITY_INDEX_ICA") return props.datos?.regresion_multiple?.r2_prueba ?? null;
    return props.datos?.r2 ?? null;
});
const juicio = computed(() => lecturaR2(r2Principal.value));
</script>

<template>
    <section class="card card-flush mb-6 val">
        <div class="card-header">
            <div class="val__cab">
                <span class="val__eyebrow">Validacion con datos reales</span>
                <h4 class="card-title fw-bold text-dark fs-5 mb-0">{{ datos?.que_se_compara ?? "Contraste con lo medido" }}</h4>
            </div>
        </div>

        <div class="card-body pt-0">
            <p v-if="!datos" class="text-gray-500 fs-7">Calculando la validacion...</p>
            <p v-else-if="!datos.disponible" class="text-gray-500 fs-7">{{ datos.motivo }}</p>

            <template v-else>
                <div class="val__resumen">
                    <div class="val__dato">
                        <small>Tipo de modelo</small>
                        <strong>{{ datos.tipo }}</strong>
                    </div>
                    <div class="val__dato val__dato--r2">
                        <small>{{ codigo === "WATER_QUALITY_INDEX_ICA" ? "R2 de la regresion multiple" : "R2 frente a lo medido" }}</small>
                        <strong :class="'val__r2--' + juicio.tono">{{ fmt(r2Principal, 3) }}</strong>
                        <span :class="'val__r2--' + juicio.tono">{{ juicio.texto }}</span>
                    </div>
                    <div v-if="datos.mae !== undefined" class="val__dato">
                        <small>Error medio</small>
                        <strong>{{ fmt(datos.mae, 3) }} mg/L</strong>
                    </div>
                    <div v-if="datos.puntos_totales" class="val__dato">
                        <small>Puntos contrastados</small>
                        <strong>{{ fmt(datos.puntos_totales, 0) }}</strong>
                    </div>
                    <div v-if="datos.acierto_svm !== undefined && datos.acierto_svm !== null" class="val__dato">
                        <small>El SVM reproduce la clase</small>
                        <strong>{{ fmt(datos.acierto_svm * 100, 1) }}%</strong>
                    </div>
                </div>

                <p v-if="datos.formula" class="val__formula"><code>{{ datos.formula }}</code></p>
                <p v-if="datos.fuente" class="text-gray-500 fs-8 mb-4">{{ datos.fuente }}</p>

                <!-- proyeccion de oxigeno -->
                <template v-if="codigo === 'SVM_OD_FORECAST_1H'">
                    <ChartFisheye v-if="graficoProyeccion" :options="graficoProyeccion" height="380px" />
                    <div class="table-responsive mt-4">
                        <table class="table table-sm table-row-dashed align-middle mb-0">
                            <thead><tr class="text-gray-500 fs-8"><th>Metodo, sobre los mismos datos de prueba</th><th class="text-end">R2</th><th class="text-end">Error medio</th></tr></thead>
                            <tbody>
                                <tr v-for="(v, nombre) in datos.comparacion" :key="nombre">
                                    <td class="fs-7">{{ nombre }}</td>
                                    <td class="text-end fs-7 fw-bold">{{ fmt(v.r2, 3) }}</td>
                                    <td class="text-end fs-7">{{ fmt(v.mae, 3) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-gray-500 fs-8 mt-2 mb-0">{{ datos.reparto }}</p>
                </template>

                <!-- estado de oxigeno -->
                <template v-else-if="codigo === 'OXYGEN_STATUS_MODEL'">
                    <ChartFisheye v-if="graficoEstado" :options="graficoEstado" height="380px" />
                    <div class="val__pie">
                        <span>Saturacion media <strong>{{ fmt(datos.saturacion_media_pct, 1) }}%</strong></span>
                        <span>mediana <strong>{{ fmt(datos.saturacion_mediana_pct, 1) }}%</strong></span>
                        <span>correlacion con la temperatura <strong>{{ fmt(datos.recta?.pearson, 3) }}</strong></span>
                    </div>
                </template>

                <!-- crecimiento -->
                <template v-else-if="codigo === 'TILAPIA_GROWTH_TEMPERATURE'">
                    <ChartFisheye v-if="graficoCrecimiento" :options="graficoCrecimiento" height="380px" />
                    <div class="table-responsive mt-4">
                        <table class="table table-sm table-row-dashed align-middle mb-0">
                            <thead><tr class="text-gray-500 fs-8"><th>Intervalo entre biometrias</th><th class="text-end">Dias</th><th class="text-end">Temperatura media</th><th class="text-end">Medido</th><th class="text-end">Soderberg</th></tr></thead>
                            <tbody>
                                <tr v-for="i in datos.intervalos" :key="i.desde">
                                    <td class="fs-7">{{ i.desde }} a {{ i.hasta }}</td>
                                    <td class="text-end fs-7">{{ i.dias }}</td>
                                    <td class="text-end fs-7">{{ fmt(i.temperatura_media, 2) }} C</td>
                                    <td class="text-end fs-7 fw-bold">{{ fmt(i.observado_mm_dia, 3) }}</td>
                                    <td class="text-end fs-7">{{ fmt(i.soderberg_mm_dia, 3) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <p class="text-gray-500 fs-8 mt-2 mb-0">Crecimiento en mm por dia. Con solo {{ datos.intervalos?.length }} intervalos el R2 es orientativo.</p>
                </template>

                <!-- calidad de agua -->
                <template v-else-if="codigo === 'WATER_QUALITY_INDEX_ICA'">
                    <p class="val__sub">De que depende la calidad del agua</p>
                    <div class="val__rejilla">
                        <div v-for="g in graficosDependencia" :key="g.variable" class="val__mini">
                            <div class="val__mini-cab">
                                <span>{{ g.variable }}</span>
                                <strong :class="'val__r2--' + lecturaR2(g.recta.r2).tono">R2 {{ fmt(g.recta.r2, 3) }}</strong>
                            </div>
                            <ChartFisheye :options="g.opciones" height="200px" />
                        </div>
                    </div>

                    <p class="val__sub mt-6">Regresion lineal multiple ajustada con los datos</p>
                    <p class="val__formula"><code>{{ datos.regresion_multiple?.formula }}</code></p>
                    <ChartFisheye v-if="graficoRegresionMultiple" :options="graficoRegresionMultiple" height="320px" />
                    <p class="val__nota">{{ datos.origen_etiqueta }}</p>
                </template>

                <p class="val__lectura">{{ datos.lectura }}</p>
                <p v-if="limpieza" class="val__limpieza">
                    {{ fmt(limpieza.lecturas_limpias, 0) }} lecturas limpias de {{ fmt(limpieza.lecturas_crudas, 0) }}.
                    {{ limpieza.criterio }}
                </p>
            </template>
        </div>
    </section>
</template>

<style scoped>
.val__cab { display: flex; flex-direction: column; gap: 2px; padding: 4px 0; }
.val__eyebrow { font-size: 10px; font-weight: 700; letter-spacing: .06em; text-transform: uppercase; color: #6b7280; }
.val__resumen { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 14px; }
.val__dato { flex: 1 1 150px; background: #f8fafc; border: 1px solid #eef2f7; border-radius: 8px; padding: 10px 12px; }
.val__dato small { display: block; font-size: 10px; font-weight: 700; letter-spacing: .04em; text-transform: uppercase; color: #6b7280; margin-bottom: 3px; }
.val__dato strong { font-size: 14px; color: #1f2937; line-height: 1.35; }
.val__dato--r2 strong { font-size: 22px; display: block; }
.val__dato--r2 span { font-size: 11px; font-weight: 600; }
.val__r2--bien { color: #15803d; }
.val__r2--medio { color: #0e7490; }
.val__r2--flojo { color: #b45309; }
.val__r2--mal { color: #b91c1c; }
.val__r2--gris { color: #6b7280; }
.val__formula { margin: 0 0 4px; }
.val__formula code { font-size: 13px; color: #1f2937; background: #f1f5f9; padding: 4px 8px; border-radius: 5px; }
.val__sub { font-size: 12px; font-weight: 700; color: #374151; margin: 4px 0 10px; }
.val__rejilla { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
.val__mini { border: 1px solid #eef2f7; border-radius: 8px; padding: 8px 10px 2px; }
.val__mini-cab { display: flex; justify-content: space-between; align-items: baseline; font-size: 12px; color: #4b5563; }
.val__mini-cab strong { font-size: 12px; }
.val__pie { display: flex; flex-wrap: wrap; gap: 18px; font-size: 12px; color: #6b7280; margin-top: 8px; }
.val__pie strong { color: #1f2937; }
.val__lectura { font-size: 13px; line-height: 1.65; color: #374151; margin: 16px 0 6px; border-left: 3px solid #e5e7eb; padding-left: 12px; }
.val__nota { font-size: 12px; line-height: 1.6; color: #6b7280; margin: 10px 0 0; }
.val__limpieza { font-size: 11px; line-height: 1.55; color: #9ca3af; margin: 8px 0 0; }
@media (max-width: 900px) { .val__rejilla { grid-template-columns: 1fr; } }
</style>
