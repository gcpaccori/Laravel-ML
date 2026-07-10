<script setup>
import { computed, onMounted, ref } from "vue";
import ChartFisheye from "@/Components/ChartFisheye.vue";

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
    horizonte: "72h",
    ventana: "all",
});

const horizontes = [
    { id: "24h", name: "24 horas" },
    { id: "72h", name: "72 horas" },
    { id: "7d", name: "7 dias" },
    { id: "30d", name: "30 dias" },
];

const ventanas = [
    { id: "7d", name: "Ultimos 7 dias" },
    { id: "30d", name: "Ultimos 30 dias" },
    { id: "90d", name: "Ultimos 90 dias" },
    { id: "all", name: "Todo el historico" },
];

const latest = computed(() => response.value?.latest ?? {});
const summary = computed(() => response.value?.summary ?? {});
const models = computed(() => response.value?.models ?? []);
const warnings = computed(() => response.value?.warnings ?? []);
const traceability = computed(() => response.value?.traceability ?? {});
const lifecycle = computed(() => response.value?.lifecycle ?? {});
const lifecycleModels = computed(() => response.value?.lifecycle?.models ?? []);

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
    };
    return labels[status] ?? status ?? "N/D";
};

const statusClass = (status) => {
    if (status === "asset_activo") return "badge-light-success";
    if (status === "entrenado") return "badge-light-success";
    if (status === "calculado") return "badge-light-primary";
    if (status === "gemelo_digital") return "badge-light-primary";
    if (status === "escenario_sin_asset") return "badge-light-warning";
    if (status === "sin_datos") return "badge-light-danger";
    return "badge-light-info";
};

const shortId = (value) => {
    if (!value) return "N/D";
    const text = String(value);
    return text.length > 18 ? `${text.slice(0, 18)}...` : text;
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
                                <div class="col-lg-3">
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
                                        <el-select v-model="form.horizonte" @change="changeFiltro">
                                            <el-option
                                                v-for="item in horizontes"
                                                :key="item.id"
                                                :label="item.name"
                                                :value="item.id"
                                            />
                                        </el-select>
                                    </el-form-item>
                                </div>

                                <div class="col-lg-3">
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
                Backend usado: {{ response.backend_engine }} | Fuente: {{ traceability.source ?? "Flask" }} | Metodo:
                {{ traceability.projection_method ?? "N/D" }}
            </template>
        </el-alert>

        <div v-if="warnings.length" class="card card-flush border border-warning mb-5">
            <div class="card-body py-4">
                <div class="fw-bold text-warning mb-2">Notas de calidad y trazabilidad</div>
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
                        <span class="text-gray-500">{{ response?.filters?.horizonte_label ?? "-" }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-8">
                <div class="card card-flush h-xl-100">
                    <div class="card-header py-4">
                        <h3 class="card-title fw-bold text-dark">Trazabilidad MLOps</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-4">
                            <div class="col-md-4">
                                <div class="border border-dashed border-gray-300 rounded px-5 py-4 h-100">
                                    <div class="text-gray-500 fw-semibold">Datos / entrenamiento / artefactos</div>
                                    <div class="fw-bold text-dark mt-2">
                                        {{ traceability.uses_all_points ? "todos los puntos" : "ventana" }} /
                                        {{ lifecycle.status ?? "N/D" }} /
                                        {{ lifecycleModels.length }} modelos
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border border-dashed border-gray-300 rounded px-5 py-4 h-100">
                                    <div class="text-gray-500 fw-semibold">Filas de entrenamiento</div>
                                    <div class="fw-bold text-dark mt-2">{{ Object.values(summary.training_rows ?? {}).join(" / ") || "N/D" }}</div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border border-dashed border-gray-300 rounded px-5 py-4 h-100">
                                    <div class="text-gray-500 fw-semibold">Proyecciones</div>
                                    <div class="fw-bold text-dark mt-2">{{ summary.forecast_points ?? 0 }} puntos futuros</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-4">
                <div class="card card-flush h-xl-100">
                    <div class="card-header py-4">
                        <h3 class="card-title fw-bold text-dark">Artefactos en uso</h3>
                    </div>
                    <div class="card-body pt-0">
                        <div v-if="!lifecycleModels.length" class="text-gray-500">Sin artefactos reportados.</div>
                        <div v-for="asset in lifecycleModels.slice(0, 5)" :key="asset.model_code ?? asset.artifact_path" class="d-flex justify-content-between py-2 border-bottom border-gray-200">
                            <div>
                                <div class="fw-bold text-dark fs-7">{{ asset.model_code ?? asset.status }}</div>
                                <div class="text-gray-500 fs-8">filas: {{ asset.training_rows ?? "N/D" }}</div>
                            </div>
                            <span class="badge badge-light-primary align-self-center">{{ asset.algorithm ?? asset.status }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="response?.combined_chart" class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-12">
                <div class="card card-flush overflow-hidden">
                    <div class="card-header py-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-dark">Datos reales y proyecciones</span>
                            <span class="text-gray-500 mt-1 fw-semibold fs-6">Historico completo de la base combinado con los modelos entrenados.</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <ChartFisheye :options="response.combined_chart" />
                    </div>
                </div>
            </div>
        </div>

        <div v-loading="loading" class="row g-5 g-xl-8">
            <div class="col-xl-12" v-if="!loading && !models.length && !errorMessage">
                <div class="card card-flush">
                    <div class="card-body text-center py-15">
                        <div class="fs-3 fw-bold text-gray-700">Sin modelos disponibles para los filtros seleccionados</div>
                        <div class="text-gray-500 mt-2">Revisa el backend MLOps o cambia piscina/horizonte.</div>
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
                            <div class="col-xl-9">
                                <ChartFisheye :options="model.chart" />
                            </div>
                            <div class="col-xl-3">
                                <div class="border border-dashed border-gray-300 rounded px-5 py-4 mb-4">
                                    <div class="text-gray-500 fw-semibold">Valor actual</div>
                                    <div class="fs-2 fw-bold text-dark">{{ formatValue(model.current_value, model.unit) }}</div>
                                </div>
                                <div class="border border-dashed border-gray-300 rounded px-5 py-4 mb-4">
                                    <div class="text-gray-500 fw-semibold">Motor / fuente</div>
                                    <div class="fw-bold text-dark">{{ model.engine }}</div>
                                    <div class="text-gray-500 fs-8">{{ model.source }}</div>
                                </div>
                                <div class="border border-dashed border-gray-300 rounded px-5 py-4 mb-4">
                                    <div class="text-gray-500 fw-semibold">Asset / version</div>
                                    <div class="fw-bold text-dark">{{ shortId(model.asset_id) }}</div>
                                    <div class="text-gray-500 fs-8">{{ model.version ?? "sin version" }}</div>
                                </div>
                                <div class="border border-dashed border-gray-300 rounded px-5 py-4 mb-4">
                                    <div class="text-gray-500 fw-semibold">Metricas</div>
                                    <div class="fw-bold text-dark">MAE: {{ metricValue(model.metrics?.mae ?? model.mae) }}</div>
                                    <div class="text-gray-500 fs-8">R2: {{ metricValue(model.metrics?.r2) }}</div>
                                </div>
                                <div class="text-gray-600 fs-7 mb-4">
                                    {{ model.traceability?.quality_note ?? model.traceability?.explanation ?? "Trazabilidad disponible en backend." }}
                                </div>
                                <div class="table-responsive">
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
                                                <td class="text-end fw-bold">{{ formatValue(item.value, model.unit) }}</td>
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
