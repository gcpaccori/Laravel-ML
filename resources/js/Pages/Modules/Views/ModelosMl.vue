<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import {
    Bell,
    Connection,
    DataAnalysis,
    Odometer,
    RefreshRight,
    SetUp,
    Sunny,
    TrendCharts,
} from "@element-plus/icons-vue";
import ChartFisheye from "@/Components/ChartFisheye.vue";
import LatexFormula from "@/Components/LatexFormula.vue";

defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false,
    },
});

const MODEL_CODES = {
    svm: "SVM_OD_FORECAST_1H",
    oxygen: "OXYGEN_STATUS_MODEL",
    growth: "TILAPIA_GROWTH_TEMPERATURE",
    water: "WATER_QUALITY_INDEX_ICA",
    light: "LIGHT_FEED_RESPONSE_CLASSIFIER_V1",
};

const catalog = [
    {
        code: MODEL_CODES.svm,
        name: "SVM/SVR: proyeccion de OD",
        description: "Modelo de regresion SVM que anticipa el oxigeno disuelto de la proxima hora con la serie real de sensores.",
        importance: "Ayuda a observar cambios de oxigeno antes de que afecten a los peces.",
        inputs: ["Temperatura", "pH", "OD", "Ion nitrato", "Historial temporal"],
        horizon: "1 hora",
        modelType: "IA entrenada - SVM de regresion",
        icon: DataAnalysis,
    },
    {
        code: MODEL_CODES.oxygen,
        name: "Estado de oxigeno",
        description: "Calcula la saturacion y el deficit de oxigeno del agua.",
        importance: "Muestra cuan cerca esta el estanque de la capacidad de oxigeno que permite su temperatura.",
        inputs: ["Temperatura", "Oxigeno disuelto"],
        horizon: "Lectura actual",
        icon: Odometer,
    },
    {
        code: MODEL_CODES.growth,
        name: "Crecimiento de tilapia",
        description: "Proyecta la ganancia de longitud y peso desde la biometria real del estanque.",
        importance: "Permite revisar la evolucion esperada del lote y su biometria de referencia.",
        inputs: ["Temperatura", "Biometria real"],
        horizon: "1 a 365 dias",
        icon: TrendCharts,
    },
    {
        code: MODEL_CODES.water,
        name: "Calidad de agua",
        description: "Resume temperatura, pH, oxigeno disuelto e ion nitrato en un indice ICA.",
        importance: "Da una lectura unica y explicable de la condicion actual del agua.",
        inputs: ["Temperatura", "pH", "OD", "Ion nitrato"],
        horizon: "Lectura actual",
        icon: Connection,
    },
    {
        code: MODEL_CODES.light,
        name: "Luz y respuesta alimentaria",
        description: "Prepara la estimacion de respuesta al alimento a partir de luz subacuatica, fotoperiodo y contexto del agua.",
        importance: "Permitira relacionar el ambiente luminoso con la actividad alimentaria sin confundir un escenario manual con una prediccion entrenada.",
        inputs: ["Luz subacuatica", "Fotoperiodo", "Hora", "OD", "Temperatura", "Racion", "Respuesta observada"],
        horizon: "Siguiente evento de alimentacion",
        modelType: "Modelo planificado - requiere datos del sensor",
        icon: Sunny,
    },
];

const loading = ref(false);
const errorMessage = ref("");
const response = ref(null);
const piscigranjas = ref([]);
const piscinasList = ref([]);
const currentView = ref("catalog");
const selectedCode = ref(null);
const requestController = ref(null);
const reloadTimer = ref(null);

const form = ref({
    piscigranja_id: "T",
    piscina_id: "T",
    ventana: "7d",
    proyeccion_dias: 7,
});

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
    { id: 180, name: "180 dias" },
];

const latest = computed(() => response.value?.latest ?? {});
const summary = computed(() => response.value?.summary ?? {});
const models = computed(() => response.value?.models ?? []);
const warnings = computed(() => response.value?.warnings ?? []);
const lifecycle = computed(() => response.value?.lifecycle ?? {});
const activeMeta = computed(() => catalog.find((item) => item.code === selectedCode.value) ?? null);
const activeModel = computed(() => models.value.find((item) => item.code === selectedCode.value) ?? null);
const growthProjection = computed(() => response.value?.tilapia_growth?.length_projection ?? null);
const selectedBiometrics = computed(() => activeModel.value?.biometric_context ?? response.value?.biometrics ?? null);

const formatValue = (value, unit = "") => {
    if (value === null || value === undefined || value === "") return "-";
    const number = Number(value);
    if (!Number.isFinite(number)) return "-";
    return `${number.toLocaleString("es-PE", { maximumFractionDigits: 3 })}${unit ? ` ${unit}` : ""}`;
};

