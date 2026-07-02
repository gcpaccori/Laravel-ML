<script setup>
import { onMounted, onBeforeUnmount, ref, computed } from "vue";
import ChartFisheye from "@/Components/ChartFisheye.vue";
import { useDate } from "@/Composables/useDate";

const { getToday, getMonth, getYear, getDateTime } = useDate();

const props = defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false,
    },
    columns: {
        type: Object,
        required: false,
    },
});

const tableInstance = ref(null);
const ajaxUrl = route("datatable.historialaguas");
const piscigranjas = ref(null);
const piscinasList = ref([]);
const form = ref({
    piscigranja_id: "T",
    piscina_id: "T",
    tipo_tiempo: "D",
    fecha: getToday(), // YYYY-MM-DD
    mes: getMonth(), // YYYY-MM
    anio: getYear(), // YYYY
});

const labels = ref([]);
const tooltips = ref([]);
const series = ref([]);

const tipoTiempo = [
    { id: "D", name: "Fecha" },
    { id: "M", name: "Mes" },
    { id: "Y", name: "Año" },
];

const pad2 = (n) => String(n).padStart(2, "0");

const formatDMY = (yyyyMmDd) => {
    if (!yyyyMmDd) return "";
    const [y, m, d] = yyyyMmDd.split("-").map(Number);
    return `${pad2(d)}/${pad2(m)}/${y}`;
};

const formatMY = (yyyyMm) => {
    if (!yyyyMm) return "";
    const [y, m] = yyyyMm.split("-").map(Number);
    return `${pad2(m)}/${y}`;
};

const subTituloFiltros = computed(() => {
    const parts = [];

    // Piscigranja
    if (form.value.piscigranja_id !== "T") {
        const pg = piscigranjas.value?.find(
            (p) => String(p.id) == String(form.value.piscigranja_id)
        );
        if (pg) parts.push(pg.nombre);
    }

    // Piscina
    if (form.value.piscina_id !== "T") {
        const pc = piscinasList.value?.find(
            (p) => String(p.id) == String(form.value.piscina_id)
        );
        if (pc) parts.push(pc.nombre);
    }

    // Fecha / Mes / Año (sin usar new Date('YYYY-MM-DD'))
    if (form.value.tipo_tiempo === "D" && form.value.fecha) {
        parts.push(formatDMY(form.value.fecha)); // ej: 30/08/2025
    } else if (form.value.tipo_tiempo === "M" && form.value.mes) {
        parts.push(formatMY(form.value.mes)); // ej: 08/2025
        // parts.push(formatMYText(form.value.mes))       // ej: agosto de 2025 (si prefieres)
    } else if (form.value.tipo_tiempo === "Y" && form.value.anio) {
        parts.push(String(form.value.anio)); // ej: 2025
    }

    return parts.length ? parts.join(" - ") : "Todos";
});

const tituloCard = computed(() => {
    let title = "-";
    if ( form.value.piscigranja_id === "T" || form.value.piscina_id === "T" || form.value.tipo_tiempo !== "D" ) {
        title = ' - Promedios';
    }else{
        title = ' - Registros';
    }

    return title;
})

// Inicializar Tabla
const handleTableReady = (dt) => {
    tableInstance.value = dt;
};

const reloadTable = () => {
    tableInstance.value.ajax.reload(null, true); // Recargar y regresa a la primera pagina;
};

const piscigranjasOptions = async () => {
    const { data } = await axios.get(route("piscigranjas.options"));
    piscigranjas.value = data.data;
};

const changePiscigranjas = () => {
    form.value.piscina_id = "T";
    loadParametros();
    reloadTable();
};

const changeTipoTiempo = async () => {
    form.value.fecha = getToday();
    form.value.mes = getMonth();
    form.value.anio = getYear();

    await loadParametros();
    reloadTable();
};

const changeTiempo = async () => {
    await loadParametros();
    reloadTable();
};

const chart = ref(null);
const loadParametros = async () => {
    const { data } = await axios.get(route("chart.historialaguas"), {
        params: form.value,
    });
    chart.value = data.chart;

    if (form.value.piscigranja_id == "T") {
        piscinasList.value = [];
    } else {
        await piscinasOptions();
    }
};

