<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import {
    ArrowLeft,
    Bell,
    Connection,
    DataAnalysis,
    RefreshRight,
    SetUp,
    Sunny,
    TrendCharts,
    Warning,
} from "@element-plus/icons-vue";
import ChartFisheye from "@/Components/ChartFisheye.vue";
import PiscinaDigital3D from "@/Components/PiscinaDigital3D.vue";

defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false,
    },
});

const MODEL_CODES = {
    ica: "WATER_QUALITY_INDEX_ICA",
    growth: "TILAPIA_GROWTH_TEMPERATURE",
    svm: "SVM_OD_FORECAST_1H",
    light: "LIGHT_FEED_RESPONSE_CLASSIFIER_V1",
};

const modelIcons = {
    [MODEL_CODES.ica]: Connection,
    [MODEL_CODES.growth]: TrendCharts,
    [MODEL_CODES.svm]: DataAnalysis,
    [MODEL_CODES.light]: Sunny,
};

const loading = ref(false);
const scenarioLoading = ref(false);
const errorMessage = ref("");
const response = ref(null);
const lightScenarioResult = ref(null);
const selectedCode = ref(new URLSearchParams(window.location.search).get("modelo") || MODEL_CODES.ica);
const piscigranjas = ref([]);
const piscinas = ref([]);
const requestController = ref(null);
const reloadTimer = ref(null);

const filters = ref({
    piscigranja_id: "T",
    piscina_id: new URLSearchParams(window.location.search).get("piscina_id") || "T",
    ventana_horas: 24,
});

const lightScenario = ref({
    maximum_lux: 500,
    current_lux: null,
    photoperiod_hours: 12,
    dawn_hour: 6,
    horizon_hours: 24,
});

const windowOptions = [
    { value: 6, label: "Ultimas 6 horas" },
    { value: 24, label: "Ultimas 24 horas" },
    { value: 168, label: "Ultimos 7 dias" },
    { value: 720, label: "Ultimos 30 dias" },
];

const models = computed(() => response.value?.models ?? []);
const summary = computed(() => response.value?.summary ?? {});
const light = computed(() => response.value?.light ?? {});
const events = computed(() => response.value?.events ?? []);
const observations = computed(() => response.value?.technical_observations ?? []);
const activeModel = computed(() => models.value.find((model) => model.code === selectedCode.value) ?? models.value[0] ?? null);
const isLight = computed(() => activeModel.value?.code === MODEL_CODES.light);
const activeChart = computed(() => {
    if (isLight.value && lightScenarioResult.value?.chart) return lightScenarioResult.value.chart;
    return activeModel.value?.projection?.chart ?? null;
});
const lightLevel = computed(() => Number(
    lightScenarioResult.value?.twin?.light_level
    ?? light.value.latest_value
    ?? lightScenario.value.current_lux
    ?? 0,
));
const lightMode = computed(() => {
    if (lightScenarioResult.value) return "manual";
    if (light.value.latest_value !== null && light.value.latest_value !== undefined) return "observed";
    return "unavailable";
});
const lightPhase = computed(() => lightScenarioResult.value?.twin?.phase ?? "sin fase medida");
const dataSourceLabel = computed(() => ({
    fastapi_model_alert_contract: "Alarmas calculadas por FastAPI local",
    legacy_fastapi_adapter: "Datos locales disponibles",
    unavailable: "Backend no disponible",
}[response.value?.meta?.source] ?? "FastAPI local"));

const formatNumber = (value, unit = "") => {
    if (value === null || value === undefined || value === "") return "N/D";
    const number = Number(value);
    if (!Number.isFinite(number)) return "N/D";
    const formatted = number.toLocaleString("es-PE", { maximumFractionDigits: 3 });
    return unit ? `${formatted} ${unit}` : formatted;
};

const formatDate = (value) => {
    if (!value) return "N/D";
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? "N/D" : date.toLocaleString("es-PE");
};

