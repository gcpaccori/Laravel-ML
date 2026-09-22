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
const mostrarFiltros = ref(false);

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

const hayEvolucion = computed(() =>
    evolucion.value.some((s) => s.puntos.length),
);
const hayComparativa = computed(() => comparativaEtapas.value.length > 0);

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
        tooltip: {
            trigger: "axis",
            formatter: (params) => {
                if (!params || params.length === 0) return "";

                const fecha = params[0].value[0];
                let HTML = `<strong style="font-size:13px;">Fecha: ${fecha}</strong><br/>`;

                params.forEach((item) => {
                    const extra = item.data;
                    const peso = item.value[1]; // prom_peso_g

                    HTML += `
                        <div style="margin-top: 4px; font-size:13px; color:#666;">
                            ${item.marker} <b>Biometría:</b> #${extra.biometria_id}<br/>
                            <span>
                                • <b>Etapa:</b> ${extra.etapa}<br/>
                                • <b>Especie:</b> ${extra.especie}<br/>
                                • <b>Peso prom.:</b> ${peso} g
                            </span>
                        </div>
                    `;
                });

                return HTML;
            }
        },
        legend: { type: "scroll", bottom: 0 },
        grid: { left: 48, right: 24, top: 24, bottom: 56 },
        xAxis: { type: "time" },
        yAxis: {
            type: "value",
            name: "g",
            axisLabel: { formatter: "{value} g" },
        },
        series: evolucion.value.map((etapa) => ({
            name: etapa.serie_nombre, // Muestra solo: "NombreCampaña · NombreEspecie"
            type: "line",
            smooth: true,
            showSymbol: true,
            data: etapa.puntos.map((p) => ({
                value: [p.fecha, p.prom_peso_g], // [Eje X, Eje Y]
                biometria_id: p.id,
                etapa: p.etapa,
                especie: p.especie,
            })),
        })),
    };
});
const { elRef: chartPesoEvolElRef } = useEchart(opcionPesoEvol);

// --- Evolución de la supervivencia (una línea por etapa) ---
const opcionSupervivenciaEvol = computed(() => {
    if (!hayEvolucion.value) return null;

    return {
        color: evolucion.value.map((_, i) => colorEtapa(i)),
        tooltip: {
            trigger: "axis",
            formatter: (params) => {
                if (!params || params.length === 0) return "";
                const fecha = params[0].value[0];
                let HTML = `<strong style="font-size:13px;">Fecha: ${fecha}</strong><br/>`;

                params.forEach((item) => {
                    const extra = item.data;
                    const supervivencia = item.value[1]; // tasa_supervivencia_porcentaje

                    HTML += `
                        <div style="margin-top: 4px; font-size:13px; color:#666;">
                            ${item.marker} <b>Biometría:</b> #${extra.biometria_id}<br/>
                            <span style="font-size:12px; color:#666;">
                                • <b>Etapa:</b> ${extra.etapa}<br/>
                                • <b>Especie:</b> ${extra.especie}<br/>
                                • <b>Supervivencia:</b> ${supervivencia != null ? supervivencia + '%' : 'N/A'}
                            </span>
                        </div>
                    `;
                });

                return HTML;
            }
        },
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
            name: etapa.serie_nombre, // Solo muestra: "Campaña · Especie"
            type: "line",
            smooth: true,
            showSymbol: true,
            data: etapa.puntos.map((p) => ({
                value: [p.fecha, p.tasa_supervivencia], // [Eje X, Eje Y]
                biometria_id: p.id,
                etapa: p.etapa,
                especie: p.especie,
            })),
        })),
    };
});
const { elRef: chartSupervivenciaElRef } = useEchart(opcionSupervivenciaEvol);

// --- Comparativa FCA por etapa (barras verticales) ---
const opcionFca = computed(() => {
    if (!hayComparativa.value) return null;

    const datos = [...comparativaEtapas.value].sort(
        (a, b) => (a.fca ?? 0) - (b.fca ?? 0),
    );

    return {
        tooltip: {
            trigger: "axis",
            axisPointer: { type: "shadow" },
            formatter: (params) => {
                if (!params || params.length === 0) return "";
                const item = params[0];
                const extra = item.data;

                return `
                    <strong>FCA: ${extra.value}</strong><br/>
                    <hr style="margin:4px 0; border-color:#eee;"/>
                    <span style="font-size:12px; color:#666;">
                        • <b>Piscigranja:</b> ${extra.piscigranja}<br/>
                        • <b>Etapa:</b> ${extra.etapa}<br/>
                        • <b>Piscina:</b> ${extra.piscina}<br/>
                        • <b>Última fecha:</b> ${extra.ultima_fecha}
                    </span>
                `;
            }
        },
        grid: {
            left: 48,
            right: 24,
            top: 36,
            bottom: 64,
        },
        xAxis: {
            type: "category",
            data: datos.map((d) => `${d.campania} · ${d.especie}`),
            axisLabel: {
                interval: 0,
                rotate: 0,
                fontSize: 11,
            },
        },
        yAxis: {
            type: "value",
            name: "FCA",
        },
        series: [
            {
                type: "bar",
                data: datos.map((d) => ({
                    value: d.fca,
                    piscigranja: d.piscigranja,
                    campania: d.campania,
                    especie: d.especie,
                    piscina: d.piscina,
                    ultima_fecha: d.ultima_fecha_muestreo,
                    etapa: d.etapa_nombre
                })),
                barMaxWidth: 30,
                itemStyle: {
                    borderRadius: [4, 4, 0, 0],
                    color: "#E6A23C",
                },
                label: {
                    show: true,
                    position: "top",
                    fontSize: 11,
                },
            },
        ],
    };
});
const { elRef: chartFcaElRef } = useEchart(opcionFca);

