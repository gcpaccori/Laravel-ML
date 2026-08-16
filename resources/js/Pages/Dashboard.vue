<script setup>
import { computed, ref, onMounted, watch } from "vue";
import KpiCard from "@/Components/KpiCard.vue";
import { useEchart } from "@/Composables/useEchart";
import FormSection from "@/Components/FormSection.vue";

const props = defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false,
    }
});

// ==================== ESTADO DE FILTROS ====================

const optionsPiscigranjas = ref([]);
const optionsCampanias = ref([]);
const optionsEspecies = ref([]);
const optionsEtapas = ref([]);
const cargando = ref(false);
const rangoFechas = ref(null);
const kpis = ref([]);
const evolucion = ref([]);
const comparativaEtapas = ref([]);

const filtrosForm = ref({
    piscigranja_id: null,
    campania_id: null,
    campania_especie_id: null,
    campania_etapa_id: null,
});


// SELECTS FILTROS
const piscigranjasOptions = async () => {
    const { data } = await axios.get(route("piscigranjas.options"));
    optionsPiscigranjas.value = data.data;
};

const campaniasOptions = async () => {
    filtrosForm.value.campania_id = null;
    filtrosForm.value.campania_especie_id = null;
    filtrosForm.value.campania_etapa_id = null;
    optionsCampanias.value = [];
    optionsEspecies.value = [];
    optionsEtapas.value = [];

    if (!filtrosForm.value.piscigranja_id) return;

    const { data } = await axios.get(route("campania.active.show", filtrosForm.value.piscigranja_id));
    optionsCampanias.value = data;
};

const especiesOptions = async () => {
    filtrosForm.value.campania_especie_id = null;
    filtrosForm.value.campania_etapa_id = null;
    optionsEspecies.value = [];
    optionsEtapas.value = [];

    if (!filtrosForm.value.campania_id) return;

    const { data } = await axios.get(route("especie.active.show", filtrosForm.value.campania_id));
    optionsEspecies.value = data;
};

const etapasOptions = async () => {
    filtrosForm.value.campania_etapa_id = null;
    optionsEtapas.value = [];

    if (!filtrosForm.value.campania_especie_id) return;

    const { data } = await axios.get(route("etapa.active.show", filtrosForm.value.campania_especie_id));
    optionsEtapas.value = data;
};

// ==================== APLICAR / LIMPIAR FILTROS ====================
const aplicarFiltros = async() => {
    cargando.value = true;

    await axios.get(route("dashboard.get.data"), {
        params: {
            piscigranja_id: filtrosForm.value.piscigranja_id,
            campania_id: filtrosForm.value.campania_id,
            campania_etapa_id: filtrosForm.value.campania_etapa_id,
            campania_especie_id: filtrosForm.value.campania_especie_id,
            fecha_inicio: rangoFechas.value?.[0] ?? null,
            fecha_fin: rangoFechas.value?.[1] ?? null,
        },
    })
    .then((response) => {
        kpis.value = response.data.kpis;
        evolucion.value = response.data.evolucion;
        comparativaEtapas.value = response.data.comparativaEtapas;
    })
    .catch((error) => {
        console.error(error);
    })
    .finally(() => {
        cargando.value = false;
    });
}

const limpiarFiltros = async() => {
    filtrosForm.value.piscigranja_id = null;
    filtrosForm.value.campania_id = null;
    filtrosForm.value.campania_especie_id = null;
    filtrosForm.value.campania_etapa_id = null;
    rangoFechas.value = null;
    await aplicarFiltros();
}

// ==================== UTILIDADES DE FORMATO ====================
function formatearNumero(valor) {
    if (valor === null || valor === undefined) return "-";
    return Number(valor).toLocaleString("es-PE", { maximumFractionDigits: 2 });
}

function colorSupervivenciaFila(v) {
    if (v === null || v === undefined) return "info";
    if (v >= 85) return "success";
    if (v >= 60) return "warning";
    return "danger";
}

const colorSupervivenciaKpi = computed(() => {
    const v = kpis.value.supervivencia_promedio;
    if (v === null || v === undefined) return "#909399";
    if (v >= 85) return "#67C23A";
    if (v >= 60) return "#E6A23C";
    return "#F56C6C";
});