const maturityLabel = (maturity) => ({
    active: "Activo",
    ready_for_policy: "Listo para politica",
    shadow: "Modo sombra",
    collecting_data: "Recopilando datos",
    blocked_inputs: "Bloqueado por datos",
    candidate: "Candidato",
}[maturity] ?? "Sin evaluar");

const maturityClass = (maturity) => {
    if (maturity === "active") return "state-active";
    if (["ready_for_policy", "shadow", "collecting_data"].includes(maturity)) return "state-review";
    return "state-blocked";
};

const alarmLabel = (model) => model?.can_emit ? "Puede emitir" : "No emite alarmas";

const loadPiscigranjas = async () => {
    try {
        const { data } = await axios.get(route("piscigranjas.options"));
        piscigranjas.value = data.data ?? [];
    } catch {
        piscigranjas.value = [];
    }
};

const loadPiscinas = async () => {
    if (filters.value.piscigranja_id === "T") {
        piscinas.value = [];
        return;
    }
    try {
        const { data } = await axios.get(route("piscigranjas.piscinas", filters.value.piscigranja_id));
        piscinas.value = data ?? [];
    } catch {
        piscinas.value = [];
    }
};

const loadDashboard = async (refresh = false) => {
    requestController.value?.abort();
    const controller = new AbortController();
    requestController.value = controller;
    loading.value = true;
    errorMessage.value = "";

    try {
        const { data } = await axios.get(route("monitoreo.alarmasmodelos.datos"), {
            params: {
                piscina_id: filters.value.piscina_id,
                ventana_horas: filters.value.ventana_horas,
                refresh: refresh ? 1 : 0,
            },
            signal: controller.signal,
        });
        response.value = data;
        if (!models.value.some((model) => model.code === selectedCode.value)) {
            selectedCode.value = models.value[0]?.code ?? MODEL_CODES.ica;
        }
        if (light.value.latest_value !== null && light.value.latest_value !== undefined) {
            lightScenario.value.current_lux = Number(light.value.latest_value);
            lightScenario.value.maximum_lux = Math.max(1, Number(light.value.latest_value));
        }
    } catch (error) {
        if (error?.code !== "ERR_CANCELED") {
            errorMessage.value = error?.response?.data?.message ?? "No se pudo cargar el estado de las alarmas de modelos.";
        }
    } finally {
        if (requestController.value === controller) loading.value = false;
    }
};

const scheduleReload = () => {
    clearTimeout(reloadTimer.value);
    reloadTimer.value = setTimeout(() => loadDashboard(false), 250);
};

const changeFarm = async () => {
    filters.value.piscina_id = "T";
    await loadPiscinas();
    scheduleReload();
};

const runLightScenario = async () => {
    scenarioLoading.value = true;
    errorMessage.value = "";
    try {
        const { data } = await axios.post(route("monitoreo.alarmasmodelos.luz.escenario"), lightScenario.value);
        lightScenarioResult.value = data;
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? "No se pudo calcular el escenario manual de luz.";
    } finally {
        scenarioLoading.value = false;
    }
};

const selectModel = (code) => {
    selectedCode.value = code;
    if (code === MODEL_CODES.light && !lightScenarioResult.value) runLightScenario();
};

const openTwin = () => {
    const query = new URLSearchParams({ modelo: activeModel.value?.code ?? MODEL_CODES.ica });
    if (filters.value.piscina_id !== "T") query.set("piscina_id", filters.value.piscina_id);
    window.location.assign(`${route("monitoreo.gemelodigitals.index")}?${query.toString()}`);
};

const openModels = () => window.location.assign(route("monitoreo.modelosmls.index"));

onMounted(async () => {
    await Promise.all([loadPiscigranjas(), loadPiscinas()]);
    await loadDashboard(false);
    if (selectedCode.value === MODEL_CODES.light) runLightScenario();
    window.Echo?.private("alarmas.modelos").listen(".alarma.generada", () => loadDashboard(false));
});