const metricValue = (value) => {
    if (value === null || value === undefined || value === "") return "N/D";
    const number = Number(value);
    return Number.isFinite(number) ? number.toLocaleString("es-PE", { maximumFractionDigits: 4 }) : "N/D";
};

const shortId = (value) => {
    if (!value) return "N/D";
    const text = String(value);
    return text.length > 18 ? `${text.slice(0, 18)}...` : text;
};

const statusLabel = (status) => ({
    asset_activo: "Modelo activo",
    entrenado: "Entrenado",
    calculado: "Calculado",
    candidato_bloqueado: "Entrenado en evaluacion",
    calculo_parcial: "Calculo parcial",
    fuera_de_dominio: "Fuera del dominio",
    sin_datos: "Sin datos",
}[status] ?? status ?? "Disponible");

const statusClass = (status) => {
    if (["asset_activo", "entrenado", "calculado"].includes(status)) return "badge-light-success";
    if (["candidato_bloqueado", "calculo_parcial"].includes(status)) return "badge-light-warning";
    if (["fuera_de_dominio", "sin_datos"].includes(status)) return "badge-light-danger";
    return "badge-light-primary";
};

const interpretationRange = (item) => {
    if (item.range) return item.range;
    const minimum = Number(item.minimum);
    const maximum = Number(item.maximum);
    if (!Number.isFinite(minimum) || !Number.isFinite(maximum)) return "-";
    return `${minimum.toLocaleString("es-PE", { maximumFractionDigits: 1 })} a ${maximum.toLocaleString("es-PE", { maximumFractionDigits: 1 })}`;
};

const hasMetrics = (model) => Object.keys(model?.metrics ?? {}).length > 0;

const modelFormulaTone = (code) => ({
    [MODEL_CODES.svm]: "formula-svm",
    [MODEL_CODES.oxygen]: "formula-oxygen",
    [MODEL_CODES.growth]: "formula-growth",
    [MODEL_CODES.water]: "formula-water",
})[code] ?? "formula-default";

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
    requestController.value?.abort();
    const controller = new AbortController();
    requestController.value = controller;
    loading.value = true;
    errorMessage.value = "";
    try {
        const { data } = await axios.get(route("monitoreo.modelosmls.proyecciones"), {
            params: form.value,
            signal: controller.signal,
        });
        response.value = data;
    } catch (error) {
        if (error?.code !== "ERR_CANCELED") {
            errorMessage.value = error?.response?.data?.message ?? "Los datos aun se estan preparando. Puedes actualizar en unos segundos.";
        }
    } finally {
        if (requestController.value === controller) loading.value = false;
    }
};

const scheduleReload = () => {
    clearTimeout(reloadTimer.value);
    reloadTimer.value = setTimeout(loadModelos, 250);
};

const changePiscigranja = async () => {
    form.value.piscina_id = "T";
    await piscinasOptions();
    scheduleReload();
};

const openModelAlerts = (modelCode = null) => {
    const query = new URLSearchParams();
    if (form.value.piscina_id !== "T") query.set("piscina_id", form.value.piscina_id);
    if (modelCode) query.set("modelo", modelCode);
    const suffix = query.toString();
    window.location.assign(`${route("monitoreo.alarmasmodelos.index")}${suffix ? `?${suffix}` : ""}`);
};

const selectModel = (code) => {
    if (code === MODEL_CODES.light) {
        openModelAlerts(code);
        return;
    }
    selectedCode.value = code;
    currentView.value = "detail";
};

const showCatalog = () => {
    currentView.value = "catalog";
    selectedCode.value = null;
};

const openDigitalTwin = (modelCode = null) => {
    const query = new URLSearchParams();
    if (form.value.piscina_id !== "T") query.set("piscina_id", form.value.piscina_id);
    if (modelCode) query.set("modelo", modelCode);
    const suffix = query.toString();
    window.location.assign(`${route("monitoreo.gemelodigitals.index")}${suffix ? `?${suffix}` : ""}`);
};

const relationshipRows = (model) => {
    const chart = model?.relationship?.chart;
    const series = chart?.series?.[0] ?? {};
    const categories = chart?.xAxis?.data ?? chart?.yAxis?.data ?? [];
    return (series.data ?? []).slice(0, 10).map((entry, index) => {
        const value = entry && typeof entry === "object" && !Array.isArray(entry) ? (entry.value ?? entry) : entry;
        const name = entry && typeof entry === "object" && entry.name
            ? entry.name
            : categories[index] ?? `Punto ${index + 1}`;
        if (Array.isArray(value)) {
            return { name, primary: value[0], secondary: value[1] ?? "-" };
        }
        return { name, primary: value, secondary: "-" };
    });
};

onMounted(async () => {
    try {
        await piscigranjasOptions();
        await piscinasOptions();
    } finally {
        loadModelos();
    }
});

