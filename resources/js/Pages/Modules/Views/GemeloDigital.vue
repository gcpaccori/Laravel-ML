<script setup>
import { computed, onMounted, ref } from "vue";
import { ArrowLeft, RefreshRight, SetUp } from "@element-plus/icons-vue";
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
    svm: "SVM_OD_FORECAST_1H",
    oxygen: "OXYGEN_STATUS_MODEL",
    growth: "TILAPIA_GROWTH_TEMPERATURE",
    water: "WATER_QUALITY_INDEX_ICA",
};

const modelOptions = [
    { value: MODEL_CODES.water, label: "Calidad de agua" },
    { value: MODEL_CODES.oxygen, label: "Estado de oxigeno" },
    { value: MODEL_CODES.growth, label: "Crecimiento de tilapia" },
    { value: MODEL_CODES.svm, label: "Proyeccion SVM de OD" },
];

const loading = ref(false);
const calculating = ref(false);
const errorMessage = ref("");
const response = ref(null);
const scenarioResult = ref(null);
const focusModel = ref(new URLSearchParams(window.location.search).get("modelo") || MODEL_CODES.growth);
const pondId = ref(new URLSearchParams(window.location.search).get("piscina_id") || "T");

const scenario = ref({
    temperature_c: null,
    ph: null,
    dissolved_oxygen_mg_l: null,
    nitrate_ion: null,
    projection_days: 30,
    active_models: [MODEL_CODES.water, MODEL_CODES.oxygen, MODEL_CODES.growth],
});

const dashboardModel = (code) => response.value?.models?.find((model) => model.code === code);
const scenarioModel = (code) => scenarioResult.value?.models?.find((model) => model.code === code);
const latest = computed(() => response.value?.latest_measurement ?? {});
const biometrics = computed(() => response.value?.biometrics ?? {});
const qualityIndex = computed(() => Number(scenarioModel(MODEL_CODES.water)?.value ?? dashboardModel(MODEL_CODES.water)?.current_value ?? 0));
const saturation = computed(() => Number(scenarioModel(MODEL_CODES.oxygen)?.value ?? dashboardModel(MODEL_CODES.oxygen)?.current_value ?? 0));
const growth = computed(() => scenarioModel(MODEL_CODES.growth)?.projection ?? response.value?.tilapia_growth?.length_projection ?? null);
const temperature = computed(() => Number(scenario.value.temperature_c ?? latest.value.water_temperature_c ?? 0));
const averageWeight = computed(() => Number(biometrics.value.peso_promedio_g ?? 0));
const projectedWeight = computed(() => Number(growth.value?.projected_weight_g ?? averageWeight.value));
const estimatedFishCount = computed(() => {
    const biomassKg = Number(biometrics.value.biomasa_final_kg ?? 0);
    return biomassKg > 0 && averageWeight.value > 0 ? Math.round((biomassKg * 1000) / averageWeight.value) : 0;
});
const visibleFishCount = computed(() => Math.min(56, Math.max(12, Math.round((estimatedFishCount.value || 500) / 42))));
const waterState = computed(() => {
    if (qualityIndex.value >= 70) return "Agua clara";
    if (qualityIndex.value >= 50) return "Agua con alerta";
    return "Agua turbia";
});
const focusLabel = computed(() => modelOptions.find((item) => item.value === focusModel.value)?.label ?? "Piscina");

const formatValue = (value, unit = "") => {
    const number = Number(value);
    if (!Number.isFinite(number)) return "-";
    return `${number.toLocaleString("es-PE", { maximumFractionDigits: 2 })}${unit ? ` ${unit}` : ""}`;
};

const loadDashboard = async () => {
    loading.value = true;
    errorMessage.value = "";
    try {
        const { data } = await axios.get(route("monitoreo.modelosmls.proyecciones"), {
            params: { piscina_id: pondId.value, ventana: "24h", proyeccion_dias: scenario.value.projection_days },
        });
        response.value = data;
        scenario.value = {
            ...scenario.value,
            temperature_c: data.latest_measurement?.water_temperature_c ?? null,
            ph: data.latest_measurement?.ph ?? null,
            dissolved_oxygen_mg_l: data.latest_measurement?.dissolved_oxygen_mg_l ?? null,
            nitrate_ion: data.latest_measurement?.nitrate_ion ?? null,
        };
        await runScenario();
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? "No se pudieron cargar los datos reales de la piscina.";
    } finally {
        loading.value = false;
    }
};

