<script setup>
import { computed, onMounted, ref } from "vue";
import axios from "axios";
import ChartFisheye from "@/Components/ChartFisheye.vue";
import { RefreshRight } from "@element-plus/icons-vue";

// Nucleo de crecimiento del informe integrado: Modelo SISMAPISCIS de
// incremento de peso (Ridge) y Modelo probabilistico SISMAPISCIS (GP).
const datos = ref(null);
const loading = ref(false);
const entradas = ref({ initial_weight_g: null, duration_days: null, temperature_mean: null, oxygen_mean: null, oxygen_p10: null });
const inferencia = ref(null);
const infiriendo = ref(false);
const errorInferencia = ref("");

const RIDGE = "#2563eb";
const GP = "#7c3aed";
const OBS = "#0f766e";

const fmt = (v, d = 2) => (v === null || v === undefined || Number.isNaN(Number(v))
    ? "-"
    : Number(v).toLocaleString("es-PE", { minimumFractionDigits: d, maximumFractionDigits: d }));
const pct = (v) => (v === null || v === undefined ? "-" : `${fmt(Number(v) * 100, 1)} %`);
const fecha = (s) => {
    if (!s) return "-";
    const [y, m, d] = String(s).slice(0, 10).split("-");
    const h = String(s).length > 10 ? ` ${String(s).slice(11, 16)}` : "";
    return `${d}/${m}/${y}${h}`;
};
const etiqueta = {
    initial_weight_g: "Peso inicial",
    duration_days: "Duración",
    temperature_mean: "T media",
    oxygen_mean: "OD medio",
    oxygen_p10: "OD P10",
};

const cargar = async () => {
    loading.value = true;
    try {
        const { data } = await axios.get("/modelos-ml/crecimiento", { timeout: 30000 });
        datos.value = data;
        const f = data?.intervalo_actual?.entradas;
        if (f) {
            entradas.value = {
                initial_weight_g: Number(f.initial_weight_g?.toFixed(2)),
                duration_days: Number(f.duration_days?.toFixed(1)),
                temperature_mean: Number(f.temperature_mean?.toFixed(2)),
                oxygen_mean: Number(f.oxygen_mean?.toFixed(2)),
                oxygen_p10: Number(f.oxygen_p10?.toFixed(2)),
            };
        }
    } catch (e) {
        datos.value = { disponible: false, motivo: "No se pudo leer el núcleo de crecimiento." };
    } finally {
        loading.value = false;
    }
};

const inferir = async () => {
    infiriendo.value = true;
    errorInferencia.value = "";
    try {
        const { data } = await axios.post("/modelos-ml/crecimiento/inferir", entradas.value, { timeout: 20000 });
        inferencia.value = data;
    } catch (e) {
        errorInferencia.value = e?.response?.data?.message ?? "No se pudo calcular la estimación.";
    } finally {
        infiriendo.value = false;
    }
};

onMounted(cargar);

const modelo = (clave) => datos.value?.modelos?.find((m) => m.clave === clave) ?? null;
const principales = computed(() => ["ridge", "gp"].map(modelo).filter(Boolean));
const ia = computed(() => datos.value?.intervalo_actual ?? null);

