<script setup>
import { computed } from "vue";
import ChartFisheye from "@/Components/ChartFisheye.vue";
import { RefreshRight } from "@element-plus/icons-vue";

const props = defineProps({
    datos: { type: Object, default: null },
    loading: { type: Boolean, default: false },
});
const emit = defineEmits(["refresh"]);

const g = computed(() => props.datos?.crecimiento_rf ?? null);
const q = computed(() => props.datos?.calidad_rf ?? null);
const fmt = (v, d = 2) => (v === null || v === undefined || Number.isNaN(Number(v))
    ? "-"
    : Number(v).toLocaleString("es-PE", { minimumFractionDigits: 0, maximumFractionDigits: d }));
const pct = (v, d = 1) => (v === null || v === undefined ? "-" : `${fmt(Number(v) * 100, d)} %`);

const baseXY = (x, y) => ({
    grid: { top: 30, left: 62, right: 24, bottom: 60, containLabel: true },
    tooltip: { trigger: "item" },
    legend: { bottom: 0, textStyle: { fontSize: 11 } },
    xAxis: { type: "value", name: x, nameLocation: "middle", nameGap: 34, axisLabel: { color: "#64748b" }, splitLine: { lineStyle: { color: "#eef2f7" } } },
    yAxis: { type: "value", name: y, nameLocation: "middle", nameGap: 48, axisLabel: { color: "#64748b" }, splitLine: { lineStyle: { color: "#eef2f7" } } },
});

const growthChart = (curves, target) => {
    if (!curves?.length) return null;
    const isWeight = target === "peso";
    const options = baseXY("Temperatura media (°C)", isWeight ? "Incremento de peso (g)" : "Incremento de longitud (cm)");
    return {
        ...options,
        tooltip: {
            trigger: "item",
            formatter: (p) => p.seriesType === "scatter"
                ? `${p.name}<br/>T = ${fmt(p.value[0], 1)} °C<br/>OD = ${fmt(p.value[2], 2)} mg/L<br/>${isWeight ? "ΔPeso" : "ΔLongitud"} = ${fmt(p.value[1], 2)}`
                : `${p.seriesName}<br/>T = ${fmt(p.value[0], 1)} °C<br/>${isWeight ? "ΔPeso" : "ΔLongitud"} = ${fmt(p.value[1], 2)}`,
        },
        series: [
            ...curves.map((c) => ({
                name: `RF con OD = ${fmt(c.od, 1)} mg/L`,
                type: "line",
                showSymbol: false,
                step: "middle",
                data: c.puntos,
            })),
            {
                name: "Intervalos biométricos reales",
                type: "scatter",
                symbolSize: 12,
                data: (g.value?.intervalos ?? []).map((r) => ({
                    name: `${r.desde} a ${r.hasta}`,
                    value: [r.temperatura_media_c, isWeight ? r.inc_peso_g : r.inc_longitud_cm, r.od_medio_mg_l],
                })),
                itemStyle: { borderColor: "#fff", borderWidth: 2 },
            },
        ],
    };
};

const growthWeight = computed(() => growthChart(g.value?.curvas_peso, "peso"));
const growthLength = computed(() => growthChart(g.value?.curvas_longitud, "longitud"));

const importanceChart = computed(() => {
    if (!q.value?.importancia?.length) return null;
    const rows = [...q.value.importancia].sort((a, b) => a.valor - b.valor);
    return {
        grid: { top: 20, left: 120, right: 28, bottom: 42, containLabel: true },
        tooltip: { trigger: "axis", axisPointer: { type: "shadow" }, formatter: (p) => `${p[0].name}: ${pct(p[0].value, 2)}` },
        xAxis: { type: "value", max: 1, axisLabel: { formatter: (v) => `${Math.round(v * 100)} %` }, splitLine: { lineStyle: { color: "#eef2f7" } } },
        yAxis: { type: "category", data: rows.map((r) => r.variable), axisLabel: { color: "#475569" } },
        series: [{ type: "bar", data: rows.map((r) => r.valor), barMaxWidth: 28 }],
    };
});

