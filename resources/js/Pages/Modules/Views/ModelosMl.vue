<script setup>
import { computed, onMounted, ref } from "vue";
import ChartFisheye from "@/Components/ChartFisheye.vue";
import LatexFormula from "@/Components/LatexFormula.vue";

defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false,
    },
});

const loading = ref(false);
const errorMessage = ref("");
const response = ref(null);
const piscigranjas = ref([]);
const piscinasList = ref([]);

const form = ref({
    piscigranja_id: "T",
    piscina_id: "T",
    horizonte: "1h",
    ventana: "7d",
    proyeccion_dias: 7,
});

const horizontes = [
    { id: "1h", name: "Proyeccion a 1 hora" },
];

const ventanas = [
    { id: "6h", name: "Ultimas 6 horas" },
    { id: "24h", name: "Ultimas 24 horas" },
    { id: "7d", name: "Ultimos 7 dias" },
    { id: "30d", name: "Ultimos 30 dias" },
    { id: "90d", name: "Ultimos 90 dias" },
];

const proyeccionesCrecimiento = [
    { id: 1, name: "1 dia" },
    { id: 7, name: "7 dias" },
    { id: 30, name: "30 dias" },
    { id: 90, name: "90 dias" },
];

const latest = computed(() => response.value?.latest ?? {});
const summary = computed(() => response.value?.summary ?? {});
const models = computed(() => response.value?.models ?? []);
const warnings = computed(() => response.value?.warnings ?? []);
const aiModel = computed(() => response.value?.ai_model ?? {});
const lifecycle = computed(() => response.value?.lifecycle ?? {});

const formatValue = (value, unit = "") => {
    if (value === null || value === undefined || value === "") return "-";
    const number = Number(value);
    if (!Number.isFinite(number)) return "-";
    return `${number.toLocaleString("es-PE", { maximumFractionDigits: 3 })}${unit ? ` ${unit}` : ""}`;
};

const metricValue = (value) => {
    if (value === null || value === undefined || value === "") return "N/D";
    const number = Number(value);
    if (!Number.isFinite(number)) return "N/D";
    return number.toLocaleString("es-PE", { maximumFractionDigits: 4 });
};

const statusLabel = (status) => {
    const labels = {
        asset_activo: "Asset ML activo",
        gemelo_digital: "Gemelo digital",
        escenario_sin_asset: "Escenario sin asset",
        entrenado: "Entrenado",
        calculado: "Calculado",
        disponible: "Disponible",
        sin_datos: "Sin datos",
        candidato_bloqueado: "Modelo entrenado en evaluacion",
        calculo_parcial: "Calculo parcial",
        fuera_de_dominio: "Fuera del dominio",
    };
    return labels[status] ?? status ?? "N/D";
};

const statusClass = (status) => {
    if (status === "asset_activo") return "badge-light-success";
    if (status === "entrenado") return "badge-light-success";
    if (status === "calculado") return "badge-light-primary";
    if (status === "gemelo_digital") return "badge-light-primary";
    if (status === "escenario_sin_asset") return "badge-light-warning";
    if (status === "candidato_bloqueado") return "badge-light-warning";
    if (status === "calculo_parcial") return "badge-light-warning";
    if (status === "fuera_de_dominio") return "badge-light-danger";
    if (status === "sin_datos") return "badge-light-danger";
    return "badge-light-info";
};

const shortId = (value) => {
    if (!value) return "N/D";
    const text = String(value);
    return text.length > 18 ? `${text.slice(0, 18)}...` : text;
};

const hasMetrics = (model) => Object.keys(model.metrics ?? {}).length > 0;

const formulaTone = (code) => ({
    SVM_OD_FORECAST_1H: "formula-svm",
    OXYGEN_STATUS_MODEL: "formula-oxygen",
    TILAPIA_GROWTH_TEMPERATURE: "formula-growth",
    WATER_QUALITY_INDEX_ICA: "formula-ica",
})[code] ?? "formula-default";

const interpretationRange = (item) => {
    if (item.range) return item.range;
    const minimum = Number(item.minimum);
    const maximum = Number(item.maximum);
    if (!Number.isFinite(minimum) || !Number.isFinite(maximum)) return "-";
    return `${minimum.toLocaleString("es-PE", { maximumFractionDigits: 1 })} a ${maximum.toLocaleString("es-PE", { maximumFractionDigits: 1 })}`;
};