const piscinasOptions = async () => {
    const { data } = await axios.get(
        route("piscigranjas.piscinas", form.value.piscigranja_id)
    );
    piscinasList.value = data;
};

onMounted(async () => {
    await loadParametros();
    await piscigranjasOptions();

    // Para canal público
    window.Echo.channel("parametros-agua").listen(
        ".parametro.actualizado",
        (data) => {
            console.log(data.message);
            reloadTable();
            loadParametros();
        }
    );
});
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="row g-5 g-xl-8">
            <div class="col-xl-12">
                <div class="card bg-body hoverable card-xl-stretch mb-xl-1">
                    <div class="card-body">
                        <el-form
                            :model="form"
                            label-position="top"
                            class="w-100"
                        >
                            <div class="row">
                                <div class="col-lg-3">
                                    <el-form-item label="Piscigranjas">
                                        <el-select
                                            filterable
                                            v-model="form.piscigranja_id"
                                            @change="changePiscigranjas"
                                        >
                                            <el-option
                                                label="Todos"
                                                value="T"
                                            />
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
                                        <el-select
                                            filterable
                                            v-model="form.piscina_id"
                                            @change="changeTiempo"
                                        >
                                            <el-option
                                                label="Todos"
                                                value="T"
                                            />
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
                                    <el-form-item label="Tipo Tiempo">
                                        <el-select
                                            filterable
                                            v-model="form.tipo_tiempo"
                                            @change="changeTipoTiempo"
                                        >
                                            <el-option
                                                v-for="item in tipoTiempo"
                                                :key="item.id"
                                                :label="item.name"
                                                :value="item.id"
                                            />
                                        </el-select>
                                    </el-form-item>
                                </div>

                                <div
                                    class="col-lg-3"
                                    v-if="form.tipo_tiempo === 'D'"
                                >
                                    <el-form-item label="Fecha Medición">
                                        <el-date-picker
                                            class="w-100"
                                            type="date"
                                            v-model="form.fecha"
                                            format="DD/MM/YYYY"
                                            value-format="YYYY-MM-DD"
                                            @change="changeTiempo"
                                            :clearable="false"
                                        />
                                    </el-form-item>
                                </div>

                                <div
                                    class="col-lg-3"
                                    v-if="form.tipo_tiempo === 'M'"
                                >
                                    <el-form-item label="Mes Medición">
                                        <el-date-picker
                                            class="w-100"
                                            v-model="form.mes"
                                            type="month"
                                            format="MM/YYYY"
                                            value-format="YYYY-MM"
                                            @change="changeTiempo"
                                            :clearable="false"
                                        />
                                    </el-form-item>
                                </div>

                                <div
                                    class="col-lg-3"
                                    v-if="form.tipo_tiempo === 'Y'"
                                >
                                    <el-form-item label="Año Medición">
                                        <el-date-picker
                                            class="w-100"
                                            type="year"
                                            v-model="form.anio"
                                            format="YYYY"
                                            value-format="YYYY"
                                            @change="changeTiempo"
                                            :clearable="false"
                                        />
                                    </el-form-item>
                                </div>
                            </div>
                        </el-form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12 mb-5">
                <div class="card card-flush overflow-hidden h-xl-100">
                    <div class="card-header py-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-dark">Historial de Registros de Parámetros del Agua {{ tituloCard }}</span>
                            <span class="text-gray-400 mt-1 fw-semibold fs-6">{{
                                subTituloFiltros
                            }}</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <ChartFisheye :options="chart" />
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-end mb-3">
                <a
                    :href="route('parametrosagua.csv', form)"
                    class="btn btn-success btn-sm me-2"
                    target="_blank"
                >
                    <i class="fa fa-file-excel"></i> Exportar a CSV
                </a>
                <a
                    :href="route('parametrosagua.excel', form)"
                    class="btn btn-success btn-sm"
                    target="_blank"
                >
                    <i class="fa fa-file-excel"></i> Exportar a EXCEL
                </a>
            </div>
            <div class="col-lg-12">
                <BaseDataTable
                    :ajax-url="ajaxUrl"
                    :columns="columns"
                    :filters="form"
                    @tableReady="handleTableReady"
                ></BaseDataTable>
            </div>
        </div>
    </App>
</template>