onBeforeUnmount(() => {
    requestController.value?.abort();
    clearTimeout(reloadTimer.value);
    window.Echo?.leave("alarmas.modelos");
});
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <section class="page-heading mb-6">
            <div>
                <div class="text-gray-500 fs-7 mb-1">Predicciones, evidencia y disponibilidad real</div>
                <h2 class="fs-2 fw-bold text-dark mb-2">Alarmas de modelos</h2>
                <p class="text-gray-600 fs-6 mb-0">Solo aparecen como productivas las condiciones calculadas por un modelo activo y una politica aprobada.</p>
            </div>
            <div class="page-actions">
                <el-button :icon="ArrowLeft" @click="openModels">Modelos</el-button>
                <el-button :icon="SetUp" @click="openTwin">Gemelo digital</el-button>
                <el-button :icon="RefreshRight" :loading="loading" @click="loadDashboard(true)">Actualizar</el-button>
            </div>
        </section>

        <section class="filter-band mb-5">
            <el-form :model="filters" label-position="top" class="w-100">
                <div class="row g-4">
                    <div class="col-md-4">
                        <el-form-item label="Piscigranja" class="mb-0">
                            <el-select v-model="filters.piscigranja_id" filterable @change="changeFarm">
                                <el-option label="Todas" value="T" />
                                <el-option v-for="item in piscigranjas" :key="item.id" :label="item.nombre" :value="String(item.id)" />
                            </el-select>
                        </el-form-item>
                    </div>
                    <div class="col-md-4">
                        <el-form-item label="Piscina" class="mb-0">
                            <el-select v-model="filters.piscina_id" filterable @change="scheduleReload">
                                <el-option label="Piscina principal" value="T" />
                                <el-option v-for="item in piscinas" :key="item.id" :label="item.nombre" :value="String(item.id)" />
                            </el-select>
                        </el-form-item>
                    </div>
                    <div class="col-md-4">
                        <el-form-item label="Ventana de evidencia" class="mb-0">
                            <el-select v-model="filters.ventana_horas" @change="scheduleReload">
                                <el-option v-for="item in windowOptions" :key="item.value" :label="item.label" :value="item.value" />
                            </el-select>
                        </el-form-item>
                    </div>
                </div>
            </el-form>
        </section>

        <el-alert v-if="errorMessage" class="mb-5" type="warning" :title="errorMessage" show-icon :closable="false" />
        <el-alert
            v-else-if="response?.meta?.degraded || response?.meta?.stale"
            class="mb-5"
            type="warning"
            :title="response.meta.message"
            show-icon
            :closable="false"
        />
        <el-alert
            v-if="response?.meta?.alarm_storage?.available === false"
            class="mb-5"
            type="warning"
            title="El esquema compartido de alarmas aun no esta migrado; las predicciones se muestran sin crear notificaciones."
            show-icon
            :closable="false"
        />

        <section v-if="loading && !response" class="initial-loading mb-6" aria-live="polite">
            <div class="initial-loading__heading">
                <el-icon class="is-loading"><RefreshRight /></el-icon>
                <div><strong>Consultando modelos y evidencia local</strong><span>La pantalla permanece disponible mientras FastAPI prepara los resultados.</span></div>
            </div>
            <el-skeleton :rows="5" animated />
        </section>

        <section v-if="response" class="summary-band mb-6" aria-label="Resumen de alarmas de modelos">
            <div><span>Alarmas activas</span><strong>{{ summary.active_events ?? 0 }}</strong><small>confirmadas por contrato</small></div>
            <div><span>Pueden emitir</span><strong>{{ summary.can_emit ?? 0 }}</strong><small>modelo y politica activos</small></div>
            <div><span>Modo sombra</span><strong>{{ summary.shadow ?? 0 }}</strong><small>sin notificar al productor</small></div>
            <div><span>Bloqueadas</span><strong>{{ summary.blocked ?? 0 }}</strong><small>datos o etiquetas faltantes</small></div>
            <div><span>Fuente</span><strong class="summary-band__source">{{ dataSourceLabel }}</strong><small>{{ formatDate(response?.generated_at) }}</small></div>
        </section>

        <section v-if="response" class="model-grid mb-7" aria-label="Modelos que pueden originar alarmas">
            <button
                v-for="model in models"
                :key="model.code"
                type="button"
                :class="['model-card', { 'model-card--active': model.code === activeModel?.code }]"
                @click="selectModel(model.code)"
            >
                <span class="model-card__icon"><el-icon><component :is="modelIcons[model.code] ?? Bell" /></el-icon></span>
                <span class="model-card__content">
                    <span class="model-card__topline">
                        <span :class="['model-state', maturityClass(model.maturity)]">{{ maturityLabel(model.maturity) }}</span>
                        <span :class="['emission-state', { 'emission-state--enabled': model.can_emit }]">{{ alarmLabel(model) }}</span>
                    </span>
                    <strong>{{ model.name }}</strong>
                    <span>{{ model.purpose }}</span>
                    <small>{{ model.horizon }}</small>
                </span>
            </button>
        </section>

        <template v-if="activeModel">
            <section class="detail-heading mb-5">
                <div class="detail-heading__identity">
                    <span class="detail-heading__icon"><el-icon><component :is="modelIcons[activeModel.code] ?? Bell" /></el-icon></span>
                    <div>
                        <div class="d-flex align-items-center flex-wrap gap-2 mb-1">
                            <h3 class="fs-3 fw-bold text-dark mb-0">{{ activeModel.name }}</h3>
                            <span :class="['model-state', maturityClass(activeModel.maturity)]">{{ maturityLabel(activeModel.maturity) }}</span>
                        </div>
                        <p class="text-gray-600 fs-7 mb-0">{{ activeModel.status_detail }}</p>
                    </div>
                </div>
                <el-button :icon="SetUp" @click="openTwin">Abrir en gemelo</el-button>
            </section>

            <section class="evidence-band mb-6">
                <div><span>Valor reciente</span><strong>{{ formatNumber(activeModel.current_value, activeModel.unit) }}</strong><small>{{ formatDate(activeModel.data_timestamp) }}</small></div>
                <div><span>Horizonte</span><strong>{{ activeModel.horizon }}</strong><small>{{ activeModel.alarm_code }}</small></div>
                <div><span>Politica</span><strong>{{ activeModel.policy?.status === "approved" ? "Aprobada" : "Borrador" }}</strong><small>{{ activeModel.policy?.condition }}</small></div>
                <div><span>Emision</span><strong>{{ alarmLabel(activeModel) }}</strong><small>{{ activeModel.can_emit ? "Evento productivo habilitado" : "Sin evento para el productor" }}</small></div>
            </section>

            <section class="model-context mb-6">
                <div>
                    <h4 class="fs-5 fw-bold text-dark mb-2">Variables utilizadas</h4>
                    <div class="input-list"><span v-for="input in activeModel.inputs" :key="input">{{ input }}</span></div>
                </div>
                <div>
                    <h4 class="fs-5 fw-bold text-dark mb-2">Condiciones pendientes</h4>
                    <ul v-if="activeModel.missing_inputs?.length" class="pending-list mb-0">
                        <li v-for="input in activeModel.missing_inputs" :key="input">{{ input }}</li>
                    </ul>
                    <p v-else class="text-gray-600 fs-7 mb-0">Validacion de la politica, persistencia, severidad y ejecucion en sombra.</p>
                </div>
            </section>

            <section v-if="isLight" class="light-workbench mb-6">
                <div class="light-readiness">
                    <div>
                        <span class="text-gray-500 fs-8 fw-semibold text-uppercase">Sensor de luz</span>
                        <strong>{{ light.sensor_registered ? "Registrado" : "No registrado" }}</strong>
                        <small>{{ light.observation_count ?? 0 }} lecturas reconocidas</small>
                    </div>
                    <div>
                        <span class="text-gray-500 fs-8 fw-semibold text-uppercase">Ultima lectura</span>
                        <strong>{{ formatNumber(light.latest_value, light.unit) }}</strong>
                        <small>{{ formatDate(light.latest_at) }}</small>
                    </div>
                    <div>
                        <span class="text-gray-500 fs-8 fw-semibold text-uppercase">Alarma</span>
                        <strong>No habilitada</strong>
                        <small>Falta artefacto activo y politica aprobada</small>
                    </div>
                </div>

                <div class="scenario-controls mt-5">
                    <div><label>Intensidad maxima (lux)</label><el-input-number v-model="lightScenario.maximum_lux" :min="0" :max="200000" :step="50" controls-position="right" /></div>
                    <div><label>Fotoperiodo (horas)</label><el-input-number v-model="lightScenario.photoperiod_hours" :min="0" :max="24" :step="0.5" controls-position="right" /></div>
                    <div><label>Inicio de luz (hora)</label><el-input-number v-model="lightScenario.dawn_hour" :min="0" :max="23.5" :step="0.5" controls-position="right" /></div>
                    <div><label>Horizonte (horas)</label><el-select v-model="lightScenario.horizon_hours"><el-option :value="12" label="12 horas" /><el-option :value="24" label="24 horas" /><el-option :value="48" label="48 horas" /><el-option :value="72" label="72 horas" /></el-select></div>
                    <el-button type="primary" :icon="Sunny" :loading="scenarioLoading" @click="runLightScenario">Proyectar escenario</el-button>
                </div>
                <p class="scenario-disclaimer mb-0">Escenario manual de protocolo. No usa un artefacto entrenado y no se guarda como prediccion real.</p>
            </section>

            <section v-if="activeChart" class="projection-panel mb-6">
                <div class="projection-panel__heading">
                    <div>
                        <span class="text-gray-500 fs-8 fw-semibold text-uppercase">{{ isLight ? "Escenario y serie disponible" : "Evidencia del modelo" }}</span>
                        <h4 class="fs-5 fw-bold text-dark mb-0">{{ isLight ? "Dinamica luminosa" : "Observado y proyectado" }}</h4>
                    </div>
                    <span v-if="isLight && lightScenarioResult" class="manual-badge">Escenario manual</span>
                </div>
                <ChartFisheye :options="activeChart" height="380px" />
            </section>

            <template v-if="isLight">
                <section class="twin-heading mb-3">
                    <div><span class="text-gray-500 fs-8 fw-semibold text-uppercase">Gemelo digital</span><h4 class="fs-5 fw-bold text-dark mb-0">Piscina bajo el escenario luminoso</h4></div>
                    <span class="text-gray-500 fs-8">{{ lightMode === "observed" ? "Dato observado" : lightMode === "manual" ? "Escenario manual" : "Sin sensor" }}</span>
                </section>
                <PiscinaDigital3D
                    :quality-index="70"
                    :temperature-c="28"
                    :average-weight-g="0"
                    :projected-weight-g="0"
                    :estimated-fish-count="0"
                    :focused-model="activeModel.name"
                    :light-level-lux="lightLevel"
                    :light-mode="lightMode"
                    :light-phase="lightPhase"
                />
                <div class="alarm-preview mt-4 mb-7">
                    <el-icon><Warning /></el-icon>
                    <div><strong>Alarma de luz no emitida</strong><span>{{ lightScenarioResult?.alarm_preview?.message ?? light.alarm?.message }}</span></div>
                </div>
            </template>
        </template>

        <section v-if="response" class="events-section mb-7">
            <div class="section-heading mb-4">
                <div><span class="text-gray-500 fs-8 fw-semibold text-uppercase">Contrato productivo</span><h3 class="fs-5 fw-bold text-dark mb-0">Eventos de modelos</h3></div>
                <span class="text-gray-500 fs-8">{{ events.length }} eventos</span>
            </div>
            <el-empty v-if="!events.length" :image-size="72" description="Ningun modelo activo ha emitido una alarma productiva." />
            <el-table v-else :data="events" stripe table-layout="fixed">
                <el-table-column prop="occurred_at" label="Fecha" width="180"><template #default="scope">{{ formatDate(scope.row.occurred_at) }}</template></el-table-column>
                <el-table-column prop="model.code" label="Modelo" min-width="190" />
                <el-table-column prop="title" label="Alarma" min-width="240" />
                <el-table-column prop="suggested_severity" label="Severidad" width="120" />
                <el-table-column prop="event_type" label="Evento" width="120" />
            </el-table>
            <p v-if="observations.length" class="legacy-note mt-4 mb-0">{{ observations.length }} observacion(es) tecnicas heredadas se conservaron como evidencia y no se cuentan como alarmas productivas.</p>
        </section>
    </App>
