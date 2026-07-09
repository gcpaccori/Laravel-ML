<script setup>
import { computed, onMounted, ref } from "vue";
import ChartFisheye from "@/Components/ChartFisheye.vue";

const props = defineProps({
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
    ventana: "30d",
});

const horizontes = [
    { id: "24h", name: "24 horas" },
    { id: "72h", name: "72 horas" },
    { id: "7d", name: "7 días" },
    { id: "30d", name: "30 días" },
];

const ventanas = [
    { id: "7d", name: "Últimos 7 días" },
    { id: "30d", name: "Últimos 30 días" },
    { id: "90d", name: "Últimos 90 días" },
    { id: "all", name: "Todo el histórico" },
];

const latest = computed(() => response.value?.latest ?? {});
const summary = computed(() => response.value?.summary ?? {});
const models = computed(() => response.value?.models ?? []);

const formatValue = (value, unit = "") => {
    if (value === null || value === undefined || value === "") return "-";
    return `${Number(value).toLocaleString("es-PE", { maximumFractionDigits: 3 })}${unit ? ` ${unit}` : ""}`;
};

const statusClass = (status) => {
    if (status === "disponible") return "badge-light-success";
    if (status === "sin_datos") return "badge-light-warning";
    return "badge-light-primary";
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
        errorMessage.value = error?.response?.data?.message ?? "No se pudo cargar la información de modelos.";
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
                <div class="card bg-body hoverable card-xl-stretch mb-xl-1">
                    <div class="card-body">
                        <el-form :model="form" label-position="top" class="w-100">
                            <div class="row">
                                <div class="col-lg-3">
                                    <el-form-item label="Piscigranjas">
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
                                    <el-form-item label="Piscinas">
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

        <div class="row g-5 g-xl-8 mb-5">
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body">
                        <span class="fs-7 text-gray-500 fw-semibold">Muestras usadas</span>
                        <div class="fs-2hx fw-bold text-dark">{{ summary.samples ?? "-" }}</div>
                        <span class="text-gray-500">Ventana seleccionada</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body">
                        <span class="fs-7 text-gray-500 fw-semibold">Ion Nitrato actual</span>
                        <div class="fs-2hx fw-bold text-info">{{ formatValue(latest.ion_nitrato, "mg/L") }}</div>
                        <span class="text-gray-500">{{ latest.timestamp ?? "-" }}</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body">
                        <span class="fs-7 text-gray-500 fw-semibold">Oxígeno disuelto actual</span>
                        <div class="fs-2hx fw-bold text-success">{{ formatValue(latest.oxigeno_disuelto, "mg/L") }}</div>
                        <span class="text-gray-500">{{ latest.piscina ?? "Todas las piscinas" }}</span>
                    </div>
                </div>
            </div>
            <div class="col-xl-3">
                <div class="card card-flush h-xl-100">
                    <div class="card-body">
                        <span class="fs-7 text-gray-500 fw-semibold">Modelos disponibles</span>
                        <div class="fs-2hx fw-bold text-primary">{{ summary.available_models ?? 0 }}</div>
                        <span class="text-gray-500">{{ response?.filters?.horizonte_label ?? "-" }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-loading="loading" class="row g-5 g-xl-8">
            <div class="col-xl-12" v-if="!loading && !models.length && !errorMessage">
                <div class="card card-flush">
                    <div class="card-body text-center py-15">
                        <div class="fs-3 fw-bold text-gray-700">Sin mediciones suficientes para los filtros seleccionados</div>
                        <div class="text-gray-500 mt-2">Prueba otra piscina, horizonte o ventana de datos.</div>
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
                            <span :class="['badge', statusClass(model.status)]">{{ model.status }}</span>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row g-5">
                            <div class="col-xl-9">
                                <ChartFisheye :options="model.chart" />
                            </div>
                            <div class="col-xl-3">
                                <div class="border border-dashed border-gray-300 rounded px-5 py-4 mb-5">
                                    <div class="text-gray-500 fw-semibold">Valor actual</div>
                                    <div class="fs-2 fw-bold text-dark">{{ formatValue(model.current_value, model.unit) }}</div>
                                </div>
                                <div class="border border-dashed border-gray-300 rounded px-5 py-4 mb-5">
                                    <div class="text-gray-500 fw-semibold">Error medio reciente</div>
                                    <div class="fs-4 fw-bold text-dark">{{ model.mae ? formatValue(model.mae, model.unit) : "N/D" }}</div>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-row-dashed table-row-gray-300 align-middle gs-0 gy-3">
                                        <thead>
                                            <tr class="fw-bold text-muted">
                                                <th>Tiempo</th>
                                                <th class="text-end">Proyección</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr v-for="item in (model.forecast ?? []).slice(0, 6)" :key="item.timestamp">
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