onBeforeUnmount(() => {
    requestController.value?.abort();
    clearTimeout(reloadTimer.value);
});
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <section class="module-intro mb-6">
            <div>
                <div class="text-gray-500 fs-7 mb-1">Piscina, datos reales y proyecciones</div>
                <h2 class="fs-2 fw-bold text-dark mb-2">Modelos de aprendizaje automatico</h2>
                <p class="text-gray-600 fs-6 mb-0">Elige un modelo para revisar su proyeccion. El gemelo digital tiene su propio modulo de simulacion de piscina.</p>
            </div>
            <div class="module-intro__actions">
                <el-button :icon="Bell" @click="openModelAlerts()">Alarmas de modelos</el-button>
                <el-button :icon="SetUp" @click="openDigitalTwin()">Gemelo digital</el-button>
                <el-button :icon="RefreshRight" :loading="loading" @click="loadModelos">Actualizar datos</el-button>
            </div>
        </section>

        <section class="filter-strip mb-6">
            <el-form :model="form" label-position="top" class="w-100">
                <div class="row g-4">
                    <div class="col-md-4 col-xl-3">
                        <el-form-item label="Piscigranja" class="mb-0">
                            <el-select filterable v-model="form.piscigranja_id" @change="changePiscigranja">
                                <el-option label="Todas" value="T" />
                                <el-option v-for="item in piscigranjas" :key="item.id" :label="item.nombre" :value="item.id" />
                            </el-select>
                        </el-form-item>
                    </div>
                    <div class="col-md-4 col-xl-3">
                        <el-form-item label="Piscina" class="mb-0">
                            <el-select filterable v-model="form.piscina_id" @change="scheduleReload">
                                <el-option label="Piscina principal" value="T" />
                                <el-option v-for="item in piscinasList" :key="item.id" :label="item.nombre" :value="item.id" />
                            </el-select>
                        </el-form-item>
                    </div>
                    <div class="col-md-4 col-xl-3">
                        <el-form-item label="Ventana visible" class="mb-0">
                            <el-select v-model="form.ventana" @change="scheduleReload">
                                <el-option v-for="item in ventanas" :key="item.id" :label="item.name" :value="item.id" />
                            </el-select>
                        </el-form-item>
                    </div>
                    <div class="col-md-4 col-xl-3">
                        <el-form-item label="Proyeccion de biometria" class="mb-0">
                            <el-select v-model="form.proyeccion_dias" @change="scheduleReload">
                                <el-option v-for="item in proyeccionesCrecimiento" :key="item.id" :label="item.name" :value="item.id" />
                            </el-select>
                        </el-form-item>
                    </div>
                </div>
            </el-form>
        </section>

        <el-alert v-if="errorMessage" class="mb-6" type="warning" :title="errorMessage" show-icon :closable="false">
            <template #default>
                <el-button link type="primary" :loading="loading" @click="loadModelos">Reintentar ahora</el-button>
            </template>
        </el-alert>

        <template v-if="currentView === 'catalog'">
            <section class="catalog-heading mb-5">
                <div>
                    <h3 class="fs-4 fw-bold text-dark mb-1">Selecciona una vista</h3>
                    <p class="text-gray-600 fs-7 mb-0">Cada modelo abre sus variables, horizonte, explicacion y resultados.</p>
                </div>
                <span v-if="loading" class="text-gray-500 fs-8 d-flex align-items-center gap-2"><el-icon class="is-loading"><RefreshRight /></el-icon>Actualizando datos reales</span>
            </section>

            <section class="model-catalog-grid">
                <div v-for="item in catalog" :key="item.code">
                    <button type="button" class="model-choice h-100 text-start" @click="selectModel(item.code)">
                        <span class="model-choice__icon"><el-icon><component :is="item.icon" /></el-icon></span>
                        <span class="model-choice__body">
                            <span class="d-flex justify-content-between align-items-start gap-2">
                                <span class="fw-bold text-dark fs-6">{{ item.name }}</span>
                                <span v-if="item.code === MODEL_CODES.light" class="badge badge-light-warning">Esperando sensor</span>
                                <span v-else-if="models.find((model) => model.code === item.code)" :class="['badge', statusClass(models.find((model) => model.code === item.code)?.status)]">{{ statusLabel(models.find((model) => model.code === item.code)?.status) }}</span>
                            </span>
                            <span class="text-gray-600 fs-7 mt-3">{{ item.description }}</span>
                            <span v-if="item.modelType" class="model-choice__type">{{ item.modelType }}</span>
                            <span class="model-choice__footer">{{ item.horizon }}</span>
                        </span>
                    </button>
                </div>
            </section>

            <section v-if="response" class="snapshot-strip mt-6">
                <div><span>OD actual</span><strong>{{ formatValue(latest.oxigeno_disuelto, latest.oxigeno_disuelto_unit) }}</strong></div>
                <div><span>Ion nitrato</span><strong>{{ formatValue(latest.ion_nitrato, latest.ion_nitrato_unit) }}</strong></div>
                <div><span>Lecturas limpias</span><strong>{{ formatValue(summary.samples) }}</strong></div>
                <div><span>Ventana</span><strong>{{ response.filters?.window_label ?? "-" }}</strong></div>
            </section>
        </template>

        <template v-else-if="currentView === 'detail'">
            <div class="detail-nav mb-5">
                <el-button text :icon="RefreshRight" @click="showCatalog">Volver a modelos</el-button>
                <span class="text-gray-400">/</span>
                <span class="text-gray-600 fs-7">{{ activeMeta?.name }}</span>
            </div>

            <section v-if="activeMeta" class="model-overview mb-6">
                <div class="model-overview__icon"><el-icon><component :is="activeMeta.icon" /></el-icon></div>
                <div class="model-overview__copy">
                    <div class="d-flex align-items-center flex-wrap gap-3">
                        <h3 class="fs-2 fw-bold text-dark mb-0">{{ activeMeta.name }}</h3>
                        <span v-if="activeModel" :class="['badge', statusClass(activeModel.status)]">{{ statusLabel(activeModel.status) }}</span>
                    </div>
                    <p class="text-gray-700 fs-6 mt-2 mb-1">{{ activeMeta.description }}</p>
                    <p class="text-gray-500 fs-7 mb-0">{{ activeMeta.importance }}</p>
                </div>
                <el-button class="model-overview__action" :icon="SetUp" @click="openDigitalTwin(activeMeta.code)">Ver en gemelo digital</el-button>
            </section>

            <section v-if="activeMeta" class="model-controls mb-6">
                <div class="model-controls__group">
                    <span class="text-gray-500 fs-8 fw-semibold text-uppercase">Tiempo</span>
                    <el-select v-if="activeMeta.code === MODEL_CODES.growth" v-model="form.proyeccion_dias" class="model-controls__select" @change="scheduleReload">
                        <el-option v-for="item in proyeccionesCrecimiento" :key="item.id" :label="item.name" :value="item.id" />
                    </el-select>
                    <el-select v-else v-model="form.ventana" class="model-controls__select" @change="scheduleReload">
                        <el-option v-for="item in ventanas" :key="item.id" :label="item.name" :value="item.id" />
                    </el-select>
                </div>
                <div class="model-controls__group model-controls__group--wide">
                    <span class="text-gray-500 fs-8 fw-semibold text-uppercase">Variables utilizadas</span>
                    <div class="input-tags"><span v-for="input in activeMeta.inputs" :key="input">{{ input }}</span></div>
                </div>
                <div class="model-controls__group">
                    <span class="text-gray-500 fs-8 fw-semibold text-uppercase">Horizonte</span>
                    <strong class="text-dark fs-7">{{ activeMeta.horizon }}</strong>
                </div>
            </section>

            <section v-if="loading && !activeModel" class="card card-flush mb-6">
                <div class="card-body py-12"><el-skeleton :rows="7" animated /></div>
            </section>

            <template v-else-if="activeModel">
                <section class="row g-5 mb-6">
                    <div class="col-lg-4">
                        <div class="result-keyline h-100">
                            <span>Valor actual</span>
                            <strong>{{ formatValue(activeModel.current_value, activeModel.unit) }}</strong>
                            <small>{{ activeModel.engine }}</small>
                        </div>
                    </div>
                    <div class="col-lg-8">
                        <div class="result-keyline h-100">
                            <span>Uso en la piscina</span>
                            <strong class="fs-5">{{ activeModel.usage?.label ?? statusLabel(activeModel.status) }}</strong>
                            <small>{{ activeModel.usage?.detail ?? activeModel.message }}</small>
                        </div>
                    </div>
                </section>

                <!-- El agua puede frenar al modelo por debajo de su potencial: la
                     temperatura marca el techo, el oxigeno y el pH solo restan. -->
                <section v-if="activeModel.limiting_factors" class="card card-flush mb-6">
                    <div class="card-header">
                        <h4 class="card-title fw-bold text-dark fs-5">Que esta frenando este modelo</h4>
                    </div>
                    <div class="card-body pt-0">
                        <p class="text-gray-700 fs-6 mb-4">
                            Con la temperatura actual podria alcanzar
                            <strong>{{ formatValue(activeModel.potential_value, activeModel.unit) }}</strong>,
                            pero el agua solo se lo permite a
                            <strong>{{ formatValue(activeModel.current_value, activeModel.unit) }}</strong>.
                        </p>
                        <ul class="ps-4 mb-3">
                            <li class="fs-7 mb-2"
                                :class="activeModel.limiting_factors.oxygen.factor < 1 ? 'text-warning fw-semibold' : 'text-gray-600'">
                                {{ activeModel.limiting_factors.oxygen.detail }}
                            </li>
                            <li class="fs-7"
                                :class="activeModel.limiting_factors.ph.factor < 1 ? 'text-warning fw-semibold' : 'text-gray-600'">
                                {{ activeModel.limiting_factors.ph.detail }}
                            </li>
                        </ul>
                        <p class="text-gray-500 fs-8 mb-0">
                            Ley del minimo: manda el factor peor. Sin lecturas de oxigeno o pH no se aplica limitacion.
                        </p>
                    </div>
                </section>

                <!-- Modelo alometrico entrenado con biometria_detalles -->
                <section v-if="activeModel.traceability?.weight_length_ml" class="card card-flush mb-6">
                    <div class="card-header">
                        <h4 class="card-title fw-bold text-dark fs-5">Modelo peso-longitud entrenado</h4>
                    </div>
                    <div class="card-body pt-0">
                        <code class="d-block text-dark fs-7 mb-4">{{ activeModel.traceability.weight_length_ml.formula }}</code>
                        <div class="row g-4">
                            <div class="col-sm-3">
                                <small class="text-gray-500 d-block">Peces medidos</small>
                                <strong class="fs-5">{{ activeModel.traceability.weight_length_ml.sample_size }}</strong>
                                <small class="text-gray-500 d-block">
                                    {{ activeModel.traceability.weight_length_ml.train_size }} entrenamiento /
                                    {{ activeModel.traceability.weight_length_ml.test_size }} prueba
                                </small>
                            </div>
                            <div class="col-sm-3">
                                <small class="text-gray-500 d-block">Acierto en prueba</small>
                                <strong class="fs-5">R2 {{ activeModel.traceability.weight_length_ml.metrics.test_r2 }}</strong>
                                <small class="text-gray-500 d-block">error {{ activeModel.traceability.weight_length_ml.metrics.test_mae_g }} g</small>
                            </div>
                            <div class="col-sm-3">
                                <small class="text-gray-500 d-block">Peso medio</small>
                                <strong class="fs-5">{{ activeModel.traceability.weight_length_ml.baselines.media.test_mae_g }} g</strong>
                                <small class="text-gray-500 d-block">de error</small>
                            </div>
                            <div class="col-sm-3">
                                <small class="text-gray-500 d-block">Ley cubica</small>
                                <strong class="fs-5">{{ activeModel.traceability.weight_length_ml.baselines.isometrico_cubico.test_mae_g }} g</strong>
                                <small class="text-gray-500 d-block">de error</small>
                            </div>
                        </div>
                        <p class="text-gray-700 fs-7 mt-4 mb-0">{{ activeModel.traceability.weight_length_ml.verdict }}</p>
                        <p class="text-gray-500 fs-8 mb-0">{{ activeModel.traceability.weight_length_ml.note }}</p>
                    </div>
                </section>

                <section class="row g-5 mb-6">
                    <div class="col-xl-8">
                        <div class="card card-flush h-100">
                            <div class="card-header"><h4 class="card-title fw-bold text-dark fs-5">{{ activeModel.chart?.title?.text ?? activeModel.name }}</h4></div>
                            <div class="card-body pt-0">
                                <p class="text-gray-600 fs-7">{{ activeModel.chart_description }}</p>
                                <ChartFisheye :options="activeModel.chart" height="420px" />
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-4">
                        <div :class="['formula-panel h-100', modelFormulaTone(activeModel.code)]">
                            <span class="text-gray-500 fs-8 fw-semibold text-uppercase">Formula y origen</span>
                            <LatexFormula v-if="activeModel.formula?.latex" :latex="activeModel.formula.latex" />
                            <code v-else class="d-block text-dark fs-7 text-break mt-3">{{ activeModel.formula?.expression }}</code>
                            <p class="text-gray-700 fs-7 mt-4">{{ activeModel.formula?.detail }}</p>
                            <ul v-if="activeModel.formula?.conditions?.length" class="text-gray-600 fs-8 ps-4 mb-4">
                                <li v-for="condition in activeModel.formula.conditions" :key="condition">{{ condition }}</li>
                            </ul>
                            <div class="formula-panel__origin">{{ activeModel.origin?.document }}<br>{{ activeModel.origin?.data }}</div>
                        </div>
                    </div>
                </section>

                <section v-if="activeModel.relationship" class="card card-flush mb-6">
                    <div class="card-header"><h4 class="card-title fw-bold text-dark fs-5">Relacion de variables</h4></div>
                    <div class="card-body pt-0">
                        <p class="text-gray-600 fs-7">{{ activeModel.relationship.description }}</p>
                        <ChartFisheye :options="activeModel.relationship.chart" height="350px" />
                        <div v-if="relationshipRows(activeModel).length" class="relationship-table mt-5">
                            <div class="text-gray-500 fs-8 fw-semibold text-uppercase mb-2">Puntos trazados</div>
                            <div class="table-responsive"><table class="table table-sm table-row-dashed align-middle mb-0"><thead><tr class="text-gray-500 fs-8"><th>Variable o punto</th><th class="text-end">Valor X</th><th class="text-end">Valor Y</th></tr></thead><tbody><tr v-for="row in relationshipRows(activeModel)" :key="`${row.name}-${row.primary}`"><td class="fs-8">{{ row.name }}</td><td class="text-end fs-8">{{ formatValue(row.primary) }}</td><td class="text-end fs-8">{{ formatValue(row.secondary) }}</td></tr></tbody></table></div>
                        </div>
                    </div>
                </section>

                <section v-if="activeModel.code === MODEL_CODES.growth && selectedBiometrics?.available" class="pond-twin mb-6">
                    <div class="pond-twin__header">
                        <div><span class="text-gray-500 fs-8 fw-semibold text-uppercase">Gemelo digital de biometria</span><h4 class="fs-5 fw-bold text-dark mb-0">Evolucion del estanque</h4></div>
                        <span class="badge badge-light-primary">Biometria real</span>
                    </div>
                    <div class="pond-twin__timeline">
                        <div class="pond-twin__step"><span class="pond-twin__dot"></span><small>Ultima muestra</small><strong>{{ formatValue(selectedBiometrics.longitud_promedio_cm, "cm") }}</strong><span>{{ formatValue(selectedBiometrics.peso_promedio_g, "g") }}</span></div>
                        <div class="pond-twin__line"></div>
                        <div class="pond-twin__step"><span class="pond-twin__dot pond-twin__dot--current"></span><small>Temperatura actual</small><strong>{{ formatValue(activeModel.current_value, activeModel.unit) }}</strong><span>Ganancia diaria</span></div>
                        <div class="pond-twin__line"></div>
                        <div class="pond-twin__step"><span class="pond-twin__dot pond-twin__dot--future"></span><small>Proyeccion {{ growthProjection?.projection_days ?? form.proyeccion_dias }} dias</small><strong>{{ formatValue(growthProjection?.projected_length_mm, "mm") }}</strong><span>{{ formatValue(growthProjection?.projected_weight_g, "g") }}</span></div>
                    </div>
                    <p class="text-gray-600 fs-8 mt-5 mb-0">FCA {{ metricValue(selectedBiometrics.conversion_alimenticia) }}: {{ selectedBiometrics.conversion_label }}. La curva longitud-peso usada se muestra en la formula del modelo.</p>
                </section>

                <section class="row g-5 mb-6">
                    <div class="col-xl-5">
                        <div v-if="activeModel.asset_id || hasMetrics(activeModel)" class="info-block h-100">
                            <span class="text-gray-500 fs-8 fw-semibold text-uppercase">Modelo y validacion</span>
                            <div v-if="activeModel.asset_id" class="mt-3"><strong>{{ activeModel.version ?? "sin version" }}</strong><div class="text-gray-500 fs-8">{{ shortId(activeModel.asset_id) }}</div></div>
                            <div v-if="activeModel.metrics?.mae !== undefined" class="mt-3 text-gray-700 fs-7">MAE: <strong>{{ metricValue(activeModel.metrics.mae) }}</strong></div>
                            <div v-if="activeModel.metrics?.r2 !== undefined" class="text-gray-700 fs-7">R2: <strong>{{ metricValue(activeModel.metrics.r2) }}</strong></div>
                            <div v-if="activeModel.metrics?.f1_weighted !== undefined" class="mt-3 text-gray-700 fs-7">F1 ponderado: <strong>{{ metricValue(activeModel.metrics.f1_weighted) }}</strong></div>
                            <div v-if="activeModel.metrics?.accuracy !== undefined" class="text-gray-700 fs-7">Exactitud: <strong>{{ metricValue(activeModel.metrics.accuracy) }}</strong></div>
                        </div>
                    </div>
                    <div class="col-xl-7">
                        <div v-if="(activeModel.forecast ?? []).length" class="info-block h-100">
                            <span class="text-gray-500 fs-8 fw-semibold text-uppercase">Resultado para el horizonte elegido</span>
                            <div class="table-responsive mt-3">
                                <table class="table table-sm table-row-dashed align-middle mb-0"><tbody><tr v-for="item in activeModel.forecast.slice(0, 6)" :key="`${activeModel.code}-${item.label}-${item.timestamp}`"><td class="text-gray-600 fs-7">{{ item.label }}</td><td class="text-end fw-bold text-dark fs-7">{{ formatValue(item.value, item.unit ?? activeModel.unit) }}</td></tr></tbody></table>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="activeModel.machine_learning || activeModel.components?.length || activeModel.interpretation?.length" class="row g-5 mb-6">
                    <div v-if="activeModel.machine_learning" class="col-xl-4"><div class="info-block h-100"><span class="text-gray-500 fs-8 fw-semibold text-uppercase">Aprendizaje automatico</span><p class="text-gray-700 fs-7 mt-3 mb-2">{{ activeModel.machine_learning.detail }}</p><strong v-if="activeModel.machine_learning.classification">{{ activeModel.machine_learning.classification }}</strong><div class="text-gray-500 fs-8 mt-2">{{ activeModel.machine_learning.target_origin }}</div></div></div>
                    <div v-if="activeModel.components?.length" class="col-xl-4"><div class="info-block h-100"><span class="text-gray-500 fs-8 fw-semibold text-uppercase">Variables del indice</span><table class="table table-sm table-row-dashed align-middle mt-3 mb-0"><tbody><tr v-for="component in activeModel.components" :key="component.variable"><td class="fs-8">{{ component.variable }}</td><td class="text-end fs-8">{{ formatValue(component.raw_value, component.unit) }}</td><td class="text-end fw-bold fs-8">Q {{ formatValue(component.normalized_score) }}</td></tr></tbody></table></div></div>
                    <div v-if="activeModel.interpretation?.length" class="col-xl-4"><div class="info-block h-100"><span class="text-gray-500 fs-8 fw-semibold text-uppercase">Interpretacion</span><table class="table table-sm table-row-dashed align-middle mt-3 mb-0"><tbody><tr v-for="item in activeModel.interpretation" :key="`${item.range}-${item.label}`"><td class="fs-8">{{ interpretationRange(item) }}</td><td class="text-end fw-bold fs-8">{{ item.label }}</td></tr></tbody></table></div></div>
                </section>

                <section v-if="lifecycle.summary?.length" class="lifecycle-strip mb-6">
                    <div v-for="item in lifecycle.summary" :key="item.step"><strong>{{ item.step }}</strong><span>{{ item.detail }}</span></div>
                </section>
            </template>
        </template>

    </App>