const piscigranjasOptions = async () => {
    const { data } = await axios.get(route("piscigranjas.options"));
    piscigranjas.value = data.data ?? [];
};

const piscinasOptions = async () => {
    if (form.value.piscigranja_id === "T") {
        piscinasList.value = [];
        return;
    }
    const { data } = await axios.get(route("piscigranjas.piscinas", form.value.piscigranja_id));
    piscinasList.value = data ?? [];
};

const loadModelos = async () => {
    loading.value = true;
    errorMessage.value = "";
    try {
        const { data } = await axios.get(route("monitoreo.modelosmls.proyecciones"), {
            params: form.value,
        });
        response.value = data;
    } catch (error) {
        response.value = null;
        errorMessage.value =
            error?.response?.data?.message ??
            error?.response?.data?.detail ??
            "No se pudo cargar la informacion de modelos.";
    } finally {
        loading.value = false;
    }
};

const changePiscigranja = async () => {
    form.value.piscina_id = "T";
    await piscinasOptions();
    await loadModelos();
};

const changeFiltro = async () => {
    await loadModelos();
};

onMounted(async () => {
    await piscigranjasOptions();
    await piscinasOptions();
    await loadModelos();
});
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="row g-5 g-xl-8">
            <div class="col-xl-12">
                <div class="card bg-body card-xl-stretch mb-xl-1">
                    <div class="card-body">
                        <el-form :model="form" label-position="top" class="w-100">
                            <div class="row">
                                <div class="col-lg-2">
                                    <el-form-item label="Piscigranja">
                                        <el-select filterable v-model="form.piscigranja_id" @change="changePiscigranja">
                                            <el-option label="Todos" value="T" />
                                            <el-option
                                                v-for="item in piscigranjas"
                                                :key="item.id"
                                                :label="item.nombre"
                                                :value="item.id"
                                            />
                                        </el-select>
                                    </el-form-item>
                                </div>

                                <div class="col-lg-3">
                                    <el-form-item label="Piscina">
                                        <el-select filterable v-model="form.piscina_id" @change="changeFiltro">
                                            <el-option label="Todos" value="T" />
                                            <el-option
                                                v-for="item in piscinasList"
                                                :key="item.id"
                                                :label="item.nombre"
                                                :value="item.id"
                                            />
                                        </el-select>
                                    </el-form-item>
                                </div>

                                <div class="col-lg-3">
                                    <el-form-item label="Horizonte">
                                        <el-select v-model="form.horizonte" disabled>
                                            <el-option
                                                v-for="item in horizontes"
                                                :key="item.id"
                                                :label="item.name"
                                                :value="item.id"
                                            />
                                        </el-select>
                                        <div class="text-gray-500 fs-8 mt-1">La SVM se valido exclusivamente a una hora.</div>
                                    </el-form-item>
                                </div>

                                <div class="col-lg-2">
                                    <el-form-item label="Ventana de datos">
                                        <el-select v-model="form.ventana" @change="changeFiltro">
                                            <el-option
                                                v-for="item in ventanas"
                                                :key="item.id"
                                                :label="item.name"
                                                :value="item.id"
                                            />
                                        </el-select>
                                    </el-form-item>
                                </div>

                                <div class="col-lg-2">
                                    <el-form-item label="Proyeccion de crecimiento">
                                        <el-select v-model="form.proyeccion_dias" @change="changeFiltro">
                                            <el-option
                                                v-for="item in proyeccionesCrecimiento"
                                                :key="item.id"
                                                :label="item.name"
                                                :value="item.id"
                                            />
                                        </el-select>
                                    </el-form-item>
                                </div>
                            </div>
                        </el-form>
                    </div>
                </div>
            </div>
        </div>

        <el-alert
            v-if="errorMessage"
            class="mb-5"
            type="error"
            :title="errorMessage"
            show-icon
            :closable="false"
        />

        <el-alert
            v-if="response"
            class="mb-5"
            type="info"
            show-icon
            :closable="false"
        >
            <template #title>
                Datos procesados dentro de la maquina virtual. La SVR de oxigeno trabaja a una hora; la ventana visible es
                {{ response?.filters?.window_label ?? "la seleccionada" }} y el crecimiento usa el horizonte elegido.
            </template>
        </el-alert>

        <div v-if="warnings.length" class="card card-flush border border-warning mb-5">
            <div class="card-body py-4">
                <div class="fw-bold text-warning mb-2">Lecturas a considerar</div>
                <div v-for="warning in warnings" :key="warning" class="text-gray-700 fs-7 mb-1">
                    {{ warning }}
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body">
                        <span class="fs-7 text-gray-500 fw-semibold">Mediciones limpias</span>
                        <div class="fs-2hx fw-bold text-dark">{{ summary.samples ?? "-" }}</div>
                        <span class="text-gray-500">{{ summary.from ?? "-" }} -> {{ summary.to ?? "-" }}</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body">
                        <span class="fs-7 text-gray-500 fw-semibold">Ion nitrato actual</span>
                        <div class="fs-2hx fw-bold text-info">
                            {{ formatValue(latest.ion_nitrato, latest.ion_nitrato_unit) }}
                        </div>
                        <span class="text-gray-500">{{ latest.timestamp ?? "-" }}</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body">
                        <span class="fs-7 text-gray-500 fw-semibold">Oxigeno disuelto actual</span>
                        <div class="fs-2hx fw-bold text-success">
                            {{ formatValue(latest.oxigeno_disuelto, latest.oxigeno_disuelto_unit) }}
                        </div>
                        <span class="text-gray-500">{{ latest.piscina ?? "Todas las piscinas" }}</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body">
                        <span class="fs-7 text-gray-500 fw-semibold">Puntos graficados</span>
                        <div class="fs-2hx fw-bold text-primary">{{ summary.historical_points ?? 0 }}</div>
                        <span class="text-gray-500">{{ response?.filters?.window_label ?? "-" }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="response" class="card card-flush border border-primary mb-5">
            <div class="card-body py-5">
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-4">
                    <div>
                        <div class="fw-bold text-dark fs-4">Aprendizaje automatico para el oxigeno disuelto</div>
                        <div class="text-gray-600 fs-7 mt-1">{{ aiModel.detail }}</div>
                    </div>
                    <div class="text-end">
                        <span :class="['badge mb-1', aiModel.productive ? 'badge-light-success' : 'badge-light-warning']">
                            {{ aiModel.productive ? 'En uso productivo' : 'Modelo entrenado en evaluacion' }}
                        </span>
                        <div class="text-gray-500 fs-8">Modelo {{ aiModel.version ?? 'sin entrenar' }} - {{ shortId(aiModel.asset_id) }}</div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="lifecycle.summary?.length" class="row g-3 mb-5">
            <div v-for="item in lifecycle.summary" :key="item.step" class="col-md-6 col-xl-3">
                <div class="border border-dashed border-gray-300 rounded p-4 h-100 bg-light">
                    <div class="fw-bold text-dark fs-7 mb-2">{{ item.step }}</div>
                    <div class="text-gray-600 fs-8">{{ item.detail }}</div>
                </div>
            </div>
        </div>

        <div v-loading="loading" class="row g-5 g-xl-8">
            <div class="col-xl-12" v-if="!loading && !models.length && !errorMessage">
                <div class="card card-flush">
                    <div class="card-body text-center py-15">
                        <div class="fs-3 fw-bold text-gray-700">Sin modelos disponibles para los filtros seleccionados</div>
                        <div class="text-gray-500 mt-2">Revisa los datos disponibles o cambia los filtros.</div>
                    </div>
                </div>
            </div>

            <div class="col-xl-12" v-for="model in models" :key="model.code">
                <div class="card card-flush overflow-hidden h-xl-100">
                    <div class="card-header py-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-dark">{{ model.name }}</span>
                            <span class="text-gray-500 mt-1 fw-semibold fs-6">{{ model.message }}</span>
                        </h3>
                        <div class="card-toolbar">
                            <span :class="['badge', statusClass(model.status)]">{{ statusLabel(model.status) }}</span>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-5">
                            <div class="col-xl-8">
                                <p class="text-gray-600 fs-7 mb-3">{{ model.chart_description }}</p>
                                <ChartFisheye :options="model.chart" height="420px" />
                                <div v-if="model.relationship" class="border border-dashed border-gray-300 rounded mt-5 p-2">
                                    <p class="text-gray-600 fs-7 px-3 pt-3 mb-0">{{ model.relationship.description }}</p>
                                    <ChartFisheye :options="model.relationship.chart" height="350px" />
                                </div>
                            </div>
                            <div class="col-xl-4">
                                <div class="border border-dashed border-gray-300 rounded px-5 py-4 mb-4">
                                    <div class="text-gray-500 fw-semibold">Valor actual</div>
                                    <div class="fs-2 fw-bold text-dark">{{ formatValue(model.current_value, model.unit) }}</div>
                                </div>
                                <div v-if="model.usage" class="border border-dashed border-gray-300 rounded px-5 py-4 mb-4">
                                    <div class="text-gray-500 fw-semibold mb-2">Como se usa</div>
                                    <span :class="['badge mb-2', statusClass(model.usage.status)]">{{ model.usage.label }}</span>
                                    <div class="text-gray-600 fs-8">{{ model.usage.detail }}</div>
                                    <div v-if="model.usage.activation_criteria" class="mt-3">
                                        <div v-for="(passed, criterion) in model.usage.activation_criteria" :key="criterion" class="d-flex justify-content-between fs-8 py-1 border-top border-gray-100">
                                            <span>{{ criterion.replaceAll('_', ' ') }}</span>
                                            <span :class="passed ? 'text-success fw-bold' : 'text-danger fw-bold'">{{ passed ? 'Cumple' : 'No cumple' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="model.formula" :class="['formula-panel', formulaTone(model.code), 'border', 'rounded', 'px-5', 'py-4', 'mb-4']">
                                    <div class="formula-panel__title fw-semibold mb-2">Formula del modelo</div>
                                    <LatexFormula v-if="model.formula.latex" :latex="model.formula.latex" />
                                    <code v-else class="d-block text-dark fs-7 text-break">{{ model.formula.expression }}</code>
                                    <code v-if="model.formula.kernel" class="d-block text-dark fs-8 mt-2 text-break">{{ model.formula.kernel }}</code>
                                    <div class="text-gray-700 fs-8 mt-3">{{ model.formula.detail }}</div>
                                    <ul v-if="model.formula.conditions?.length" class="text-gray-700 fs-8 ps-4 mt-3 mb-0">
                                        <li v-for="condition in model.formula.conditions" :key="condition">{{ condition }}</li>
                                    </ul>
                                </div>
                                <div v-if="model.origin" class="border border-dashed border-gray-300 rounded px-5 py-4 mb-4">
                                    <div class="text-gray-500 fw-semibold mb-2">Origen</div>
                                    <div class="text-dark fs-8 mb-2">{{ model.origin.document }}</div>
                                    <div class="text-gray-600 fs-8">{{ model.origin.data }}</div>
                                </div>
                                <div v-if="model.asset_id" class="border border-dashed border-gray-300 rounded px-5 py-4 mb-4">
                                    <div class="text-gray-500 fw-semibold">Modelo entrenado</div>
                                    <div class="fw-bold text-dark">{{ model.version ?? "sin version" }}</div>
                                    <div class="text-gray-500 fs-8">{{ shortId(model.asset_id) }}</div>
                                </div>
                                <div v-if="hasMetrics(model)" class="border border-dashed border-gray-300 rounded px-5 py-4 mb-4">
                                    <div class="text-gray-500 fw-semibold">Metricas</div>
                                    <div v-if="model.metrics?.mae !== undefined" class="fw-bold text-dark">MAE: {{ metricValue(model.metrics.mae) }}</div>
                                    <div v-if="model.metrics?.r2 !== undefined" class="text-gray-500 fs-8">R2: {{ metricValue(model.metrics.r2) }}</div>
                                    <div v-if="model.metrics?.f1_weighted !== undefined" class="fw-bold text-dark">F1 ponderado: {{ metricValue(model.metrics.f1_weighted) }}</div>
                                    <div v-if="model.metrics?.accuracy !== undefined" class="text-gray-500 fs-8">Exactitud: {{ metricValue(model.metrics.accuracy) }}</div>
                                </div>
                                <div v-if="model.machine_learning" class="border border-dashed border-info rounded px-5 py-4 mb-4 bg-light-info">
                                    <div class="text-info fw-semibold mb-2">SVM para clasificar el ICA</div>
                                    <div class="text-gray-700 fs-8 mb-2">{{ model.machine_learning.detail }}</div>
                                    <div v-if="model.machine_learning.classification" class="fw-bold text-dark">Clasificacion SVM: {{ model.machine_learning.classification }}</div>
                                    <div v-if="model.machine_learning.version" class="text-gray-600 fs-8">{{ model.machine_learning.version }} - {{ shortId(model.machine_learning.asset_id) }}</div>
                                    <div class="text-gray-500 fs-8 mt-2">Etiqueta de entrenamiento: {{ model.machine_learning.target_origin ?? "pendiente" }}</div>
                                </div>
                                <div v-if="model.components?.length" class="table-responsive border border-dashed border-gray-300 rounded px-4 py-3 mb-4">
                                    <div class="text-gray-500 fw-semibold mb-2">Lecturas que componen el indice</div>
                                    <table class="table table-sm align-middle mb-0">
                                        <thead><tr class="text-muted fs-8"><th>Variable</th><th class="text-end">Lectura</th><th class="text-end">Q</th><th class="text-end">Peso</th></tr></thead>
                                        <tbody>
                                            <tr v-for="component in model.components" :key="component.variable">
                                                <td class="fs-8">{{ component.variable }}</td>
                                                <td class="text-end fs-8">{{ formatValue(component.raw_value, component.unit) }}</td>
                                                <td class="text-end fs-8">{{ formatValue(component.normalized_score) }}</td>
                                                <td class="text-end fs-8">{{ formatValue(component.weight) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-if="model.interpretation?.length" class="table-responsive border border-dashed border-gray-300 rounded px-4 py-3 mb-4">
                                    <div class="text-gray-500 fw-semibold mb-2">Interpretacion</div>
                                    <table class="table table-sm align-middle mb-0">
                                        <thead><tr class="text-muted fs-8"><th>Rango</th><th class="text-end">Lectura</th></tr></thead>
                                        <tbody>
                                            <tr v-for="item in model.interpretation" :key="`${item.range}-${item.label}`">
                                                <td class="fs-8">{{ interpretationRange(item) }}</td>
                                                <td class="text-end fw-semibold fs-8">{{ item.label }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div v-if="model.biometric_context?.available" class="border border-dashed border-warning rounded px-5 py-4 mb-4 bg-light-warning">
                                    <div class="text-warning fw-semibold mb-2">Ultima biometria real</div>
                                    <div class="text-gray-700 fs-8">{{ model.biometric_context.sampled_at }} - peso medio {{ formatValue(model.biometric_context.peso_promedio_g, "g") }} - longitud media {{ formatValue(model.biometric_context.longitud_promedio_cm, "cm") }}</div>
                                    <div class="fw-bold text-dark mt-2">FCA: {{ metricValue(model.biometric_context.conversion_alimenticia) }} ({{ model.biometric_context.conversion_label }})</div>
                                    <LatexFormula :latex="model.biometric_context.formula" />
                                    <table class="table table-sm align-middle mb-0"><tbody><tr v-for="item in model.biometric_context.interpretation" :key="item.range"><td class="fs-8">{{ item.range }}</td><td class="text-end fw-semibold fs-8">{{ item.label }}</td></tr></tbody></table>
                                </div>
                                <div v-if="(model.forecast ?? []).length" class="table-responsive">
                                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3">
                                        <thead>
                                            <tr class="fw-bold text-muted">
                                                <th>Tiempo</th>
                                                <th class="text-end">Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="item in (model.forecast ?? []).slice(0, 6)" :key="`${model.code}-${item.timestamp}-${item.hour}`">
                                                <td>{{ item.label }}</td>
                                                <td class="text-end fw-bold">{{ formatValue(item.value, item.unit ?? model.unit) }}</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </App>
</template>

<style scoped>
.formula-panel {
    border-width: 1px;
}

.formula-panel__title {
    letter-spacing: 0;
}

.formula-svm {
    background: #eef5ff;
    border-color: #9ec5fe !important;
    color: #0d6efd;
}

.formula-oxygen {
    background: #ecfdf3;
    border-color: #8ce3b2 !important;
    color: #16803c;
}

.formula-growth {
    background: #f5f0ff;
    border-color: #c7b3ff !important;
    color: #7048c8;
}

.formula-ica {
    background: #ecfeff;
    border-color: #8de4e8 !important;
    color: #0f766e;
}

.formula-default {
    background: #f8f9fa;
    border-color: #ced4da !important;
    color: #495057;
}

.formula-panel :deep(.katex) {
    color: currentColor;
}
</style>