const grafico = computed(() => {
    const d = datos.value;
    if (!d?.disponible) return null;
    const t = (s) => new Date(`${s}T00:00:00`).getTime();
    const puntos = (clave) => d.evaluacion.filter((r) => r[clave]).map((r) => ({
        value: [t(r.fecha), r[clave].peso_g],
        error: r[clave].error_g,
        sd: r[clave].sd_g,
    }));
    const series = [
        {
            name: "Peso medio observado (biometrías)",
            type: "line",
            data: d.biometrias.map((b) => [t(b.fecha), b.peso_g]),
            symbolSize: 9,
            lineStyle: { width: 2, color: OBS },
            itemStyle: { color: OBS },
        },
        { name: "Modelo SISMAPISCIS de incremento de peso (Ridge)", type: "scatter", symbol: "diamond", symbolSize: 15, itemStyle: { color: RIDGE }, data: puntos("ridge") },
        { name: "Modelo probabilístico SISMAPISCIS (GP)", type: "scatter", symbol: "triangle", symbolSize: 14, itemStyle: { color: GP }, data: puntos("gp") },
    ];
    const ac = ia.value;
    if (ac?.admisible && ac.laravel?.ridge) {
        series.push({
            name: "Estimación del intervalo en curso",
            type: "scatter",
            symbol: "circle",
            symbolSize: 13,
            itemStyle: { color: "#f59e0b" },
            data: [
                { value: [new Date(ac.hasta.replace(" ", "T")).getTime(), ac.laravel.ridge.predicted_weight_g], name: "Ridge" },
                { value: [new Date(ac.hasta.replace(" ", "T")).getTime(), ac.laravel.gp.predicted_weight_g], name: "GP" },
            ],
        });
    }
    return {
        grid: { top: 28, left: 58, right: 28, bottom: 70, containLabel: true },
        legend: { bottom: 0, textStyle: { fontSize: 11 } },
        tooltip: {
            trigger: "item",
            formatter: (p) => {
                const dia = new Date(p.value[0]).toLocaleDateString("es-PE");
                let s = `${p.seriesName}${p.name ? ` · ${p.name}` : ""}<br/>${dia}: <b>${fmt(p.value[1], 2)} g</b>`;
                if (p.data?.error !== undefined) s += `<br/>Error absoluto: ${fmt(p.data.error, 2)} g`;
                if (p.data?.sd) s += `<br/>DE condicional: ${fmt(p.data.sd, 2)} g`;
                return s;
            },
        },
        xAxis: { type: "time", axisLabel: { color: "#64748b", formatter: (v) => new Date(v).toLocaleDateString("es-PE", { day: "2-digit", month: "2-digit" }) }, splitLine: { show: false } },
        yAxis: { type: "value", name: "Peso medio (g)", nameLocation: "middle", nameGap: 44, axisLabel: { color: "#64748b" }, splitLine: { lineStyle: { color: "#eef2f7" } } },
        series,
    };
});

const graficoMetricas = computed(() => {
    const ms = datos.value?.modelos ?? [];
    if (!ms.length) return null;
    const nombres = { ridge: "Ridge\ncon sensores", gp: "GP\ncon sensores", ridge_context: "Ridge\nsin sensores", gp_context: "GP\nsin sensores" };
    const orden = ["ridge", "gp", "ridge_context", "gp_context"].map((k) => ms.find((m) => m.clave === k)).filter(Boolean);
    const cats = [...orden.map((m) => nombres[m.clave]), "Random Forest\n(informe)"];
    return {
        grid: { top: 30, left: 40, right: 10, bottom: 36, containLabel: true },
        legend: { bottom: 0 },
        tooltip: { trigger: "axis", valueFormatter: (v) => `${fmt(v, 2)} g` },
        xAxis: { type: "category", data: cats, axisLabel: { color: "#475569", fontSize: 10, interval: 0, lineHeight: 13 } },
        yAxis: { type: "value", name: "g", axisLabel: { color: "#64748b" }, splitLine: { lineStyle: { color: "#eef2f7" } } },
        series: [
            { name: "MAE", type: "bar", barMaxWidth: 34, itemStyle: { color: "#2563eb" }, label: { show: true, position: "top", formatter: (p) => fmt(p.value, 2) }, data: [...orden.map((m) => m.mae_g), 13.72] },
            { name: "RMSE", type: "bar", barMaxWidth: 34, itemStyle: { color: "#94a3b8" }, label: { show: true, position: "top", formatter: (p) => (p.value === null ? "" : fmt(p.value, 2)) }, data: [...orden.map((m) => m.rmse_g), null] },
        ],
    };
});

const estadoTexto = computed(() => {
    const s = ia.value?.estado;
    if (s === "admissible_experimental_inference") return { texto: "Estimación admisible", tipo: "success" };
    if (s === "abstained_insufficient_coverage") return { texto: "Sin estimación: cobertura insuficiente", tipo: "warning" };
    return { texto: s ?? "-", tipo: "info" };
});
</script>

