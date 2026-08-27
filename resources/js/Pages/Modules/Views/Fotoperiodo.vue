<script setup>
import { onMounted, computed, ref, nextTick } from "vue";
import * as echarts from "echarts";
import ServerTime from "@/Components/ServerTime.vue";
import KpiCard from "@/Components/KpiCard.vue";
import { useDate } from "@/Composables/useDate";

const { getToday } = useDate();

const props = defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false,
    },
});

const form = ref({
    piscigranja_id: "T",
    piscina_id: "T",
    fecha: getToday()
});

const ultima = ref({});
const lecturas = ref([]);
const bandas = ref({
    iluminancia: [],
    temperatura_ambiente: [],
    humedad_ambiente: [],
});
const piscigranjas = ref(null);
const piscinasList = ref(null);

const piscigranjasOptions = async () => {
    const { data } = await axios.get(route("piscigranjas.options"));
    piscigranjas.value = data.data;
};

const piscinasOptions = async () => {
    const { data } = await axios.get(
        route("piscigranjas.piscinas", form.value.piscigranja_id),
    );
    piscinasList.value = data;
};

const changePiscigranjas = () => {
    form.value.piscina_id = "T";
    loadParametros();
};

const loadParametros = async () => {
    const { data } = await axios.get(
        route("fotoperiodos.parametros", form.value),
    );

    ultima.value = data.ultima;
    lecturas.value = data.lecturas;
    bandas.value = data.bandas;

    if (form.value.piscigranja_id === "T") {
        piscinasList.value = [];
    } else {
        await piscinasOptions();
    }

    await nextTick();
    renderDona();
    renderLinea();

    window.Echo.channel('parametros-agua')
    .listen('.parametro.actualizado', (data) => {
        console.log(data.message);
        loadParametros();
    });
};

/* ---------- Obtener banda según parámetro y valor ---------- */
const obtenerBanda = (parametro, valor) => {
    const bandasParametro = bandas.value[parametro] ?? [];

    return bandasParametro.find( (banda) =>
        valor >= Number(banda.low_score) &&
        valor < Number(banda.high_score),
    );
};

const nombrePiscigranja = computed(() => ultima.value?.piscina?.piscigranja?.nombre ?? "-");
const nombrePiscina = computed(() => ultima.value?.piscina?.nombre ?? "-");

/* ---------- Valores actuales ---------- */
const iluminanciaActual = computed(() => ultima.value?.iluminancia ?? 0);
const temperaturaActual = computed(() => ultima.value?.temperatura_ambiente ?? 0);
const humedadActual     = computed(() => ultima.value?.humedad_ambiente ?? 0);
/* ---------- Iluminancia ---------- */
const estadoLuminico = computed(() => ultima.value?.estado_luminico ?? '-');
const bandaIluminancia = computed(() => obtenerBanda("iluminancia", iluminanciaActual.value));
const colorEstado      = computed(() => bandaIluminancia.value?.color ?? "#909399");
// const etiquetaEstado   = computed(() => bandaIluminancia.value?.title ?? "-");
/* ---------- Temperatura ambiente ---------- */
const bandaTemperatura    = computed(() => obtenerBanda("temperatura_ambiente", temperaturaActual.value));
const colorTemperatura    = computed(() => bandaTemperatura.value?.color ?? "#909399");
const etiquetaTemperatura = computed(() => bandaTemperatura.value?.title ?? "-",);
/* ---------- Humedad ambiente ---------- */
const bandaHumedad    = computed(() => obtenerBanda("humedad_ambiente", humedadActual.value));
const colorHumedad    = computed(() => bandaHumedad.value?.color ?? "#909399");
const etiquetaHumedad = computed(() => bandaHumedad.value?.title ?? "-");

/* ---------- Gráfico de dona: proporción L / D del día ---------- */
const donaRef = ref(null);
let donaChart = null;

function renderDona() {
    if (!donaRef.value) return;

    if (!donaChart) {
        donaChart = echarts.init(donaRef.value);

        donaChart.setOption({
            animation: false,

            tooltip: {
                trigger: "item",
                formatter: "{b}: {c}h ({d}%)",
            },

            legend: {
                bottom: 0,
            },

            series: [
                {
                    name: "Fotoperíodo",
                    type: "pie",
                    radius: ["55%", "75%"],
                    avoidLabelOverlap: false,
                    label: {
                        show: false,
                    },
                    data: [],
                },
            ],
        });
    }

    // Solo actualizamos los datos
    donaChart.setOption({
        animation: true,
        animationDurationUpdate: 600,
        animationEasingUpdate: "cubicOut",

        series: [
            {
                data: [
                    {
                        value:
                            ultima.value?.fotoperiodo?.horas_luz ?? 0,
                        name: "Luz (L)",
                        itemStyle: {
                            color: "#f2c744",
                        },
                    },
                    {
                        value:
                            ultima.value?.fotoperiodo?.horas_oscuridad ?? 0,
                        name: "Oscuridad (D)",
                        itemStyle: {
                            color: "#1F3864",
                        },
                    },
                ],
            },
        ],
    });
}

/* ---------- Gráfico de línea: iluminancia durante el día ---------- */
const lineaRef = ref(null);
let lineaChart = null;

