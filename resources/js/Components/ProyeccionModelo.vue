<script setup>
// Proyeccion de un modelo a los horizontes del modulo.
//
// Cada modelo proyecta a todos los horizontes que el modulo ofrecio desde el
// inicio, y al lado de cada proyeccion se muestra cuanto acerto ese mismo
// calculo en el pasado. Un plazo sin confiabilidad se dibuja igual, pero en
// gris y dicho, para que nadie tome una curva por una promesa.
import { computed, onMounted, ref, watch } from "vue";
import axios from "axios";
import ChartFisheye from "@/Components/ChartFisheye.vue";

const props = defineProps({
    codigo: { type: String, required: true },
    datos: { type: Object, default: null },
    historial: { type: Array, default: () => [] },
    numero: { type: [Number, String], default: 3 },
});

const AZUL = "#2563eb";
const NARANJA = "#ea580c";
const VERDE = "#0d9488";
const ROJO = "#dc2626";
const GRIS = "#94a3b8";
const MORADO = "#7c3aed";

const espacio = (t) => t.replace(/\u202f/g, "\u00a0");
const fmt = (v, d = 2) => (v === null || v === undefined || Number.isNaN(Number(v)) ? "-" : espacio(Number(v).toLocaleString("fr-FR", { maximumFractionDigits: d })));
const fmtFijo = (v, d = 2) => (v === null || v === undefined || Number.isNaN(Number(v)) ? "-" : espacio(Number(v).toLocaleString("fr-FR", { minimumFractionDigits: d, maximumFractionDigits: d })));
const fecha = (iso, conHora = false) => (iso ? new Date(iso.length <= 10 ? `${iso}T00:00:00` : iso).toLocaleString("es-PE", conHora ? { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" } : { day: "numeric", month: "short", year: "numeric" }) : "");
const etiquetaTiempo = (valor) => {
    const d = new Date(valor);
    if (d.getHours() === 0 && d.getMinutes() === 0) return d.toLocaleDateString("es-PE", { day: "numeric", month: "short" });
    return d.toLocaleTimeString("es-PE", { hour: "2-digit", minute: "2-digit" });
};

const ES_CRECIMIENTO = computed(() => props.codigo === "TILAPIA_GROWTH_TEMPERATURE");
const ES_CALIDAD = computed(() => props.codigo === "WATER_QUALITY_INDEX_ICA");
const proy = computed(() => props.datos?.horizontes ?? null);
const opciones = computed(() => proy.value?.opciones ?? []);
const seleccion = ref(null);
const variable = ref("peso");
watch(opciones, (lista) => {
    if (!lista.length) return;
    if (!lista.find((o) => o.id === seleccion.value)) seleccion.value = ES_CRECIMIENTO.value ? (lista.find((o) => o.id === "30d") ?? lista[0]).id : lista[0].id;
}, { immediate: true });
const opcion = computed(() => opciones.value.find((o) => o.id === seleccion.value) ?? null);
const confiable = computed(() => (proy.value?.confiabilidad ?? []).find((c) => c.id === seleccion.value) ?? null);
const noConfiable = computed(() => ["mal", "gris"].includes(juicio(confiable.value).tono));

const juicio = (c) => {
    if (!c || !c.comprobable) return { texto: "sin datos para comprobarlo todavía", tono: "gris" };
    if (c.acierto !== undefined) {
        if (c.acierto >= 0.8) return { texto: "confiable", tono: "bien" };
        if (c.acierto >= 0.6) return { texto: "orientativo", tono: "medio" };
        return { texto: "no confiable", tono: "mal" };
    }
    if (c.r2 >= 0.5) return { texto: "confiable", tono: "bien" };
    if (c.r2 >= 0.1) return { texto: "orientativo", tono: "medio" };
    if (c.r2 >= 0) return { texto: "apenas mejor que el promedio", tono: "flojo" };
    return { texto: "no confiable: acierta menos que el promedio", tono: "mal" };
};

const ejeTiempo = (nombreY, extra = {}) => ({
    grid: { top: 30, left: 58, right: 28, bottom: 64, containLabel: true },
    tooltip: { trigger: "axis" },
    legend: { bottom: 0, textStyle: { fontSize: 11 } },
    xAxis: { type: "time", splitLine: { show: false }, axisLabel: { color: "#6b7280", hideOverlap: true, formatter: etiquetaTiempo } },
    yAxis: { type: "value", name: nombreY, nameLocation: "middle", nameGap: 44, nameTextStyle: { color: "#4b5563", fontSize: 12, fontWeight: 600 }, axisLabel: { color: "#6b7280", formatter: (v) => fmt(v, 1) }, min: 0, splitLine: { lineStyle: { color: "#f1f5f9" } } },
    ...extra,
});

const horasHistorial = (horas) => (horas <= 1 ? 12 : horas <= 24 ? 48 : 168);
const recortarHistorial = (desdeIso, horas) => {
    if (!desdeIso) return props.historial;
    const limite = new Date(desdeIso).getTime() - horasHistorial(horas) * 3600 * 1000;
    return props.historial.filter((x) => new Date(x[0]).getTime() >= limite);
};

// ---------- oxigeno: gradient boosting por horizonte ----------
const graficoGb = computed(() => {
    const p = proy.value;
    if (!p?.puntos?.length || !opcion.value) return null;
    const horas = opcion.value.horas;
    const inicio = [p.desde, p.valor_inicial];
    const visibles = p.puntos.filter((x) => x.horas <= horas);
    const buenos = visibles.filter((x) => juicio(x.r2 === null ? null : { comprobable: true, r2: x.r2 }).tono !== "mal" && x.r2 !== null);
    const malos = visibles.filter((x) => !buenos.includes(x));
    const hist = recortarHistorial(p.desde, horas);
    return {
        ...ejeTiempo("Oxígeno disuelto (mg/L)"),
        tooltip: { trigger: "item", formatter: (q) => (q.data?.nota ? q.data.nota : `${q.seriesName}<br/>${fecha(q.value[0], true)}: ${fmt(q.value[1])} mg/L`) },
        series: [
            { name: "Oxígeno medido", type: "line", showSymbol: false, color: AZUL, lineStyle: { width: 2 }, data: hist.map((x) => [x[0], x[1]]) },
            { name: "Proyección", type: "line", color: NARANJA, lineStyle: { width: 2, type: "dashed" }, symbolSize: 1, data: [inicio, ...buenos.map((x) => [x.cuando, x.od_mg_l])] },
            ...(malos.length ? [{ name: "Valores que el modelo no sostiene", type: "line", color: GRIS, lineStyle: { width: 1.2, type: "dotted" }, symbolSize: 1, data: [buenos.length ? [buenos.at(-1).cuando, buenos.at(-1).od_mg_l] : inicio, ...malos.map((x) => [x.cuando, x.od_mg_l])] }] : []),
            {
                name: "Horizonte confiable",
                type: "scatter",
                symbolSize: 13,
                color: NARANJA,
                itemStyle: { borderColor: "#fff", borderWidth: 2 },
                data: buenos.map((x) => ({ value: [x.cuando, x.od_mg_l], nota: `${x.nombre}: ${fmt(x.od_mg_l)} mg/L<br/>error medio a ese plazo ±${fmt(x.error_medio)} mg/L, R² ${fmtFijo(x.r2)}` })),
            },
            {
                name: "Horizonte no confiable",
                type: "scatter",
                symbolSize: 13,
                color: GRIS,
                itemStyle: { color: "#fff", borderColor: GRIS, borderWidth: 2 },
                data: malos.map((x) => ({ value: [x.cuando, x.od_mg_l], nota: `${x.nombre}: ${fmt(x.od_mg_l)} mg/L<br/>no confiable: ${x.r2 === null ? "sin datos para comprobarlo" : `R² ${fmtFijo(x.r2)}, error medio ±${fmt(x.error_medio)} mg/L`}` })),
            },
            { name: "Umbral de alarma (3 mg/L)", type: "line", showSymbol: false, silent: true, color: ROJO, lineStyle: { width: 1, type: "dotted" }, data: [[hist[0]?.[0] ?? p.desde, 3], [visibles.at(-1)?.cuando ?? p.desde, 3]] },
        ],
    };
});

// ---------- oxigeno: ciclo diario ----------
const serieHasta = (serie, desdeIso, horas) => {
    const limite = new Date(desdeIso).getTime() + horas * 3600 * 1000;
    return (serie ?? []).filter((x) => new Date(x[0]).getTime() <= limite);
};
const graficoDiario = computed(() => {
    const p = proy.value;
    if (!p?.serie?.length || !opcion.value) return null;
    const horas = opcion.value.horas;
    const tramo = serieHasta(p.serie, p.desde, horas);
    const error = confiable.value?.mae ?? 0;
    const hist = recortarHistorial(p.desde, horas);
    const margen = `Margen de error (±${fmt(error)} mg/L)`;
    return {
        ...ejeTiempo("Oxígeno disuelto (mg/L)"),
        legend: { bottom: 0, textStyle: { fontSize: 11 }, data: ["Oxígeno medido", "Proyección", margen, "Umbral de alarma (3 mg/L)"] },
        tooltip: { trigger: "item", formatter: (q) => `${q.seriesName}<br/>${fecha(q.value[0], true)}: ${fmt(q.value[1])} mg/L` },
        series: [
            { name: "Oxígeno medido", type: "line", showSymbol: false, color: AZUL, lineStyle: { width: 2 }, data: hist.map((x) => [x[0], x[1]]) },
            { name: "Límite inferior", type: "line", showSymbol: false, silent: true, color: NARANJA, lineStyle: { opacity: 0 }, stack: "banda", data: tramo.map((x) => [x[0], Math.max(0, x[1] - error)]) },
            { name: margen, type: "line", showSymbol: false, silent: true, color: NARANJA, lineStyle: { opacity: 0 }, areaStyle: { color: NARANJA, opacity: 0.12 }, stack: "banda", data: tramo.map((x) => [x[0], x[1] + error - Math.max(0, x[1] - error)]) },
            { name: "Proyección", type: "line", showSymbol: false, color: NARANJA, lineStyle: { width: 2.2 }, data: [[p.desde, p.valor_inicial], ...tramo] },
            { name: "Umbral de alarma (3 mg/L)", type: "line", showSymbol: false, silent: true, color: ROJO, lineStyle: { width: 1, type: "dotted" }, data: [[hist[0]?.[0] ?? p.desde, 3], [tramo.at(-1)?.[0] ?? p.desde, 3]] },
        ],
    };
});
const extremoDiario = computed(() => {
    const p = proy.value;
    if (!p?.serie?.length || !opcion.value || ES_CRECIMIENTO.value || ES_CALIDAD.value || props.codigo === "SVM_OD_FORECAST_1H") return null;
    const tramo = serieHasta(p.serie, p.desde, opcion.value.horas);
    if (!tramo.length) return null;
    const minimo = tramo.reduce((a, b) => (b[1] < a[1] ? b : a));
    const horasBajo3 = tramo.filter((x) => x[1] < 3).length;
    return { minimo, horasBajo3, final: tramo.at(-1) };
});

// ---------- calidad de agua ----------
const graficoCalidad = computed(() => {
    const p = proy.value;
    if (!p?.serie?.length || !opcion.value) return null;
    const horas = opcion.value.horas;
    const tramo = serieHasta(p.serie, p.desde, horas);
    const entradas = serieHasta(p.entradas, p.desde, horas);
    const orden = [...(p.orden_clases ?? [])].reverse();
    return {
        grid: [
            { top: 24, left: 58, right: 28, height: "34%", containLabel: true },
            { top: "52%", left: 58, right: 28, bottom: 64, containLabel: true },
        ],
        tooltip: { trigger: "axis" },
        legend: { bottom: 0, textStyle: { fontSize: 11 } },
        xAxis: [
            { type: "time", gridIndex: 0, axisLabel: { show: false }, splitLine: { show: false } },
            { type: "time", gridIndex: 1, axisLabel: { color: "#6b7280", hideOverlap: true, formatter: etiquetaTiempo }, splitLine: { show: false } },
        ],
        yAxis: [
            { type: "category", gridIndex: 0, data: orden, axisLabel: { color: "#4b5563" }, splitLine: { show: true, lineStyle: { color: "#f1f5f9" } } },
            { type: "value", gridIndex: 1, min: 0, splitNumber: 4, name: "mg/L y °C", nameLocation: "middle", nameGap: 36, nameTextStyle: { color: "#4b5563", fontSize: 12, fontWeight: 600 }, axisLabel: { color: "#6b7280" }, splitLine: { lineStyle: { color: "#f1f5f9" } } },
        ],
        series: [
            { name: "Clase proyectada por el SVM", type: "line", step: "end", showSymbol: false, xAxisIndex: 0, yAxisIndex: 0, color: VERDE, lineStyle: { width: 2.2 }, data: tramo },
            { name: "Oxígeno proyectado (mg/L)", type: "line", showSymbol: false, xAxisIndex: 1, yAxisIndex: 1, color: AZUL, lineStyle: { width: 1.8 }, data: entradas.map((x) => [x[0], x[2]]) },
            { name: "Temperatura proyectada (°C)", type: "line", showSymbol: false, xAxisIndex: 1, yAxisIndex: 1, color: NARANJA, lineStyle: { width: 1.5, type: "dashed" }, data: entradas.map((x) => [x[0], x[1]]) },
        ],
    };
});
const repartoClases = computed(() => {
    const p = proy.value;
    if (!ES_CALIDAD.value || !p?.serie?.length || !opcion.value) return [];
    const tramo = serieHasta(p.serie, p.desde, opcion.value.horas);
    const cuenta = {};
    tramo.forEach((x) => { cuenta[x[1]] = (cuenta[x[1]] ?? 0) + 1; });
    return (p.orden_clases ?? []).filter((c) => cuenta[c]).map((c) => ({ clase: c, horas: cuenta[c], pct: (100 * cuenta[c]) / tramo.length }));
});


// ---------- crecimiento: modelos ML del nucleo SISMAPISCIS ----------
// Ridge y proceso gaussiano estiman el peso medio al cierre de cada intervalo
// entre biometrias. Se agregan a la grafica del ciclo sin cambiar lo demas.
const ml = ref(null);
onMounted(async () => {
    if (!ES_CRECIMIENTO.value) return;
    try {
        const { data } = await axios.get("/modelos-ml/crecimiento", { timeout: 30000 });
        if (data?.disponible) ml.value = data;
    } catch (e) {
        ml.value = null;
    }
});
const ML_RIDGE = "#2563eb";
const ML_GP = "#db2777";
const ML_NOMBRE = { ridge: "Ridge", gp: "GP" };
const mlActual = computed(() => {
    const m = ml.value;
    if (!m) return [];
    const ia = m.intervalo_actual;
    return ["ridge", "gp"].map((k) => {
        const mod = m.modelos.find((x) => x.clave === k);
        return {
            clave: k,
            nombre: mod?.nombre ?? k,
            color: k === "ridge" ? ML_RIDGE : ML_GP,
            mae: mod?.mae_g,
            desde: ia?.desde?.slice(0, 10),
            hasta: ia?.hasta?.slice(0, 10),
            admisible: !!ia?.admisible,
            peso: ia?.laravel?.[k]?.predicted_weight_g ?? null,
            sd: ia?.laravel?.[k]?.conditional_latent_gain_sd_g ?? null,
        };
    });
});
const seriesMl = () => {
    const m = ml.value;
    if (!m || variable.value !== "peso") return [];
    const pts = (k) => [
        ...m.evaluacion.filter((r) => r[k]).map((r) => ({ value: [r.fecha, r[k].peso_g], name: `${ML_NOMBRE[k]}, estimado al cierre del intervalo (medido ${fmt(r.peso_observado_g, 1)} g)` })),
        ...mlActual.value.filter((a) => a.clave === k && a.admisible && a.peso !== null).map((a) => ({ value: [a.hasta, a.peso], name: `${ML_NOMBRE[k]}, intervalo en curso`, symbolSize: 17 })),
    ];
    return [
        { name: "ML Ridge: Modelo SISMAPISCIS de incremento de peso", type: "scatter", symbol: "diamond", symbolSize: 13, color: ML_RIDGE, itemStyle: { borderColor: "#fff", borderWidth: 1 }, data: pts("ridge"), z: 5 },
        { name: "ML GP: Modelo probabilístico SISMAPISCIS", type: "scatter", symbol: "triangle", symbolSize: 13, color: ML_GP, itemStyle: { borderColor: "#fff", borderWidth: 1 }, data: pts("gp"), z: 5 },
    ];
};

// ---------- crecimiento: el ciclo completo ----------
const indicePorFecha = (serie) => Object.fromEntries((serie ?? []).map((x) => [x[0], x]));
const valorEn = (serie, iso, columna) => indicePorFecha(serie)[iso]?.[columna] ?? null;
const COLOR_ESCENARIO = { reciente: NARANJA, sin_limite: VERDE, peor_mes: ROJO };
const graficoCiclo = computed(() => {
    const p = proy.value;
    const d = props.datos;
    if (!p?.escenarios?.length || !opcion.value) return null;
    const col = variable.value === "peso" ? 1 : 2;
    const unidad = variable.value === "peso" ? "g" : "mm";
    const hasta = new Date(`${opcion.value.hasta}T00:00:00`).getTime();
    const ciclo = p.ciclo ?? {};
    const finCiclo = ciclo.fin_ciclo_referencia ? new Date(`${ciclo.fin_ciclo_referencia}T00:00:00`).getTime() : hasta;
    const limite = Math.max(hasta, opcion.value.id === "ciclo" ? finCiclo : hasta);
    const recorte = (serie) => serie.filter((x) => new Date(`${x[0]}T00:00:00`).getTime() <= limite);
    const medidas = (d?.medias ?? []).map((m) => [m.fecha, variable.value === "peso" ? m.peso_g : m.talla_mm, m.medido]);
    const modeloDesdeSiembra = (variable.value === "peso" ? d?.serie_peso_modelo : d?.serie_talla_modelo) ?? [];
    const referencia = (ciclo.referencia ?? []).map((r) => ({ value: [r.fecha, variable.value === "peso" ? r.peso_g : r.talla_mm], name: `Día ${r.dia}: ${r.fase}` }));
    const marcas = [
        { xAxis: p.hoy, name: "Hoy", lineStyle: { color: "#111827", type: "solid" } },
        ...(ciclo.fin_campania ? [{ xAxis: ciclo.fin_campania, name: "Fin estimado de la campaña", lineStyle: { color: GRIS } }] : []),
        ...(ciclo.fin_ciclo_referencia ? [{ xAxis: ciclo.fin_ciclo_referencia, name: "Día 249, fin del ciclo de referencia", lineStyle: { color: MORADO } }] : []),
    ];
    const horizontales = variable.value === "peso" && ciclo.peso_final_objetivo_g ? [{ yAxis: ciclo.peso_final_objetivo_g, name: `Objetivo de la campaña: ${fmt(ciclo.peso_final_objetivo_g, 0)} g`, lineStyle: { color: "#16a34a" } }] : [];
    return {
        ...ejeTiempo(variable.value === "peso" ? "Peso del pez (g)" : "Talla del pez (mm)"),
        grid: { top: 36, left: 58, right: 36, bottom: ml.value && variable.value === "peso" ? 104 : 84, containLabel: true },
        xAxis: { type: "time", min: ciclo.siembra ?? undefined, max: limite, splitLine: { show: false }, axisLabel: { color: "#6b7280", hideOverlap: true, formatter: (v) => new Date(v).toLocaleDateString("es-PE", { day: "numeric", month: "short" }) } },
        tooltip: { trigger: "item", formatter: (q) => (q.name && q.seriesType === "scatter" ? `${q.name}<br/>${fecha(q.value[0])}: ${fmt(q.value[1], 1)} ${unidad}` : `${q.seriesName}<br/>${fecha(q.value?.[0])}: ${fmt(q.value?.[1], 1)} ${unidad}`) },
        series: [
            { name: "Ciclo de referencia, Informe 18", type: "line", color: MORADO, symbol: "circle", symbolSize: 7, lineStyle: { width: 1.5, type: "dashed" }, data: referencia },
            { name: "Modelo desde la siembra", type: "line", showSymbol: false, color: "#64748b", lineStyle: { width: 1.5 }, data: modeloDesdeSiembra },
            ...p.escenarios.map((e) => ({
                name: e.nombre,
                type: "line",
                showSymbol: false,
                color: COLOR_ESCENARIO[e.id] ?? AZUL,
                lineStyle: { width: e.id === "reciente" ? 2.8 : 1.8, type: e.id === "reciente" ? "solid" : "dashed" },
                data: recorte(e.serie).map((x) => [x[0], x[col]]),
                markLine: e.id === "reciente" ? { silent: true, symbol: "none", label: { fontSize: 10, formatter: "{b}", position: "insideEndTop" }, data: [...marcas, ...horizontales] } : undefined,
            })),
            ...seriesMl(),
            { name: "Biometría medida", type: "scatter", symbolSize: 12, color: "#111827", itemStyle: { borderColor: "#fff", borderWidth: 2 }, data: medidas.filter((m) => m[2]).map((m) => ({ value: [m[0], m[1]], name: "Biometría" })) },
            { name: "Siembra registrada", type: "scatter", symbol: "diamond", symbolSize: 13, color: "#16a34a", data: medidas.filter((m) => !m[2]).map((m) => ({ value: [m[0], m[1]], name: `Siembra: ${ciclo.alevines ? fmt(ciclo.alevines, 0) + " alevines de " : ""}${fmt(ciclo.peso_siembra_g, 1)} g` })) },
        ],
    };
});

const referenciaEnDia = (dia) => {
    const r = proy.value?.ciclo?.referencia ?? [];
    if (!r.length) return null;
    const campo = variable.value === "peso" ? "peso_g" : "talla_mm";
    if (dia <= r[0].dia) return r[0][campo];
    for (let i = 1; i < r.length; i++) {
        if (dia <= r[i].dia) {
            const a = r[i - 1]; const b = r[i];
            if (campo === "peso_g") return Math.exp(Math.log(a[campo]) + ((dia - a.dia) / (b.dia - a.dia)) * (Math.log(b[campo]) - Math.log(a[campo])));
            return a[campo] + ((dia - a.dia) / (b.dia - a.dia)) * (b[campo] - a[campo]);
        }
    }
    return null;
};
const faseEnDia = (dia) => {
    const r = proy.value?.ciclo?.referencia ?? [];
    const fases = ["Alevinaje", "Crecimiento 1", "Crecimiento 2", "Finalización"];
    for (let i = 1; i < r.length; i++) if (dia <= r[i].dia) return `fase de ${fases[i - 1].toLowerCase()}`;
    return "después del día 249 del ciclo de referencia";
};
const resumenCiclo = computed(() => {
    const p = proy.value;
    if (!ES_CRECIMIENTO.value || !p?.escenarios?.length || !opcion.value) return null;
    const siembra = p.ciclo?.siembra ? new Date(`${p.ciclo.siembra}T00:00:00`) : null;
    const destino = new Date(`${opcion.value.hasta}T00:00:00`);
    const dia = siembra ? Math.round((destino - siembra) / 86400000) : null;
    const col = variable.value === "peso" ? 1 : 2;
    return {
        dia,
        fase: dia !== null ? faseEnDia(dia) : null,
        referencia: dia !== null ? referenciaEnDia(dia) : null,
        escenarios: p.escenarios.map((e) => ({ id: e.id, nombre: e.nombre, delta: e.delta, valor: valorEn(e.serie, opcion.value.hasta, col) })),
        hoy: valorEn(p.escenarios[0].serie, p.hoy, col),
    };
});
</script>

<template>
    <section class="card card-flush mb-6 pro">
        <div class="card-header">
            <div class="pro__cab">
                <h4 class="card-title fw-bold text-dark fs-5 mb-0"><span class="pro__num">{{ numero }}</span>Proyección</h4>
                <span class="pro__sub">{{ ES_CRECIMIENTO ? "Peso y talla del lote en los próximos días y en el ciclo completo" : "Qué espera el modelo en cada horizonte, y cuánto se puede confiar en ese plazo" }}</span>
            </div>
            <div v-if="opciones.length" class="card-toolbar pro__controles">
                <el-radio-group v-if="ES_CRECIMIENTO" v-model="variable" size="small">
                    <el-radio-button label="peso">Peso</el-radio-button>
                    <el-radio-button label="talla">Talla</el-radio-button>
                </el-radio-group>
                <el-select v-model="seleccion" class="pro__select">
                    <el-option v-for="o in opciones" :key="o.id" :label="o.nombre" :value="o.id" />
                </el-select>
            </div>
        </div>
        <div class="card-body pt-0">
            <p v-if="!datos" class="text-gray-500 fs-7">Calculando la proyección...</p>
            <p v-else-if="!proy" class="text-gray-500 fs-7">Este modelo no tiene datos suficientes para proyectar.</p>

            <!-- oxigeno y calidad de agua -->
            <template v-else-if="!ES_CRECIMIENTO">
                <div class="pro__resumen">
                    <div v-if="codigo === 'SVM_OD_FORECAST_1H'" :class="['pro__dato', noConfiable ? 'pro__dato--apagado' : 'pro__dato--fuerte']">
                        <small>Oxígeno a {{ opcion?.nombre }}</small>
                        <template v-if="!noConfiable">
                            <strong>{{ fmt(proy.puntos?.find((x) => x.id === seleccion)?.od_mg_l) }} <em>mg/L</em></strong>
                            <span>desde {{ fmt(proy.valor_inicial) }} mg/L del {{ fecha(proy.desde, true) }}</span>
                        </template>
                        <template v-else>
                            <strong class="pro__tono--mal">Sin proyección confiable</strong>
                            <span>El modelo daría {{ fmt(proy.puntos?.find((x) => x.id === seleccion)?.od_mg_l) }} mg/L, pero a este plazo {{ confiable?.comprobable ? "acierta menos que el promedio" : "todavía no hay datos para comprobarlo" }}.<template v-if="[24, 72].includes(opcion?.horas)"> Para este plazo es más confiable la proyección del estado de oxígeno disuelto.</template></span>
                        </template>
                    </div>
                    <template v-else-if="extremoDiario">
                        <div :class="['pro__dato', noConfiable ? 'pro__dato--apagado' : 'pro__dato--fuerte']">
                            <small>Mínimo en {{ opcion?.nombre }}</small>
                            <strong>{{ fmt(extremoDiario.minimo[1]) }} <em>mg/L</em></strong>
                            <span>hacia el {{ fecha(extremoDiario.minimo[0], true) }}<template v-if="noConfiable">; a este plazo tómalo solo como referencia</template></span>
                        </div>
                        <div class="pro__dato">
                            <small>Horas bajo 3 mg/L</small>
                            <strong>{{ extremoDiario.horasBajo3 }}</strong>
                            <span>de {{ opcion?.horas }} horas proyectadas</span>
                        </div>
                    </template>
                    <div v-else-if="ES_CALIDAD" class="pro__dato pro__dato--fuerte">
                        <small>Clases en {{ opcion?.nombre }}</small>
                        <strong class="pro__clases">
                            <span v-for="c in repartoClases" :key="c.clase">{{ c.clase }} {{ fmt(c.pct, 0) }} %</span>
                        </strong>
                        <span>pH {{ fmt(proy.entradas_fijas?.ph) }} y nitrato {{ fmt(proy.entradas_fijas?.nitrato_mg_l) }} mg/L se mantienen</span>
                    </div>
                    <div class="pro__dato">
                        <small>Confiabilidad a {{ opcion?.nombre }}</small>
                        <strong :class="'pro__tono--' + juicio(confiable).tono">
                            <template v-if="confiable?.comprobable">{{ confiable.acierto !== undefined ? fmt(confiable.acierto * 100, 0) + " % de acierto" : "R² " + fmtFijo(confiable.r2) }}</template>
                            <template v-else>-</template>
                        </strong>
                        <span :class="'pro__tono--' + juicio(confiable).tono">{{ juicio(confiable).texto }}</span>
                    </div>
                    <div v-if="confiable?.mae !== undefined && confiable?.mae !== null" class="pro__dato">
                        <small>Error medio a ese plazo</small>
                        <strong>± {{ fmt(confiable.mae) }} <em>mg/L</em></strong>
                        <span>{{ confiable.casos }} casos comprobados</span>
                    </div>
                </div>

                <ChartFisheye v-if="codigo === 'SVM_OD_FORECAST_1H' && graficoGb" :options="graficoGb" height="380px" />
                <ChartFisheye v-else-if="codigo === 'OXYGEN_STATUS_MODEL' && graficoDiario" :options="graficoDiario" height="380px" />
                <ChartFisheye v-else-if="ES_CALIDAD && graficoCalidad" :options="graficoCalidad" height="480px" />

                <div class="table-responsive mt-4">
                    <table class="table table-sm table-row-dashed align-middle mb-0">
                        <thead>
                            <tr class="text-gray-500 fs-8">
                                <th>Horizonte</th>
                                <th class="text-end">{{ ES_CALIDAD ? "Acierto de la clase" : "R²" }}</th>
                                <th v-if="!ES_CALIDAD" class="text-end">Error medio</th>
                                <th class="text-end">Casos comprobados</th>
                                <th>Lectura</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="c in proy.confiabilidad" :key="c.id" :class="{ 'pro__fila-activa': c.id === seleccion }" @click="seleccion = c.id">
                                <td class="fs-7 fw-semibold">{{ c.nombre }}</td>
                                <td class="text-end fs-7">{{ c.comprobable ? (ES_CALIDAD ? fmt(c.acierto * 100, 0) + " %" : fmtFijo(c.r2)) : "-" }}</td>
                                <td v-if="!ES_CALIDAD" class="text-end fs-7">{{ c.mae === null ? "-" : "± " + fmt(c.mae) + " mg/L" }}</td>
                                <td class="text-end fs-7">{{ c.casos }}</td>
                                <td class="fs-7" :class="'pro__tono--' + juicio(c).tono">{{ juicio(c).texto }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="pro__nota">{{ proy.supuestos }}</p>
            </template>

            <!-- crecimiento -->
            <template v-else>
                <div class="pro__resumen">
                    <div class="pro__dato">
                        <small>Siembra registrada</small>
                        <strong>{{ fmt(proy.ciclo?.peso_siembra_g, 1) }} <em>g</em></strong>
                        <span>{{ proy.ciclo?.alevines ? fmt(proy.ciclo.alevines, 0) + " alevines, " : "" }}{{ fecha(proy.ciclo?.siembra) }}</span>
                    </div>
                    <div class="pro__dato">
                        <small>Hoy, según el modelo</small>
                        <strong>{{ fmt(resumenCiclo?.hoy, 1) }} <em>{{ variable === "peso" ? "g" : "mm" }}</em></strong>
                        <span>desde {{ fmt(proy.peso_desde_g, 1) }} g de la biometría del {{ fecha(proy.desde_biometria) }}</span>
                    </div>
                    <div class="pro__dato pro__dato--fuerte">
                        <small>{{ opcion?.nombre }}: {{ fecha(opcion?.hasta) }}</small>
                        <strong>{{ fmt(resumenCiclo?.escenarios?.[0]?.valor, 1) }} <em>{{ variable === "peso" ? "g" : "mm" }}</em></strong>
                        <span>día {{ resumenCiclo?.dia }} desde la siembra, {{ resumenCiclo?.fase }}</span>
                    </div>
                    <div class="pro__dato">
                        <small>Referencia del Informe 18 ese día</small>
                        <strong>{{ fmt(resumenCiclo?.referencia, variable === "peso" ? 0 : 0) }} <em>{{ variable === "peso" ? "g" : "mm" }}</em></strong>
                        <span v-if="proy.ciclo?.peso_final_objetivo_g && variable === 'peso'">objetivo de la campaña: {{ fmt(proy.ciclo.peso_final_objetivo_g, 0) }} g</span>
                    </div>
                </div>

                <ChartFisheye v-if="graficoCiclo" :options="graficoCiclo" height="460px" />

                <div class="table-responsive mt-4">
                    <table class="table table-sm table-row-dashed align-middle mb-0">
                        <thead><tr class="text-gray-500 fs-8"><th>Escenario de oxígeno desde hoy</th><th class="text-end">δ supuesto</th><th class="text-end">{{ variable === "peso" ? "Peso" : "Talla" }} al {{ fecha(opcion?.hasta) }}</th></tr></thead>
                        <tbody>
                            <tr v-for="(e, i) in resumenCiclo?.escenarios ?? []" :key="e.id" :class="{ 'pro__fila-activa': i === 0 }">
                                <td class="fs-7"><span class="pro__punto" :style="{ background: COLOR_ESCENARIO[e.id] }"></span>{{ e.nombre }}</td>
                                <td class="text-end fs-7">{{ fmtFijo(e.delta) }}</td>
                                <td class="text-end fs-7 fw-bold">{{ fmt(e.valor, 1) }} {{ variable === "peso" ? "g" : "mm" }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>


                <div v-if="mlActual.length && variable === 'peso'" class="table-responsive mt-4">
                    <table class="table table-sm table-row-dashed align-middle mb-0">
                        <thead><tr class="text-gray-500 fs-8"><th>Modelos ML de incremento de peso</th><th>Intervalo en curso</th><th class="text-end">Peso estimado al cierre</th><th class="text-end">Error medio (4 fechas)</th></tr></thead>
                        <tbody>
                            <tr v-for="a in mlActual" :key="a.clave">
                                <td class="fs-7"><span class="pro__punto" :style="{ background: a.color }"></span>{{ a.nombre }}</td>
                                <td class="fs-7">{{ fecha(a.desde) }} – {{ fecha(a.hasta) }}</td>
                                <td class="text-end fs-7 fw-bold">
                                    <template v-if="a.admisible && a.peso !== null">{{ fmt(a.peso, 1) }} g<span v-if="a.sd" class="text-gray-500 fw-normal"> ± {{ fmt(a.sd, 1) }}</span></template>
                                    <span v-else class="pro__tono--gris fw-normal">sin estimación: cobertura de sensores insuficiente</span>
                                </td>
                                <td class="text-end fs-7">{{ fmt(a.mae, 2) }} g</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <p class="pro__titulo mt-6">Cuánto acertó proyectar desde una biometría hasta las siguientes</p>
                <div class="table-responsive">
                    <table class="table table-sm table-row-dashed align-middle mb-0">
                        <thead><tr class="text-gray-500 fs-8"><th>Desde</th><th>Hasta</th><th class="text-end">Días</th><th class="text-end">Medido</th><th class="text-end">Proyectado</th><th class="text-end">Error</th><th>Oxígeno del tramo</th></tr></thead>
                        <tbody>
                            <tr v-for="x in proy.comprobacion" :key="x.desde + x.hasta">
                                <td class="fs-7">{{ fecha(x.desde) }}</td>
                                <td class="fs-7">{{ fecha(x.hasta) }}</td>
                                <td class="text-end fs-7">{{ x.dias }}</td>
                                <td class="text-end fs-7">{{ fmt(x.medido_g, 1) }} g</td>
                                <td class="text-end fs-7 fw-bold">{{ fmt(x.predicho_g, 1) }} g</td>
                                <td class="text-end fs-7" :class="Math.abs(x.error_pct) > 20 ? 'pro__tono--mal' : Math.abs(x.error_pct) > 10 ? 'pro__tono--flojo' : 'pro__tono--bien'">{{ x.error_pct > 0 ? "+" : "" }}{{ fmt(x.error_pct, 1) }} %</td>
                                <td class="fs-7 text-gray-600">{{ x.oxigeno_medido ? "medido" : "sin lecturas válidas (δ = 1)" }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <p class="pro__nota">
                    {{ proy.supuestos }}
                    El ciclo de referencia es el ciclo comercial de tilapia del Informe 18 (Aguilar et al., 2010): alevines de 0,8 g, alevinaje hasta el día 45, crecimiento 1 hasta el 77, crecimiento 2 hasta el 177 y finalización hasta el 249, con alimento a saciedad y agua controlada.
                    <template v-if="proy.ciclo?.fin_campania"> La campaña «{{ proy.ciclo.campania }}» registra la siembra del {{ fecha(proy.ciclo.siembra) }} y fin estimado el {{ fecha(proy.ciclo.fin_campania) }}.</template>
                </p>
            </template>
        </div>
    </section>
</template>

<style scoped>
.pro__cab { display: flex; flex-direction: column; gap: 4px; padding: 6px 0; }
.pro__num { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: #111827; color: #fff; font-size: 12px; margin-right: 10px; }
.pro__sub { font-size: 13px; color: #6b7280; padding-left: 32px; }
.pro__controles { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
.pro__select { width: 240px; max-width: 100%; }
.pro__resumen { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 12px; margin-bottom: 14px; }
.pro__dato { border: 1px solid #eef2f7; border-radius: 10px; padding: 12px 14px; background: #fff; }
.pro__dato small { display: block; font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
.pro__dato strong { display: block; font-size: 20px; font-weight: 700; color: #111827; line-height: 1.3; }
.pro__dato strong em { font-style: normal; font-size: 12px; font-weight: 600; color: #6b7280; }
.pro__dato span { display: block; font-size: 12px; color: #6b7280; margin-top: 2px; }
.pro__dato--fuerte { border-color: #fed7aa; background: #fff7ed; }
.pro__dato--apagado { background: #f8fafc; }
.pro__dato--apagado strong:not(.pro__tono--mal) { color: #6b7280; }
.pro__clases { display: flex; flex-wrap: wrap; gap: 4px 10px; font-size: 14px !important; }
.pro__tono--bien { color: #15803d !important; }
.pro__tono--medio { color: #0e7490 !important; }
.pro__tono--flojo { color: #b45309 !important; }
.pro__tono--mal { color: #b91c1c !important; }
.pro__tono--gris { color: #6b7280 !important; }
.pro__fila-activa td { background: #fff7ed; }
.pro__punto { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 8px; vertical-align: middle; }
.pro__titulo { font-size: 13px; font-weight: 700; color: #374151; margin: 4px 0 6px; }
.pro__nota { font-size: 12px; line-height: 1.6; color: #6b7280; margin: 10px 0 0; }
tbody tr { cursor: pointer; }
</style>