</template>

<style scoped>
.page-heading,
.page-actions,
.summary-band,
.model-card,
.model-card__topline,
.detail-heading,
.detail-heading__identity,
.evidence-band,
.model-context,
.light-readiness,
.scenario-controls,
.projection-panel__heading,
.twin-heading,
.alarm-preview,
.section-heading {
    display: flex;
    align-items: center;
}

.page-heading,
.detail-heading,
.projection-panel__heading,
.twin-heading,
.section-heading {
    justify-content: space-between;
    gap: 20px;
}

.page-heading { align-items: flex-end; }
.page-actions { gap: 8px; flex-wrap: wrap; }

.filter-band,
.initial-loading,
.summary-band,
.evidence-band,
.light-workbench,
.projection-panel,
.events-section {
    border: 1px solid #e1e7ef;
    background: #ffffff;
}

.filter-band { padding: 18px 20px; }
.filter-band :deep(.el-select) { width: 100%; }

.initial-loading { padding: 22px; }
.initial-loading__heading { display: flex; align-items: flex-start; gap: 11px; margin-bottom: 20px; color: #0b7cff; }
.initial-loading__heading strong,
.initial-loading__heading span { display: block; }
.initial-loading__heading strong { color: #172033; font-size: 14px; }
.initial-loading__heading span { color: #64748b; font-size: 12px; margin-top: 3px; }

.summary-band,
.evidence-band,
.light-readiness {
    align-items: stretch;
    overflow-x: auto;
}

.summary-band > div,
.evidence-band > div,
.light-readiness > div {
    min-width: 170px;
    flex: 1;
    padding: 15px 18px;
    border-right: 1px solid #edf1f5;
}

.summary-band > div:last-child,
.evidence-band > div:last-child,
.light-readiness > div:last-child { border-right: 0; }

.summary-band span,
.summary-band small,
.evidence-band span,
.evidence-band small,
.light-readiness span,
.light-readiness small {
    display: block;
    color: #64748b;
    font-size: 11px;
    line-height: 1.45;
}

.summary-band strong,
.evidence-band strong,
.light-readiness strong {
    display: block;
    color: #172033;
    font-size: 22px;
    line-height: 1.25;
    margin: 4px 0;
}

.summary-band .summary-band__source { font-size: 14px; max-width: 210px; }

.model-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
}

.model-card {
    align-items: flex-start;
    gap: 12px;
    min-height: 210px;
    padding: 17px;
    border: 1px solid #dfe6ee;
    border-radius: 6px;
    background: #ffffff;
    color: inherit;
    text-align: left;
    transition: border-color .16s ease, box-shadow .16s ease;
}

.model-card:hover,
.model-card--active {
    border-color: #409eff;
    box-shadow: 0 7px 20px rgba(30, 74, 120, .09);
}

.model-card__icon,
.detail-heading__icon {
    flex: 0 0 auto;
    display: grid;
    place-items: center;
    width: 38px;
    height: 38px;
    border: 1px solid #bddcff;
    border-radius: 6px;
    background: #eef7ff;
    color: #0b7cff;
    font-size: 20px;
}

.model-card__content { display: flex; flex: 1; min-width: 0; flex-direction: column; height: 100%; }
.model-card__topline { justify-content: space-between; gap: 6px; margin-bottom: 12px; }
.model-card__content > strong { color: #172033; font-size: 14px; line-height: 1.35; }
.model-card__content > span:not(.model-card__topline) { color: #64748b; font-size: 12px; line-height: 1.5; margin-top: 8px; }
.model-card__content > small { color: #475569; font-size: 11px; font-weight: 700; margin-top: auto; padding-top: 12px; }

.model-state,
.emission-state,
.manual-badge {
    display: inline-flex;
    align-items: center;
    min-height: 22px;
    padding: 3px 7px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 700;
    line-height: 1.2;
}

.state-active { background: #e8f7ee; color: #13783d; }
.state-review { background: #fff6df; color: #966000; }
.state-blocked { background: #f1f3f6; color: #526174; }
.emission-state { background: #f1f3f6; color: #64748b; }
.emission-state--enabled { background: #e8f7ee; color: #13783d; }
.manual-badge { background: #fff3d8; color: #8b5a00; }

.detail-heading__identity { align-items: flex-start; gap: 13px; }

.model-context {
    align-items: stretch;
    gap: 0;
    border-top: 1px solid #e1e7ef;
    border-bottom: 1px solid #e1e7ef;
}

.model-context > div { width: 50%; padding: 20px 22px; }
.model-context > div + div { border-left: 1px solid #e1e7ef; }
.input-list { display: flex; flex-wrap: wrap; gap: 7px; }
.input-list span { padding: 5px 8px; border: 1px solid #dce5ef; border-radius: 4px; color: #475569; background: #f8fafc; font-size: 11px; }
.pending-list { padding-left: 18px; color: #5b6878; font-size: 12px; line-height: 1.7; }

.light-workbench,
.projection-panel,
.events-section { padding: 21px; }
.scenario-controls { align-items: flex-end; display: grid; grid-template-columns: repeat(4, minmax(135px, 1fr)) auto; gap: 12px; }
.scenario-controls label { display: block; color: #475569; font-size: 11px; font-weight: 700; margin-bottom: 6px; }
.scenario-controls :deep(.el-input-number),
.scenario-controls :deep(.el-select) { width: 100%; }
.scenario-disclaimer { color: #64748b; font-size: 11px; margin-top: 11px; }
.projection-panel__heading { margin-bottom: 12px; }

.alarm-preview {
    align-items: flex-start;
    gap: 11px;
    padding: 14px 16px;
    border-left: 3px solid #f59e0b;
    background: #fffaf0;
    color: #8a5a05;
}

.alarm-preview strong,
.alarm-preview span { display: block; }
.alarm-preview span { color: #6b7280; font-size: 12px; margin-top: 3px; }
.legacy-note { color: #64748b; font-size: 11px; }

@media (max-width: 1200px) {
    .model-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .scenario-controls { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}

@media (max-width: 768px) {
    .page-heading,
    .detail-heading,
    .projection-panel__heading,
    .twin-heading,
    .section-heading { align-items: flex-start; flex-direction: column; }
    .page-actions { width: 100%; }
    .model-grid { grid-template-columns: 1fr; }
    .model-context { flex-direction: column; }
    .model-context > div { width: 100%; }
    .model-context > div + div { border-left: 0; border-top: 1px solid #e1e7ef; }
    .scenario-controls { grid-template-columns: 1fr; }
    .scenario-controls :deep(.el-button) { width: 100%; }
}
</style>