const runScenario = async () => {
    calculating.value = true;
    errorMessage.value = "";
    try {
        const { data } = await axios.post(route("monitoreo.modelosmls.simulacion"), {
            piscina_id: pondId.value,
            ...scenario.value,
        });
        scenarioResult.value = data;
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? "No se pudo calcular este escenario.";
    } finally {
        calculating.value = false;
    }
};

const goToModels = () => window.location.assign(route("monitoreo.modelosmls.index"));

onMounted(loadDashboard);
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <section class="twin-heading mb-5">
            <div>
                <el-button text :icon="ArrowLeft" @click="goToModels">Volver a modelos</el-button>
                <div class="text-gray-500 fs-7 mt-3">Piscina {{ pondId === "T" ? "principal" : pondId }} basada en datos locales</div>
                <h2 class="fs-2 fw-bold text-dark mb-2">Gemelo digital de piscina</h2>
                <p class="text-gray-600 fs-6 mb-0">Representacion 3D animada de biometria, agua y comportamiento visual de los peces. La escena cambia con la lectura real o el escenario manual.</p>
            </div>
            <el-button :icon="RefreshRight" :loading="loading" @click="loadDashboard">Restablecer datos reales</el-button>
        </section>

        <el-alert v-if="errorMessage" class="mb-5" type="warning" :title="errorMessage" show-icon :closable="false" />

        <section class="twin-status mb-4">
            <div><span>Calidad de agua</span><strong>{{ formatValue(qualityIndex, "/100") }}</strong><small>{{ waterState }}</small></div>
            <div><span>Oxigeno</span><strong>{{ formatValue(saturation, "%") }}</strong><small>Saturacion</small></div>
            <div><span>Biometria</span><strong>{{ formatValue(averageWeight, "g") }}</strong><small>{{ formatValue(biometrics.longitud_promedio_cm, "cm") }} de longitud media</small></div>
            <div><span>Estanque</span><strong>{{ formatValue(estimatedFishCount) }}</strong><small>peces estimados por biomasa</small></div>
        </section>

        <PiscinaDigital3D
            :quality-index="qualityIndex"
            :temperature-c="temperature"
            :average-weight-g="averageWeight"
            :projected-weight-g="projectedWeight"
            :estimated-fish-count="estimatedFishCount"
            :focused-model="focusLabel"
        />

        <section class="scene-note mt-3 mb-6">
            <span>La escena muestra {{ visibleFishCount }} peces visuales de {{ formatValue(estimatedFishCount) }} estimados. El agua se enturbia al disminuir el ICA; la escala de los peces incorpora la proyeccion de peso.</span>
            <span v-if="response?.biometrics?.sampled_at">Ultima biometria: {{ response.biometrics.sampled_at }}</span>
        </section>

        <section class="simulation-tools mb-6">
            <div class="simulation-tools__header"><div><span class="text-gray-500 fs-8 fw-semibold text-uppercase">Escenario de piscina</span><h3 class="fs-5 fw-bold text-dark mb-0">Ajusta una lectura y observa la piscina</h3></div><el-select v-model="focusModel" class="focus-select"><el-option v-for="item in modelOptions" :key="item.value" :label="item.label" :value="item.value" /></el-select></div>
            <div class="row g-4 mt-1">
                <div class="col-sm-6 col-xl-3"><label>Temperatura (C)</label><el-input-number v-model="scenario.temperature_c" :min="0" :max="45" :step="0.1" controls-position="right" /></div>
                <div class="col-sm-6 col-xl-3"><label>pH</label><el-input-number v-model="scenario.ph" :min="0" :max="14" :step="0.01" controls-position="right" /></div>
                <div class="col-sm-6 col-xl-3"><label>Oxigeno disuelto (mg/L)</label><el-input-number v-model="scenario.dissolved_oxygen_mg_l" :min="0" :max="30" :step="0.1" controls-position="right" /></div>
                <div class="col-sm-6 col-xl-3"><label>Ion nitrato (mg/L)</label><el-input-number v-model="scenario.nitrate_ion" :min="0" :max="500" :step="0.1" controls-position="right" /></div>
            </div>
            <div class="simulation-tools__bottom"><div><label>Proyeccion de biometria</label><el-select v-model="scenario.projection_days" class="days-select"><el-option :value="1" label="1 dia" /><el-option :value="7" label="7 dias" /><el-option :value="30" label="30 dias" /><el-option :value="90" label="90 dias" /><el-option :value="180" label="180 dias" /></el-select></div><div><label>Modelos aplicados</label><el-checkbox-group v-model="scenario.active_models" class="model-toggles"><el-checkbox :label="MODEL_CODES.water">Calidad de agua</el-checkbox><el-checkbox :label="MODEL_CODES.oxygen">Estado de oxigeno</el-checkbox><el-checkbox :label="MODEL_CODES.growth">Crecimiento</el-checkbox></el-checkbox-group></div><el-button type="primary" :icon="SetUp" :loading="calculating" @click="runScenario">Actualizar simulacion</el-button></div>
        </section>

        <section v-if="scenarioResult" class="row g-5 mb-6">
            <div v-for="item in scenarioResult.models" :key="item.code" class="col-md-4"><div class="model-response"><span>{{ item.name }}</span><strong>{{ formatValue(item.value, item.unit) }}</strong><small>{{ item.detail }}</small><small v-if="item.projection?.projected_weight_g">Peso proyectado: {{ formatValue(item.projection.projected_weight_g, "g") }}</small></div></div>
        </section>

        <section v-if="scenarioResult" class="response-chart mb-6">
            <div><span class="text-gray-500 fs-8 fw-semibold text-uppercase">Modelos activados</span><h3 class="fs-5 fw-bold text-dark mb-1">Respuesta combinada</h3><p class="text-gray-600 fs-7">El grafico compara el nivel relativo de cada modelo aplicado al escenario. Los valores exactos se muestran arriba.</p></div>
            <ChartFisheye :options="scenarioResult.chart" height="360px" />
            <p class="text-gray-500 fs-8 mt-3 mb-0">{{ scenarioResult.notice }}</p>
        </section>
    </App>