function renderLinea() {
    if (!lineaRef.value) return;

    if (!lineaChart) {
        lineaChart = echarts.init(lineaRef.value);
    }

    const horas = lecturas.value?.map((l) =>
        new Date(l?.fecha_medicion).toLocaleTimeString("es-PE", {
            hour12: false,
            hour: "2-digit",
            minute: "2-digit",
        }),
    );

    const valores = lecturas.value?.map((l) => Number(l?.iluminancia));

    lineaChart.setOption({
        tooltip: {
            trigger: "axis",
        },

        grid: {
            left: 50,
            right: 20,
            top: 20,
            bottom: 30,
        },

        xAxis: {
            type: "category",
            data: horas,
            boundaryGap: false,
        },

        yAxis: {
            type: "log",
            name: "LUX",
            min: 1,
        },

        series: [
            {
                name: "Iluminancia",
                type: "line",
                smooth: true,
                showSymbol: false,
                areaStyle: {
                    opacity: 0.15,
                },
                itemStyle: {
                    color: "#f2c744",
                },
                data: valores,
            },
        ],
    });
}

onMounted(async () => {
    await piscigranjasOptions();
    await loadParametros();
});
</script>
<template>
    <App :title="title" :toolbar="toolbar">
        <!-- FILTROS -->
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

                                <!-- PISCIGRANJAS -->
                                <div class="col-lg-4">
                                    <el-form-item label="Piscigranjas">
                                        <el-select
                                            filterable
                                            v-model="form.piscigranja_id"
                                            @change="changePiscigranjas"
                                            class="w-100"
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

                                <!-- PISCINAS -->
                                <div class="col-lg-3">
                                    <el-form-item label="Piscinas">
                                        <el-select
                                            filterable
                                            v-model="form.piscina_id"
                                            @change="loadParametros"
                                            class="w-100"
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

                                <!-- FECHA -->
                                <div class="col-lg-2">
                                    <el-form-item label="Fecha">
                                        <el-date-picker
                                            v-model="form.fecha"
                                            type="date"
                                            placeholder="Seleccionar fecha"
                                            format="DD/MM/YYYY"
                                            value-format="YYYY-MM-DD"
                                            class="w-100"
                                            :clearable="false"
                                            @change="loadParametros"
                                        />
                                    </el-form-item>
                                </div>

                                <!-- HORA DEL SERVIDOR -->
                                <div class="col-lg-3 d-flex justify-content-end">
                                    <ServerTime />
                                </div>
                            </div>
                        </el-form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Piscigranja -->
            <div class="col-lg-4">
                <KpiCard
                    label="Piscigranja"
                    :valor="nombrePiscigranja"
                    icon="OfficeBuilding"
                    color="#409EFF"
                />
            </div>

            <!-- Piscina -->
            <div class="col-lg-4">
                <KpiCard
                    label="Piscina"
                    :valor="nombrePiscina"
                    icon="Grid"
                    color="#67C23A"
                />
            </div>

            <!-- Fecha/Hora -->
            <div class="col-lg-4">
                <KpiCard
                    label="Último registro"
                    :valor="ultima?.fecha_medicion_formato ?? '-'"
                    icon="Clock"
                    color="#909399"
                />
            </div>
        </div>

        <div class="row mt-5">
            <!-- Iluminancia -->
            <div class="col-lg-3">
                <KpiCard
                    label="Iluminancia"
                    :valor="iluminanciaActual"
                    sufijo="LUX"
                    icon="Sunny"
                    :color="colorEstado"
                />
            </div>

            <!-- Estado lumínico -->
            <div class="col-lg-3">
                <KpiCard
                    label="Estado lumínico"
                    :valor="estadoLuminico"
                    icon="Lightning"
                    :color="colorEstado"
                />
            </div>

            <!-- Temperatura -->
            <div class="col-lg-3">
                <KpiCard
                    label="Temperatura ambiente"
                    :valor="temperaturaActual"
                    sufijo="°C"
                    :ayuda="etiquetaTemperatura"
                    icon="Odometer"
                    :color="colorTemperatura"
                    :decimales="1"
                />
            </div>

            <!-- Humedad -->
            <div class="col-lg-3">
                <KpiCard
                    label="Humedad ambiente"
                    :valor="humedadActual"
                    sufijo="%"
                    :ayuda="etiquetaHumedad"
                    icon="Drizzling"
                    :color="colorHumedad"
                    :decimales="1"
                />
            </div>
        </div>

        <div class="row mt-5 d-flex align-items-stretch">
            <!-- Fotoperíodo -->
            <div class="col-lg-4 d-flex">
                <el-card shadow="hover" class="w-100">
                    <template #header>
                        <span class="fw-bold text-uppercase">Fotoperíodo del día</span>
                    </template>

                    <KpiCard
                        label="Fotoperíodo"
                        :valor="ultima?.fotoperiodo?.formateado ?? '-'"
                        icon="Timer"
                        color="#9B59B6"
                    />

                    <div
                        ref="donaRef"
                        class="chart chart-dona"
                    ></div>
                </el-card>
            </div>

            <!-- Historial de iluminancia -->
            <div class="col-lg-8 d-flex">
                <el-card shadow="hover" class="w-100">
                    <template #header>
                        <span class="fw-bold text-uppercase">Iluminancia durante el día</span>
                    </template>

                    <div
                        ref="lineaRef"
                        class="chart chart-linea"
                    ></div>
                </el-card>
            </div>
        </div>
    </App>
</template>
<style scoped>
.chart {
    width: 100%;
}
.chart-dona {
    min-height: 260px;
}
.chart-linea {
    min-height: 260px;
}
</style>