</template>

<style scoped>
.module-intro,
.catalog-heading,
.detail-nav,
.model-overview,
.model-controls,
.pond-twin__header,
.snapshot-strip,
.lifecycle-strip,
.twin-layout {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.module-intro {
    align-items: flex-end;
}

.module-intro__actions {
    flex: 0 0 auto;
    display: flex;
    gap: 8px;
}

.filter-strip,
.model-controls,
.twin-layout,
.pond-twin,
.snapshot-strip,
.lifecycle-strip,
.warning-strip {
    border: 1px solid #e5e7eb;
    background: #ffffff;
}

.filter-strip {
    padding: 18px 20px;
}

.model-catalog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
}

.model-choice {
    width: 100%;
    display: flex;
    gap: 14px;
    border: 1px solid #dbe3ee;
    background: #ffffff;
    padding: 20px;
    transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}

.model-choice:hover,
.model-choice:focus-visible {
    border-color: #4d8dff;
    box-shadow: 0 8px 20px rgba(18, 74, 155, 0.12);
    transform: translateY(-2px);
    outline: none;
}

.model-choice__icon,
.model-overview__icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #0d6efd;
    background: #edf5ff;
    font-size: 21px;
}

.model-overview__icon {
    color: #087f5b;
    background: #eafaf3;
}