// FCA más bajo = más eficiente. Umbrales orientativos para acuicultura;
// ajústalos según los rangos técnicos reales de tus informes.
const colorFca = computed(() => {
    const v = kpis.value.fca_promedio;
    if (v === null || v === undefined) return "#909399";
    if (v <= 1.5) return "#67C23A";
    if (v <= 2.2) return "#E6A23C";
    return "#F56C6C";
});

// ==================== RESUMEN (fila de totales de la tabla) ====================
function resumenTabla(param) {
    const { columns, data } = param;
    const sumar = (prop) =>
        data.reduce((acc, row) => acc + (Number(row[prop]) || 0), 0);

    return columns.map((col, index) => {
        if (index === 0) return "Totales";
        if (col.property === "peces_actuales")
            return formatearNumero(sumar("peces_actuales"));
        if (col.property === "biomasa_actual_kg")
            return formatearNumero(sumar("biomasa_actual_kg"));
        if (col.property === "alimento_consumido_kg")
            return formatearNumero(sumar("alimento_consumido_kg"));
        return "";
    });
}

// ==================== GRÁFICOS (ECharts vía composable useEchart) ====================
const hayEvolucion = computed(() =>
    evolucion.value.some((s) => s.puntos.length),
);
const hayComparativa = computed(() => comparativaEtapas.value.length > 0);

// Paleta consistente para identificar cada etapa entre los distintos gráficos
const PALETA = [
    "#409EFF",
    "#67C23A",
    "#E6A23C",
    "#F56C6C",
    "#909399",
    "#9B59B6",
    "#1ABC9C",
    "#E67E22",
];
function colorEtapa(index) {
    return PALETA[index % PALETA.length];
}

// --- Evolución del peso promedio (una línea por etapa) ---
const opcionPesoEvol = computed(() => {
    if (!hayEvolucion.value) return null;

    return {
        color: evolucion.value.map((_, i) => colorEtapa(i)),
        tooltip: { trigger: "axis" },
        legend: { type: "scroll", bottom: 0 },
        grid: { left: 48, right: 24, top: 24, bottom: 56 },
        xAxis: { type: "time" },
        yAxis: {
            type: "value",
            name: "g",
            axisLabel: { formatter: "{value} g" },
        },
        series: evolucion.value.map((etapa) => ({
            name: etapa.etapa_nombre,
            type: "line",
            smooth: true,
            showSymbol: true,
            data: etapa.puntos.map((p) => [p.fecha, p.prom_peso_g]),
        })),
    };
});

const { elRef: chartPesoEvolElRef } = useEchart(opcionPesoEvol);

// --- Evolución de la supervivencia (una línea por etapa) ---
const opcionSupervivenciaEvol = computed(() => {
    if (!hayEvolucion.value) return null;

    return {
        color: evolucion.value.map((_, i) => colorEtapa(i)),
        tooltip: { trigger: "axis", valueFormatter: (v) => `${v}%` },
        legend: { type: "scroll", bottom: 0 },
        grid: { left: 48, right: 24, top: 24, bottom: 56 },
        xAxis: { type: "time" },
        yAxis: {
            type: "value",
            name: "%",
            min: 0,
            max: 100,
            axisLabel: { formatter: "{value}%" },
        },
        series: evolucion.value.map((etapa) => ({
            name: etapa.etapa_nombre,
            type: "line",
            smooth: true,
            showSymbol: true,
            data: etapa.puntos.map((p) => [
                p.fecha,
                p.tasa_supervivencia_porcentaje,
            ]),
        })),
    };
});
const { elRef: chartSupervivenciaElRef } = useEchart(opcionSupervivenciaEvol);