// --- Comparativa Biomasa actual por etapa (barras verticales) ---
const opcionBiomasa = computed(() => {
    if (!hayComparativa.value) return null;

    const datos = [...comparativaEtapas.value].sort(
        (a, b) => (a.biomasa_actual_kg ?? 0) - (b.biomasa_actual_kg ?? 0),
    );

    return {
        tooltip: {
            trigger: "axis",
            axisPointer: { type: "shadow" },
            formatter: (params) => {
                if (!params || params.length === 0) return "";
                const item = params[0];
                const extra = item.data;

                return `
                    <strong>Biomasa: ${extra.value} kg</strong><br/>
                    <hr style="margin:4px 0; border-color:#eee;"/>
                    <span style="font-size:12px; color:#666;">
                        • <b>Piscigranja:</b> ${extra.piscigranja}<br/>
                        • <b>Etapa:</b> ${extra.etapa}<br/>
                        • <b>Piscina:</b> ${extra.piscina}<br/>
                        • <b>Última fecha:</b> ${extra.ultima_fecha}
                    </span>
                `;
            }
        },
        grid: {
            left: 48,
            right: 24,
            top: 36,
            bottom: 64,
        },
        xAxis: {
            type: "category",
            data: datos.map((d) => `${d.campania} · ${d.especie}`),
            axisLabel: {
                interval: 0,
                rotate: 0,
                fontSize: 11,
            },
        },
        yAxis: {
            type: "value",
            name: "kg",
        },
        series: [
            {
                type: "bar",
                data: datos.map((d) => ({
                    value: d.biomasa_actual_kg,
                    piscigranja: d.piscigranja,
                    campania: d.campania,
                    especie: d.especie,
                    piscina: d.piscina,
                    ultima_fecha: d.ultima_fecha_muestreo,
                    etapa: d.etapa_nombre
                })),
                barMaxWidth: 30,
                itemStyle: {
                    borderRadius: [4, 4, 0, 0],
                    color: "#409EFF",
                },
                label: {
                    show: true,
                    position: "top",
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
        <div class="mb-3">
            <a href="#" @click.prevent="mostrarFiltros = !mostrarFiltros" class="text-decoration-none">
                <i :class="mostrarFiltros ? 'el-icon-arrow-up' : 'el-icon-arrow-down'"></i>
                {{ mostrarFiltros ? '[ - Ocultar Filtros ]' : '[ + Mostrar Filtros ]' }}
            </a>
        </div>
        <FormSection v-show="mostrarFiltros" class="mb-4" @submitted="aplicarFiltros" size="small">
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
                        type="primary"
                        native-type="submit"
                        icon="Search"
                        :loading="cargando"
                        @click="aplicarFiltros"
                    >
                        Filtrar
                    </el-button>
                    <el-button
                        icon="Refresh"
                        @click="limpiarFiltros"
                        >Limpiar
                    </el-button>
                </div>
            </template>
        </FormSection>

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

        <el-row class="row g-3 seccion mb-4">
            <el-col class="col-12 col-md-6">
                <el-card shadow="never">
                    <template #header>
                        <span class="seccion-titulo"
                            >Evolución del Peso Promedio (g) - Biometrías</span
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

            <el-col class="col-12 col-md-6">
                <el-card shadow="never">
                    <template #header>
                        <span class="seccion-titulo"
                            >Evolución de la Supervivencia (%) - Biometrías</span
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

        <el-row class="row g-3 seccion mb-4">
            <el-col class="col-12 col-md-6">
                <el-card shadow="never">
                    <template #header>
                        <span class="seccion-titulo"
                            >Conversión Alimenticia (FCA) por Etapa - Biometrías</span
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

            <el-col class="col-12 col-md-6">
                <el-card shadow="never">
                    <template #header>
                        <span class="seccion-titulo"
                            >Biomasa Actual por Etapa (kg) - Biometrías</span
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

        <el-card shadow="never" class="seccion">
            <template #header>
                <span class="seccion-titulo"
                    >Resumen por Etapa (Biometrías)</span
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