.model-choice__body {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.model-choice__footer {
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
    margin-top: auto;
    padding-top: 16px;
}

.model-choice__type {
    color: #0d6efd;
    font-size: 12px;
    font-weight: 600;
    margin-top: 12px;
}

.snapshot-strip,
.lifecycle-strip {
    align-items: stretch;
    overflow-x: auto;
}

.snapshot-strip > div,
.lifecycle-strip > div {
    min-width: 170px;
    flex: 1;
    padding: 16px 18px;
    border-right: 1px solid #eef1f5;
}

.snapshot-strip > div:last-child,
.lifecycle-strip > div:last-child {
    border-right: 0;
}

.snapshot-strip span,
.lifecycle-strip span {
    display: block;
    color: #64748b;
    font-size: 12px;
}

.snapshot-strip strong {
    display: block;
    color: #172033;
    font-size: 18px;
    margin-top: 4px;
}

.detail-nav {
    justify-content: flex-start;
    gap: 10px;
}

.model-overview {
    align-items: flex-start;
    justify-content: flex-start;
    padding: 24px 0;
    border-bottom: 1px solid #e5e7eb;
}

.model-overview__copy {
    max-width: 850px;
}

.model-overview__action {
    margin-left: auto;
    flex: 0 0 auto;
}

.relationship-table {
    border-top: 1px solid #e5e7eb;
    padding-top: 16px;
}

.model-controls {
    align-items: stretch;
    justify-content: flex-start;
    padding: 16px;
    flex-wrap: wrap;
}

.model-controls__group {
    min-width: 180px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.model-controls__group--wide {
    flex: 1 1 360px;
}

.model-controls__select {
    width: 220px;
    max-width: 100%;
}

.input-tags,
.twin-checkboxes {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.input-tags span {
    border: 1px solid #dbe3ee;
    padding: 5px 8px;
    color: #475569;
    font-size: 12px;
    background: #f8fafc;
}

.result-keyline,
.info-block {
    border: 1px solid #e1e7ef;
    padding: 20px;
    background: #ffffff;
}

.result-keyline span,
.info-block > span {
    display: block;
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.result-keyline strong {
    display: block;
    color: #172033;
    font-size: 28px;
    line-height: 1.2;
    margin: 9px 0 7px;
}

.result-keyline small {
    display: block;
    color: #64748b;
    font-size: 12px;
    line-height: 1.45;
}

.formula-panel {
    border: 1px solid #dbe3ee;
    padding: 22px;
    background: #ffffff;
}

.formula-panel :deep(.katex) {
    color: #172033;
    font-size: 1.12em;
}

.formula-panel__origin {
    border-top: 1px solid #e5e7eb;
    padding-top: 12px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.formula-svm { border-left: 3px solid #0d6efd; }
.formula-oxygen { border-left: 3px solid #16803c; }
.formula-growth { border-left: 3px solid #7048c8; }
.formula-water { border-left: 3px solid #0f766e; }
.formula-default { border-left: 3px solid #64748b; }

.pond-twin {
    padding: 20px;
    background: #f7fbff;
}

.pond-twin__timeline {
    display: flex;
    align-items: center;
    margin-top: 24px;
}

.pond-twin__step {
    width: 31%;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.pond-twin__step small,
.pond-twin__step span {
    color: #64748b;
    font-size: 12px;
}

.pond-twin__step strong {
    color: #172033;
    font-size: 20px;
    margin: 5px 0;
}

.pond-twin__dot {
    width: 12px;
    height: 12px;
    background: #0d6efd;
    display: block;
    margin-bottom: 10px;
}

.pond-twin__dot--current { background: #16803c; }
.pond-twin__dot--future { background: #7048c8; }

.pond-twin__line {
    height: 2px;
    flex: 1;
    background: #b8c7d8;
    margin: 0 12px 30px;
}

.twin-layout {
    align-items: stretch;
}

.twin-inputs,
.twin-reference {
    padding: 22px;
}

.twin-inputs {
    flex: 1 1 66%;
}

.twin-reference {
    flex: 1 1 34%;
    border-left: 1px solid #e5e7eb;
    background: #f8fafc;
}

.twin-inputs label {
    display: block;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 6px;
}

.twin-inputs :deep(.el-input-number),
.twin-inputs :deep(.el-select) {
    width: 100%;
}

.twin-checkboxes {
    margin-top: 9px;
}

.twin-reference__value {
    color: #172033;
    font-weight: 700;
    font-size: 16px;
    margin-top: 10px;
}

.twin-reference p {
    color: #64748b;
    font-size: 13px;
    line-height: 1.55;
    margin-top: 14px;
}

.twin-reference__biometrics {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-top: 14px;
    border-top: 1px solid #dbe3ee;
}

.twin-reference__biometrics span,
.twin-reference__biometrics small { color: #64748b; font-size: 12px; }
.twin-reference__biometrics strong { color: #172033; }

.warning-strip {
    padding: 16px 20px;
}

.warning-strip strong,
.warning-strip span {
    display: block;
    color: #7c5d00;
    font-size: 13px;
}

.warning-strip span { margin-top: 5px; }

@media (max-width: 991px) {
    .module-intro,
    .twin-layout {
        flex-direction: column;
        align-items: stretch;
    }

    .twin-reference { border-left: 0; border-top: 1px solid #e5e7eb; }
    .model-overview__action { margin-left: 0; }
}

@media (max-width: 650px) {
    .model-controls,
    .catalog-heading,
    .pond-twin__header {
        align-items: flex-start;
        flex-direction: column;
    }

    .snapshot-strip > div,
    .lifecycle-strip > div { min-width: 155px; }
    .pond-twin__timeline { align-items: flex-start; }
    .pond-twin__line { margin-top: 5px; }
    .pond-twin__step strong { font-size: 16px; }
    .model-catalog-grid { grid-template-columns: 1fr; }
}
</style>