// --- Comparativa FCA por etapa (barras) ---
const opcionFca = computed(() => {
    if (!hayComparativa.value) return null;

    const datos = [...comparativaEtapas.value].sort(
        (a, b) => (a.fca ?? 0) - (b.fca ?? 0),
    );

    return {
        tooltip: {
            trigger: "axis",
            axisPointer: { type: "shadow" },
        },
        grid: {
            left: 120,
            right: 24,
            top: 24,
            bottom: 24,
        },
        xAxis: {
            type: "value",
            name: "FCA",
        },
        yAxis: {
            type: "category",
            data: datos.map((d) => d.etapa_nombre),
            axisLabel: {
                width: 100,
                overflow: "break",
                fontSize: 12,
            },
        },
        series: [
            {
                type: "bar",
                data: datos.map((d) => d.fca),
                barMaxWidth: 30,
                itemStyle: {
                    borderRadius: [0, 4, 4, 0],
                    color: "#E6A23C",
                },
                label: {
                    show: true,
                    position: "right",
                    fontSize: 11,
                },
            },
        ],
    };
});
const { elRef: chartFcaElRef } = useEchart(opcionFca);

// --- Comparativa Biomasa actual por etapa (barras) ---
const opcionBiomasa = computed(() => {
    if (!hayComparativa.value) return null;

    const datos = [...comparativaEtapas.value].sort(
        (a, b) => (a.biomasa_actual_kg ?? 0) - (b.biomasa_actual_kg ?? 0),
    );

    return {
        tooltip: {
            trigger: "axis",
            axisPointer: { type: "shadow" },
            valueFormatter: (v) => `${v} kg`,
        },
        grid: {
            left: 120,
            right: 24,
            top: 24,
            bottom: 24,
        },
        xAxis: {
            type: "value",
            name: "kg",
        },
        yAxis: {
            type: "category",
            data: datos.map((d) => d.etapa_nombre),
            axisLabel: {
                width: 100,
                overflow: "break",
                fontSize: 12,
            },
        },
        series: [
            {
                type: "bar",
                data: datos.map((d) => d.biomasa_actual_kg),
                barMaxWidth: 30,
                itemStyle: {
                    borderRadius: [0, 4, 4, 0],
                    color: "#409EFF",
                },
                label: {
                    show: true,
                    position: "right",
                    fontSize: 11,
                    formatter: ({ value }) => `${value} kg`,
                },
            },
        ],
    };
});

const { elRef: chartBiomasaElRef } = useEchart(opcionBiomasa);

watch(() => filtrosForm.value.piscigranja_id, campaniasOptions);
watch(() => filtrosForm.value.campania_id, especiesOptions);
watch(() => filtrosForm.value.campania_especie_id, etapasOptions);