<template>
    <section class="card card-flush mb-8 crecimiento-ml">
        <div class="card-header align-items-center">
            <div>
                <h3 class="card-title fw-bold text-dark fs-4 mb-1">Núcleo de crecimiento SISMAPISCIS</h3>
                <div class="text-gray-600 fs-7">Ridge y proceso gaussiano entrenados con las biometrías de la campaña. Estiman el peso medio al cierre de un intervalo a partir del peso inicial, los días y la temperatura y el oxígeno registrados.</div>
            </div>
            <div class="card-toolbar">
                <el-button :icon="RefreshRight" :loading="loading" @click="cargar">Actualizar</el-button>
            </div>
        </div>
        <div class="card-body pt-2">
            <el-skeleton v-if="loading && !datos" :rows="6" animated />
            <el-alert v-else-if="datos && !datos.disponible" type="warning" :closable="false" show-icon :title="datos.motivo" />
            <template v-else-if="datos">
                <div class="modelos-nucleo mb-6">
                    <div v-for="m in principales" :key="m.clave" :class="['nucleo-card', m.clave]">
                        <span class="badge badge-light-primary mb-2">{{ m.rol }}</span>
                        <div class="fw-bold text-dark fs-5">{{ m.nombre }}</div>
                        <div class="text-gray-600 fs-7">{{ m.algoritmo }}</div>
                        <div class="metricas mt-4">
                            <div><span>MAE</span><strong>{{ fmt(m.mae_g) }} g</strong></div>
                            <div><span>RMSE</span><strong>{{ fmt(m.rmse_g) }} g</strong></div>
                            <div><span>Error máximo</span><strong>{{ fmt(m.max_error_g) }} g</strong></div>
                            <div><span>Intervalos de ajuste</span><strong>{{ m.n_intervalos }}</strong></div>
                        </div>
                        <div class="mt-3 fs-7 text-gray-700">
                            <b>Entradas:</b>
                            <span v-for="e in m.entradas" :key="e.clave" class="chip">{{ e.etiqueta }}</span>
                        </div>
                        <div class="mt-2 fs-8 text-gray-500">Salida: incremento de peso (g) y peso medio al cierre del intervalo<span v-if="m.familia === 'gaussian_correlated'">, con desviación condicional del incremento</span>.</div>
                    </div>
                </div>

                <h4 class="seccion">Evaluación cronológica en cuatro fechas</h4>
                <p class="nota">Cada fecha se estima con un ajuste que solo conoce los intervalos anteriores. Las líneas unen los pesos medios medidos en las biometrías; los marcadores son las estimaciones de cada modelo para la misma fecha.</p>
                <div class="graficas mb-4">
                    <div><h5>Peso medio observado y estimado</h5><ChartFisheye v-if="grafico" :options="grafico" height="360px" /></div>
                    <div><h5>Error por modelo (g)</h5><ChartFisheye v-if="graficoMetricas" :options="graficoMetricas" height="360px" /></div>
                </div>
                <div class="table-responsive mb-6">
                    <table class="table table-sm table-row-dashed align-middle">
                        <thead>
                            <tr class="text-gray-600 fw-bold fs-7">
                                <th>Fecha</th><th class="text-end">Peso inicial</th><th class="text-end">Peso medido</th>
                                <th class="text-end">Ridge</th><th class="text-end">Error Ridge</th>
                                <th class="text-end">GP ± DE</th><th class="text-end">Error GP</th>
                                <th class="text-end">Ridge sin sensores</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="r in datos.evaluacion" :key="r.fecha">
                                <td>{{ fecha(r.fecha) }}</td>
                                <td class="text-end">{{ fmt(r.peso_inicial_g, 3) }} g</td>
                                <td class="text-end fw-bold">{{ fmt(r.peso_observado_g, 3) }} g</td>
                                <td class="text-end">{{ fmt(r.ridge?.peso_g, 3) }} g</td>
                                <td class="text-end text-primary fw-bold">{{ fmt(r.ridge?.error_g, 3) }} g</td>
                                <td class="text-end">{{ fmt(r.gp?.peso_g, 3) }} ± {{ fmt(r.gp?.sd_g, 1) }} g</td>
                                <td class="text-end">{{ fmt(r.gp?.error_g, 3) }} g</td>
                                <td class="text-end text-gray-600">{{ fmt(r.ridge_context?.peso_g, 3) }} g</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h4 class="seccion">Intervalo en curso</h4>
                <div v-if="ia" class="intervalo mb-6">
                    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
                        <div class="fs-6">Desde la última biometría <b>{{ fecha(ia.desde) }}</b> hasta <b>{{ fecha(ia.hasta) }}</b> (hora de Lima)</div>
                        <el-tag :type="estadoTexto.tipo" effect="dark">{{ estadoTexto.texto }}</el-tag>
                    </div>
                    <div class="metricas mb-3">
                        <div><span>Cobertura T</span><strong>{{ pct(ia.cobertura.temperature) }}</strong></div>
                        <div><span>Cobertura OD</span><strong>{{ pct(ia.cobertura.oxygen) }}</strong></div>
                        <div><span>Cobertura pH</span><strong>{{ pct(ia.cobertura.ph) }}</strong></div>
                        <div><span>Horas sin datos</span><strong>{{ ia.horas_faltantes ?? "-" }} <small class="text-gray-500 fs-8">de {{ ia.horas_esperadas }}</small></strong></div>
                        <div><span>T media</span><strong>{{ fmt(ia.entradas.temperature_mean) }} °C</strong></div>
                        <div><span>OD medio / P10</span><strong>{{ fmt(ia.entradas.oxygen_mean) }} / {{ fmt(ia.entradas.oxygen_p10) }}</strong></div>
                    </div>
                    <div class="metricas resaltado">
                        <div><span>Ridge: peso estimado</span><strong>{{ fmt(ia.laravel?.ridge?.predicted_weight_g) }} g</strong><small>incremento {{ fmt(ia.laravel?.ridge?.predicted_gain_g) }} g</small></div>
                        <div><span>GP: peso estimado</span><strong>{{ fmt(ia.laravel?.gp?.predicted_weight_g) }} g</strong><small>incremento {{ fmt(ia.laravel?.gp?.predicted_gain_g) }} ± {{ fmt(ia.laravel?.gp?.conditional_latent_gain_sd_g) }} g</small></div>
                        <div><span>Coincidencia con predecir_ml.py</span><strong>{{ fmt(Math.abs((ia.laravel?.ridge?.predicted_weight_g ?? 0) - (ia.corrida?.ridge?.predicted_weight_g ?? 0)), 3) }} g</strong><small>diferencia Ridge entre Laravel y la corrida</small></div>
                    </div>
                    <p class="nota mt-3 mb-0">La estimación solo se admite con cobertura de al menos 70 % en temperatura, OD y pH y un hueco final de 48 h como máximo. Entradas fuera del rango de ajuste: <b>{{ (ia.laravel?.ridge?.outside_training_features ?? []).map((k) => etiqueta[k] ?? k).join(", ") || "ninguna" }}</b>. Biometrías nuevas desde el último ajuste: {{ datos.nuevas_biometrias }}.</p>
                </div>

                <h4 class="seccion">Calcular con otras entradas</h4>
                <p class="nota">Se usan los parámetros congelados de los cuatro activos, sin reentrenar. Los valores iniciales son los del intervalo en curso.</p>
                <el-form label-position="top" class="row g-3 mb-3">
                    <el-form-item label="Peso inicial (g)" class="col-6 col-md mb-0"><el-input-number v-model="entradas.initial_weight_g" :min="0.1" :step="1" :precision="2" controls-position="right" class="w-100" /></el-form-item>
                    <el-form-item label="Duración (días)" class="col-6 col-md mb-0"><el-input-number v-model="entradas.duration_days" :min="0.1" :step="1" :precision="1" controls-position="right" class="w-100" /></el-form-item>
                    <el-form-item label="Temperatura media (°C)" class="col-6 col-md mb-0"><el-input-number v-model="entradas.temperature_mean" :min="0" :max="45" :step="0.1" :precision="2" controls-position="right" class="w-100" /></el-form-item>
                    <el-form-item label="OD medio (mg/L)" class="col-6 col-md mb-0"><el-input-number v-model="entradas.oxygen_mean" :min="0" :max="30" :step="0.1" :precision="2" controls-position="right" class="w-100" /></el-form-item>
                    <el-form-item label="OD percentil 10 (mg/L)" class="col-6 col-md mb-0"><el-input-number v-model="entradas.oxygen_p10" :min="0" :max="30" :step="0.1" :precision="2" controls-position="right" class="w-100" /></el-form-item>
                    <div class="col-6 col-md-auto d-flex align-items-end"><el-button type="primary" :loading="infiriendo" @click="inferir">Estimar incremento</el-button></div>
                </el-form>
                <el-alert v-if="errorInferencia" type="error" :closable="false" show-icon :title="errorInferencia" class="mb-3" />
                <div v-if="inferencia" class="table-responsive mb-6">
                    <table class="table table-sm table-row-dashed align-middle">
                        <thead><tr class="text-gray-600 fw-bold fs-7"><th>Modelo</th><th class="text-end">Incremento</th><th class="text-end">Peso al cierre</th><th class="text-end">DE condicional</th><th>Entradas fuera de rango</th></tr></thead>
                        <tbody>
                            <tr v-for="(r, k) in inferencia.resultados" :key="k">
                                <td class="fw-bold">{{ modelo(k)?.nombre ?? k }}</td>
                                <td class="text-end">{{ fmt(r.predicted_gain_g) }} g</td>
                                <td class="text-end fw-bold">{{ fmt(r.predicted_weight_g) }} g</td>
                                <td class="text-end">{{ r.conditional_latent_gain_sd_g === null ? "-" : `${fmt(r.conditional_latent_gain_sd_g)} g` }}</td>
                                <td class="fs-7">{{ r.outside_training_features.map((x) => etiqueta[x] ?? x).join(", ") || "ninguna" }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="row g-4">
                    <div class="col-lg-6">
                        <h4 class="seccion">Parámetros del Modelo SISMAPISCIS de incremento de peso</h4>
                        <table v-if="modelo('ridge')" class="table table-sm table-row-dashed align-middle">
                            <thead><tr class="text-gray-600 fw-bold fs-7"><th>Entrada</th><th class="text-end">Media</th><th class="text-end">Escala</th><th class="text-end">Coeficiente</th><th class="text-end">Rango de ajuste</th></tr></thead>
                            <tbody>
                                <tr v-for="(e, i) in modelo('ridge').entradas" :key="e.clave">
                                    <td>{{ e.etiqueta }}</td>
                                    <td class="text-end">{{ fmt(modelo('ridge').medias[i], 3) }}</td>
                                    <td class="text-end">{{ fmt(modelo('ridge').escalas[i], 3) }}</td>
                                    <td class="text-end fw-bold">{{ fmt(modelo('ridge').coeficientes[i], 4) }}</td>
                                    <td class="text-end fs-8">{{ fmt(modelo('ridge').limites[e.clave][0], 2) }} – {{ fmt(modelo('ridge').limites[e.clave][1], 2) }}</td>
                                </tr>
                            </tbody>
                        </table>
                        <p class="nota">ΔW = ȳ + s<sub>y</sub> · Σ β<sub>i</sub> (x<sub>i</sub> − μ<sub>i</sub>) / σ<sub>i</sub>; penalización α = {{ modelo('ridge')?.penalizacion }}. Peso al cierre = peso inicial + ΔW.</p>
                    </div>
                    <div class="col-lg-6">
                        <h4 class="seccion">Comparadores y referencias del informe</h4>
                        <table class="table table-sm table-row-dashed align-middle">
                            <thead><tr class="text-gray-600 fw-bold fs-7"><th>Modelo</th><th>Función</th><th>Resultado</th></tr></thead>
                            <tbody>
                                <tr v-for="r in datos.referencias" :key="r.nombre">
                                    <td class="fw-bold">{{ r.nombre }}</td><td class="fs-7">{{ r.rol }}</td><td class="fs-7">{{ r.metrica }}<div class="text-gray-500 fs-8">{{ r.fuente }}</div></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <p class="nota mb-0">Corrida de validación {{ datos.corrida?.run }}. Estimación retrospectiva al cierre de intervalos de una sola campaña; no es un pronóstico emitido al inicio ni una precisión certificada para otras campañas.</p>
            </template>
        </div>
    </section>
</template>

<style scoped>
.crecimiento-ml { border: 1px solid #c7d2fe; }
.modelos-nucleo { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.nucleo-card { border: 1px solid #e5e7eb; border-left: 5px solid #2563eb; border-radius: 10px; padding: 16px; background: #fbfdff; }
.nucleo-card.gp { border-left-color: #7c3aed; background: #fcfbff; }
.chip { display: inline-block; background: #eef2ff; color: #3730a3; border-radius: 12px; padding: 1px 9px; margin: 2px 3px; font-size: 11px; }
.metricas { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
.metricas > div { border: 1px solid #e5e7eb; background: #f8fafc; padding: 10px 12px; border-radius: 8px; }
.metricas span { display: block; color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 700; }
.metricas strong { display: block; color: #172033; font-size: 16px; margin-top: 3px; }
.metricas small { display: block; color: #64748b; font-size: 11px; }
.metricas.resaltado > div { background: #fffbeb; border-color: #fde68a; }
.seccion { font-size: 15px; font-weight: 800; color: #172033; border-left: 4px solid #2563eb; padding-left: 10px; margin: 18px 0 8px; }
.nota { color: #64748b; font-size: 12px; line-height: 1.6; }
.graficas { display: grid; grid-template-columns: 3fr 2fr; gap: 16px; }
.graficas > div, .intervalo { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; background: #fff; }
.graficas h5 { font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 6px; }
@media (max-width: 1000px) { .modelos-nucleo, .graficas { grid-template-columns: 1fr; } }
</style>