</template>

<style scoped>
.twin-heading,
.twin-status,
.simulation-tools__header,
.simulation-tools__bottom,
.scene-note {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.twin-heading { align-items: flex-end; }

.twin-status {
    align-items: stretch;
    overflow-x: auto;
    border: 1px solid #e5e7eb;
    background: #ffffff;
}

.twin-status > div {
    min-width: 180px;
    flex: 1;
    padding: 15px 18px;
    border-right: 1px solid #eef1f5;
}

.twin-status > div:last-child { border-right: 0; }
.twin-status span,
.twin-status small { display: block; color: #64748b; font-size: 12px; }
.twin-status strong { display: block; color: #172033; font-size: 23px; line-height: 1.2; margin: 5px 0; }

.scene-note {
    align-items: flex-start;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.scene-note span { max-width: 560px; }

.simulation-tools,
.response-chart {
    border: 1px solid #e1e7ef;
    background: #ffffff;
    padding: 22px;
}

.focus-select { width: 245px; max-width: 100%; }

.simulation-tools label {
    display: block;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 6px;
}

.simulation-tools :deep(.el-input-number),
.simulation-tools :deep(.el-select) { width: 100%; }

.simulation-tools__bottom {
    align-items: flex-end;
    flex-wrap: wrap;
    margin-top: 20px;
}

.days-select { width: 180px !important; }
.model-toggles { display: flex; flex-wrap: wrap; gap: 10px; }

.model-response {
    height: 100%;
    border: 1px solid #e1e7ef;
    padding: 20px;
    background: #ffffff;
}

.model-response span,
.model-response small { display: block; color: #64748b; font-size: 12px; line-height: 1.5; }
.model-response strong { display: block; color: #172033; font-size: 27px; margin: 8px 0; }

@media (max-width: 768px) {
    .twin-heading,
    .simulation-tools__header,
    .simulation-tools__bottom,
    .scene-note { align-items: flex-start; flex-direction: column; }
    .focus-select { width: 100%; }
}
</style>