onMounted( async() => {
    await piscigranjasOptions();
    await aplicarFiltros();
});
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <!-- ==================== FILTROS ==================== -->
        <FormSection class="mb-4" @submitted="aplicarFiltros">
            <template #form>
                <div class="row">
                    <div class="col-lg-4">
                        <el-form-item label="Piscigranja">
                            <el-select
                                v-model="filtrosForm.piscigranja_id"
                                placeholder="Seleccione una piscigranja"
                                filterable
                            >
                                <el-option
                                    v-for="item in optionsPiscigranjas"
                                    :key="item.id"
                                    :label="item.nombre"
                                    :value="item.id"
                                />
                            </el-select>
                        </el-form-item>
                    </div>

                    <div class="col-lg-4">
                        <el-form-item label="Campaña">
                            <el-select
                                v-model="filtrosForm.campania_id"
                                :disabled="!filtrosForm.piscigranja_id"
                                placeholder="Seleccione una campaña"
                            >
                                <el-option
                                    v-for="item in optionsCampanias"
                                    :key="item.id"
                                    :label="item.nombre"
                                    :value="item.id"
                                />
                            </el-select>
                        </el-form-item>
                    </div>

                    <div class="col-lg-4">
                        <el-form-item label="Especie">
                            <el-select
                                v-model="filtrosForm.campania_especie_id"
                                :disabled="!filtrosForm.campania_id"
                                placeholder="Seleccione una especie"
                            >
                                <el-option
                                    v-for="item in optionsEspecies"
                                    :key="item.id"
                                    :label="item.especie.nombre"
                                    :value="item.id"
                                />
                            </el-select>
                        </el-form-item>
                    </div>

                    <div class="col-lg-4">
                        <el-form-item label="Etapa">
                            <el-select
                                v-model="filtrosForm.campania_etapa_id"
                                :disabled="!filtrosForm.campania_especie_id"
                                placeholder="Seleccione una etapa"
                            >
                                <el-option
                                    v-for="item in optionsEtapas"
                                    :key="item.id"
                                    :label="item.etapa.nombre"
                                    :value="item.id"
                                />
                            </el-select>
                        </el-form-item>
                    </div>

                    <div class="col-lg-4">
                        <el-form-item label="Rango de fechas">
                            <el-date-picker
                                v-model="rangoFechas"
                                type="daterange"
                                range-separator="a"
                                start-placeholder="Inicio"
                                end-placeholder="Fin"
                                format="DD/MM/YYYY"
                                value-format="YYYY-MM-DD"
                            />
                        </el-form-item>
                    </div>
                </div>
            </template>
            <template #actions>
                <div class="d-flex justify-content-center">
                    <el-button
                        size="small"
                        type="primary"
                        native-type="submit"
                        icon="Search"
                        :loading="cargando"
                        @click="aplicarFiltros"
                    >
                        Filtrar
                    </el-button>
                    <el-button
                        size="small"
                        icon="Refresh"
                        @click="limpiarFiltros"
                        >Limpiar
                    </el-button>
                </div>
            </template>
        </FormSection>

        <!-- ==================== KPIs ==================== -->
        <div class="row g-3 mb-4">
            <div class="col-6 col-sm-4 col-md-3 col-lg-3">
                <KpiCard
                    label="Etapas activas"
                    :valor="kpis.total_etapas_activas"
                    icon="OfficeBuilding"
                    color="#409EFF"
                />
            </div>

            <div class="col-6 col-sm-4 col-md-3 col-lg-3">
                <KpiCard
                    label="Peces actuales"
                    :valor="kpis.total_peces_actuales"
                    icon="Ship"
                    color="#67C23A"
                />
            </div>

            <div class="col-6 col-sm-4 col-md-3 col-lg-3">
                <KpiCard
                    label="Biomasa actual"
                    :valor="kpis.biomasa_actual_kg"
                    sufijo=" kg"
                    :decimales="1"
                    icon="ScaleToOriginal"
                    color="#E6A23C"
                />
            </div>

            <div class="col-6 col-sm-4 col-md-3 col-lg-3">
                <KpiCard
                    label="Alimento consumido"
                    :valor="kpis.alimento_consumido_total_kg"
                    sufijo=" kg"
                    :decimales="1"
                    icon="Bowl"
                    color="#909399"
                />
            </div>

            <div class="col-6 col-sm-4 col-md-3 col-lg-3">
                <KpiCard
                    label="FCA promedio"
                    :valor="kpis.fca_promedio"
                    :decimales="2"
                    icon="TrendCharts"
                    :color="colorFca"
                />
            </div>

            <div class="col-6 col-sm-4 col-md-3 col-lg-3">
                <KpiCard
                    label="Supervivencia"
                    :valor="kpis.supervivencia_promedio"
                    sufijo="%"
                    :decimales="1"
                    icon="Sunny"
                    :color="colorSupervivenciaKpi"
                />
            </div>

            <div class="col-6 col-sm-4 col-md-3 col-lg-3">
                <KpiCard
                    label="Crecimiento diario"
                    :valor="kpis.tasa_crecimiento_promedio"
                    sufijo=" g/día"
                    :decimales="2"
                    icon="Sunrise"
                    color="#67C23A"
                />
            </div>
        </div>

        <!-- ==================== EVOLUCIÓN EN EL TIEMPO ==================== -->
        <el-row :gutter="16" class="seccion mb-4">
            <el-col :span="12">
                <el-card shadow="never">
                    <template #header>
                        <span class="seccion-titulo"
                            >Evolución del Peso Promedio (g)</span
                        >
                    </template>
                    <div
                        v-if="hayEvolucion"
                        ref="chartPesoEvolElRef"
                        class="chart"
                    ></div>
                    <el-empty
                        v-else
                        description="Sin biometrías en el rango seleccionado"
                    />
                </el-card>
            </el-col>

            <el-col :span="12">
                <el-card shadow="never">
                    <template #header>
                        <span class="seccion-titulo"
                            >Evolución de la Supervivencia (%)</span
                        >
                    </template>
                    <div
                        v-if="hayEvolucion"
                        ref="chartSupervivenciaElRef"
                        class="chart"
                    ></div>
                    <el-empty
                        v-else
                        description="Sin biometrías en el rango seleccionado"
                    />
                </el-card>
            </el-col>
        </el-row>

        <!-- ==================== COMPARATIVAS ENTRE ETAPAS ==================== -->
        <el-row :gutter="16" class="seccion mb-4">
            <el-col :span="12">
                <el-card shadow="never">
                    <template #header>
                        <span class="seccion-titulo"
                            >Comparativa: Conversión Alimenticia (FCA) por
                            Etapa</span
                        >
                    </template>
                    <div
                        v-if="hayComparativa"
                        ref="chartFcaElRef"
                        class="chart"
                    ></div>
                    <el-empty
                        v-else
                        description="Sin datos para comparar"
                    />
                </el-card>
            </el-col>

            <el-col :span="12">
                <el-card shadow="never">
                    <template #header>
                        <span class="seccion-titulo"
                            >Comparativa: Biomasa Actual por Etapa
                            (kg)</span
                        >
                    </template>
                    <div
                        v-if="hayComparativa"
                        ref="chartBiomasaElRef"
                        class="chart"
                    ></div>
                    <el-empty
                        v-else
                        description="Sin datos para comparar"
                    />
                </el-card>
            </el-col>
        </el-row>

        <!-- ==================== TABLA COMPARATIVA ==================== -->
        <el-card shadow="never" class="seccion">
            <template #header>
                <span class="seccion-titulo"
                    >Resumen por Etapa ({{
                        comparativaEtapas.length
                    }})</span
                >
            </template>

            <el-table
                :data="comparativaEtapas"
                border
                stripe
                size="default"
                show-summary
                :summary-method="resumenTabla"
            >
                <el-table-column
                    prop="etapa_nombre"
                    label="Etapa"
                    min-width="120"
                    sortable
                />
                <el-table-column
                    prop="piscigranja"
                    label="Piscigranja"
                    min-width="140"
                    sortable
                />
                <el-table-column
                    prop="campania"
                    label="Campaña"
                    min-width="140"
                    sortable
                />
                <el-table-column
                    prop="especie"
                    label="Especie"
                    min-width="120"
                    sortable
                />
                <el-table-column
                    prop="piscina"
                    label="Piscina"
                    min-width="110"
                />
                <el-table-column
                    label="Peces actuales"
                    width="130"
                    align="right"
                    sortable
                    prop="peces_actuales"
                >
                    <template #default="{ row }">{{
                        formatearNumero(row.peces_actuales)
                    }}</template>
                </el-table-column>
                <el-table-column
                    label="Biomasa (kg)"
                    width="120"
                    align="right"
                    sortable
                    prop="biomasa_actual_kg"
                >
                    <template #default="{ row }">{{
                        formatearNumero(row.biomasa_actual_kg)
                    }}</template>
                </el-table-column>
                <el-table-column
                    label="Alimento (kg)"
                    width="130"
                    align="right"
                    sortable
                    prop="alimento_consumido_kg"
                >
                    <template #default="{ row }">{{
                        formatearNumero(row.alimento_consumido_kg)
                    }}</template>
                </el-table-column>
                <el-table-column
                    label="FCA"
                    width="90"
                    align="right"
                    sortable
                    prop="fca"
                >
                    <template #default="{ row }">{{
                        formatearNumero(row.fca)
                    }}</template>
                </el-table-column>
                <el-table-column
                    label="Supervivencia"
                    width="130"
                    align="center"
                    sortable
                    prop="supervivencia"
                >
                    <template #default="{ row }">
                        <el-tag
                            :type="
                                colorSupervivenciaFila(row.supervivencia)
                            "
                            size="small"
                        >
                            {{ formatearNumero(row.supervivencia) }}%
                        </el-tag>
                    </template>
                </el-table-column>
                <el-table-column
                    label="Última biometría"
                    width="140"
                    sortable
                    prop="ultima_fecha_muestreo"
                >
                    <template #default="{ row }">{{
                        row.ultima_fecha_muestreo
                    }}</template>
                </el-table-column>
            </el-table>
        </el-card>
    </App>
</template>
<style scoped>
.dashboard {
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}


.seccion {
    margin-top: 0;
}

.seccion-titulo {
    font-weight: 600;
    font-size: 15px;
}

.chart {
    height: 320px;
}
</style>