const qualityScatter = computed(() => {
    const rows = q.value?.scatter_temperatura_od ?? [];
    if (!rows.length) return null;
    const options = baseXY("Temperatura (°C)", "Oxígeno disuelto (mg/L)");
    const make = (label) => rows.filter((r) => r[2] === label).map((r) => [r[0], r[1]]);
    return {
        ...options,
        tooltip: { trigger: "item", formatter: (p) => `${p.seriesName}<br/>T = ${fmt(p.value[0], 1)} °C<br/>OD = ${fmt(p.value[1], 2)} mg/L` },
        series: [
            { name: "No Óptima", type: "scatter", symbolSize: 5, data: make("No Optima"), itemStyle: { opacity: 0.45 } },
            {
                name: "Óptima",
                type: "scatter",
                symbolSize: 6,
                data: make("Optima"),
                itemStyle: { opacity: 0.65 },
                markArea: {
                    silent: true,
                    label: { show: true, position: "insideTop", formatter: "Zona T/OD compatible" },
                    itemStyle: { opacity: 0.07 },
                    data: [[{ xAxis: 24, yAxis: 4.5 }, { xAxis: 31, yAxis: 12 }]],
                },
            },
        ],
    };
});
</script>

<template>
    <section class="card card-flush mb-6 articulo-ml">
        <div class="card-header">
            <div>
                <h3 class="card-title fw-bold text-dark fs-5 mb-1">Corrida corregida del artículo IOT617 — datos reales</h3>
                <p class="text-gray-600 fs-7 mb-0">Reemplaza los <code>np.random</code> del ensayo inicial por registros MySQL y conserva los dos Random Forest que aparecen en el informe del ingeniero.</p>
            </div>
            <el-button :icon="RefreshRight" :loading="loading" @click="emit('refresh')">Recalcular</el-button>
        </div>
        <div class="card-body pt-0">
            <el-skeleton v-if="loading && !datos" :rows="8" animated />
            <el-alert v-else-if="!datos?.disponible" type="warning" :closable="false" show-icon title="La corrida corregida todavía no está disponible." />
            <template v-else>
                <div class="resumen mb-5">
                    <div><span>Fuente</span><strong>{{ datos.fuente }}</strong></div>
                    <div><span>Lecturas crudas</span><strong>{{ fmt(datos.datos?.lecturas_crudas, 0) }}</strong></div>
                    <div><span>Lecturas limpias</span><strong>{{ fmt(datos.datos?.lecturas_limpias, 0) }}</strong></div>
                    <div><span>NO₃ válido</span><strong>{{ fmt(datos.datos?.lecturas_nitrato_valido, 0) }}</strong></div>
                    <div><span>Biometrías</span><strong>{{ fmt(datos.datos?.biometrias, 0) }}</strong></div>
                    <div><span>Peces medidos</span><strong>{{ fmt(datos.datos?.peces_medidos, 0) }}</strong></div>
                </div>

                <el-alert type="info" :closable="false" show-icon class="mb-6">
                    <template #title>El valor de nitrato &gt; 100 mg/L se excluye como lectura fuera de dominio; 14 000 no se divide arbitrariamente entre 1000.</template>
                    La corrida usa <strong>created_at</strong> para reproducir el criterio histórico de la aplicación y conserva <strong>fecha_medicion</strong> para auditoría. Desfase mediano observado: {{ fmt(datos.mediana_desfase_dias, 2) }} días.
                </el-alert>

                <h4 class="seccion">Evidencia biométrica completa de la base</h4>
                <p class="nota">La base contiene <strong>{{ g?.biometrias_completas?.length ?? 0 }} biometrías</strong> y <strong>{{ datos.datos?.peces_medidos }}</strong> mediciones individuales. La tabla siguiente muestra todas las rondas, aunque el análisis RF estricto use solo los periodos con OD de mayor confianza.</p>
                <div class="table-responsive mb-6" v-if="g?.biometrias_completas?.length">
                    <table class="table table-sm table-row-dashed align-middle">
                        <thead><tr><th>Fecha</th><th class="text-end">n</th><th class="text-end">Peso medio ± DE</th><th class="text-end">Rango peso</th><th class="text-end">Talla media ± DE</th><th class="text-end">Rango talla</th></tr></thead>
                        <tbody><tr v-for="b in g.biometrias_completas" :key="b.id">
                            <td>{{ b.fecha }}</td><td class="text-end">{{ b.n }}</td>
                            <td class="text-end">{{ fmt(b.peso_medio_g,2) }} ± {{ fmt(b.peso_sd_g,2) }} g</td>
                            <td class="text-end">{{ fmt(b.peso_min_g,2) }}–{{ fmt(b.peso_max_g,2) }} g</td>
                            <td class="text-end">{{ fmt(b.longitud_media_cm,2) }} ± {{ fmt(b.longitud_sd_cm,2) }} cm</td>
                            <td class="text-end">{{ fmt(b.longitud_min_cm,2) }}–{{ fmt(b.longitud_max_cm,2) }} cm</td>
                        </tr></tbody>
                    </table>
                </div>

                <h4 class="seccion">Modelo 1 — Random Forest Regressor multi-output: crecimiento</h4>
                <p class="nota">X = [temperatura media, OD medio, peso inicial, longitud inicial] → y = [Δpeso, Δlongitud]. Las curvas son la respuesta del bosque real; por eso son escalonadas y no deben confundirse con las ecuaciones continuas (1)-(4) del prototipo sintético.</p>
                <div class="metricas mb-4" v-if="g?.disponible">
                    <div><span>Intervalos independientes</span><strong>{{ g.n_intervalos_independientes }}</strong></div>
                    <div><span>MAE Δpeso</span><strong>{{ fmt(g.metricas?.mae_peso_g, 2) }} g</strong></div>
                    <div><span>R² Δpeso</span><strong>{{ fmt(g.metricas?.r2_peso, 3) }}</strong></div>
                    <div><span>MAE Δlongitud</span><strong>{{ fmt(g.metricas?.mae_longitud_cm, 3) }} cm</strong></div>
                    <div><span>R² Δlongitud</span><strong>{{ fmt(g.metricas?.r2_longitud, 3) }}</strong></div>
                </div>
                <el-alert v-if="g?.disponible" type="warning" :closable="false" show-icon class="mb-4" :title="g.nota" />
                <div v-if="g?.analisis_sensibilidad_5_intervalos?.disponible" class="matriz-box mb-5">
                    <h5>Análisis de sensibilidad usando las cinco biometrías</h5>
                    <p class="nota mb-2">{{ g.analisis_sensibilidad_5_intervalos.nota }}</p>
                    <div class="metricas">
                        <div><span>Intervalos</span><strong>{{ g.analisis_sensibilidad_5_intervalos.n_intervalos }}</strong></div>
                        <div><span>MAE Δpeso</span><strong>{{ fmt(g.analisis_sensibilidad_5_intervalos.metricas?.mae_peso_g,2) }} g</strong></div>
                        <div><span>R² Δpeso</span><strong>{{ fmt(g.analisis_sensibilidad_5_intervalos.metricas?.r2_peso,3) }}</strong></div>
                        <div><span>MAE Δlongitud</span><strong>{{ fmt(g.analisis_sensibilidad_5_intervalos.metricas?.mae_longitud_cm,3) }} cm</strong></div>
                        <div><span>R² Δlongitud</span><strong>{{ fmt(g.analisis_sensibilidad_5_intervalos.metricas?.r2_longitud,3) }}</strong></div>
                    </div>
                </div>
                <div class="graficas" v-if="g?.disponible">
                    <div><h5>Incremento de peso</h5><ChartFisheye v-if="growthWeight" :options="growthWeight" height="330px" /></div>
                    <div><h5>Incremento de longitud</h5><ChartFisheye v-if="growthLength" :options="growthLength" height="330px" /></div>
                </div>
                <div class="table-responsive mt-4" v-if="g?.intervalos?.length">
                    <table class="table table-sm table-row-dashed align-middle">
                        <thead><tr><th>Tramo</th><th class="text-end">T</th><th class="text-end">OD</th><th class="text-end">Peso</th><th class="text-end">Δpeso real</th><th class="text-end">Δpeso RF (LOO)</th></tr></thead>
                        <tbody>
                            <tr v-for="r in g.intervalos" :key="r.id">
                                <td>{{ r.desde }} → {{ r.hasta }}</td>
                                <td class="text-end">{{ fmt(r.temperatura_media_c, 2) }} °C</td>
                                <td class="text-end">{{ fmt(r.od_medio_mg_l, 2) }} mg/L</td>
                                <td class="text-end">{{ fmt(r.peso_inicial_g, 2) }} → {{ fmt(r.peso_final_g, 2) }} g</td>
                                <td class="text-end fw-bold">{{ fmt(r.inc_peso_g, 2) }} g</td>
                                <td class="text-end">{{ fmt(r.pred_inc_peso_g, 2) }} g</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <h4 class="seccion mt-7">Modelo 2 — Random Forest Classifier: calidad de agua</h4>
                <p class="nota">Mismo criterio del informe: pH 6,5-8,5; OD ≥ 4,5 mg/L; temperatura 24-31 °C; nitrato 0-50 mg/L. El SVM se conserva únicamente como comparador sobre exactamente el mismo DataFrame y la misma partición.</p>
                <div class="metricas mb-4" v-if="q?.disponible">
                    <div><span>Filas</span><strong>{{ fmt(q.n, 0) }}</strong></div>
                    <div><span>Óptima</span><strong>{{ fmt(q.clases?.Optima, 0) }}</strong></div>
                    <div><span>No óptima</span><strong>{{ fmt(q.clases?.['No Optima'], 0) }}</strong></div>
                    <div><span>RF F1 Óptima</span><strong>{{ fmt(q.rf?.f1_optima, 4) }}</strong></div>
                    <div><span>RF Recall</span><strong>{{ fmt(q.rf?.recall_optima, 4) }}</strong></div>
                    <div><span>RF ROC-AUC</span><strong>{{ fmt(q.rf?.roc_auc, 6) }}</strong></div>
                </div>
                <div class="graficas" v-if="q?.disponible">
                    <div><h5>Importancia relativa — Random Forest</h5><ChartFisheye v-if="importanceChart" :options="importanceChart" height="320px" /></div>
                    <div><h5>Temperatura vs. oxígeno — datos reales</h5><ChartFisheye v-if="qualityScatter" :options="qualityScatter" height="320px" /></div>
                </div>

                <div class="row g-4 mt-2" v-if="q?.disponible">
                    <div class="col-lg-6">
                        <div class="matriz-box">
                            <h5>Matriz de confusión — Random Forest</h5>
                            <table class="table table-bordered text-center mb-2">
                                <thead><tr><th>Real \ Pred.</th><th>Óptima</th><th>No Óptima</th></tr></thead>
                                <tbody>
                                    <tr><th>Óptima</th><td class="diag">{{ q.rf.confusion[0][0] }}</td><td>{{ q.rf.confusion[0][1] }}</td></tr>
                                    <tr><th>No Óptima</th><td>{{ q.rf.confusion[1][0] }}</td><td class="diag">{{ q.rf.confusion[1][1] }}</td></tr>
                                </tbody>
                            </table>
                            <p class="nota">Accuracy {{ pct(q.rf.accuracy, 2) }} · Balanced accuracy {{ pct(q.rf.balanced_accuracy, 2) }} · Precision {{ pct(q.rf.precision_optima, 2) }} · Recall {{ pct(q.rf.recall_optima, 2) }} · F1 {{ fmt(q.rf.f1_optima, 4) }}</p>
                        </div>
                    </div>
                    <div class="col-lg-6">
                        <div class="matriz-box">
                            <h5>SVM RBF — comparación</h5>
                            <table class="table table-bordered text-center mb-2">
                                <thead><tr><th>Real \ Pred.</th><th>Óptima</th><th>No Óptima</th></tr></thead>
                                <tbody>
                                    <tr><th>Óptima</th><td class="diag">{{ q.svm.confusion[0][0] }}</td><td>{{ q.svm.confusion[0][1] }}</td></tr>
                                    <tr><th>No Óptima</th><td>{{ q.svm.confusion[1][0] }}</td><td class="diag">{{ q.svm.confusion[1][1] }}</td></tr>
                                </tbody>
                            </table>
                            <p class="nota">Accuracy {{ pct(q.svm.accuracy, 2) }} · Balanced accuracy {{ pct(q.svm.balanced_accuracy, 2) }} · Precision {{ pct(q.svm.precision_optima, 2) }} · Recall {{ pct(q.svm.recall_optima, 2) }} · F1 {{ fmt(q.svm.f1_optima, 4) }}</p>
                        </div>
                    </div>
                </div>
                <p class="nota mt-4"><strong>Limitación metodológica:</strong> {{ q?.nota }}</p>
            </template>
        </div>
    </section>
</template>

<style scoped>
.articulo-ml { border: 1px solid #dbeafe; }
.resumen, .metricas { display: grid; grid-template-columns: repeat(auto-fit, minmax(145px, 1fr)); gap: 10px; }
.resumen > div, .metricas > div { border: 1px solid #e5e7eb; background: #f8fafc; padding: 12px; border-radius: 8px; }
.resumen span, .metricas span { display: block; color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: 700; }
.resumen strong, .metricas strong { display: block; color: #172033; font-size: 16px; margin-top: 3px; }
.seccion { font-size: 16px; font-weight: 800; color: #172033; border-left: 4px solid #2563eb; padding-left: 10px; }
.nota { color: #64748b; font-size: 12px; line-height: 1.6; }
.graficas { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
.graficas > div, .matriz-box { border: 1px solid #e5e7eb; border-radius: 8px; padding: 12px; background: #fff; }
.graficas h5, .matriz-box h5 { font-size: 13px; font-weight: 700; color: #374151; margin-bottom: 6px; }
.diag { background: #ecfdf5 !important; color: #047857; font-weight: 800; }
@media (max-width: 900px) { .graficas { grid-template-columns: 1fr; } }
</style>

