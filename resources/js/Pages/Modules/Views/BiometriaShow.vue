<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { Document as DocumentIcon } from "@element-plus/icons-vue";
import * as echarts from "echarts";

const props = defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false,
    },
    biometria: {
        type: Object,
        required: true,
    },
    anchoLongitud: {
        type: Number,
        default: 2,
    },
    anchoPeso: {
        type: Number,
        default: 5,
    },
});

// ==================== ESTADO ====================
const exportandoExcel = ref(false);
const exportandoPdf = ref(false);

const biometria = computed(() => props.biometria);
const campaniaEspecie = computed(
    () => biometria.value?.campania_etapa?.campania_especie ?? null,
);

// ==================== UTILIDADES DE FORMATO ====================
function formatearFecha(fecha) {
    if (!fecha) return "-";
    return new Date(fecha).toLocaleDateString("es-PE", {
        year: "numeric",
        month: "long",
        day: "numeric",
    });
}

function formatearNumero(valor) {
    if (valor === null || valor === undefined || Number.isNaN(Number(valor)))
        return "-";
    return Number(valor).toLocaleString("es-PE", { maximumFractionDigits: 2 });
}

const colorSupervivencia = computed(() => {
    const v = biometria.value?.tasa_supervivencia_porcentaje;
    if (v === null || v === undefined) return "info";
    if (v >= 85) return "success";
    if (v >= 60) return "warning";
    return "danger";
});

// ==================== CÁLCULO DE DISTRIBUCIÓN PORCENTUAL ====================
function calcularDistribucion(valores, ancho) {
    const datos = (valores || []).filter(
        (v) => v !== null && v !== undefined && !Number.isNaN(Number(v)),
    );
    if (!datos.length) return [];

    const min = Math.floor(Math.min(...datos) / ancho) * ancho;
    const max = Math.ceil(Math.max(...datos) / ancho) * ancho;
    const bins = [];

    for (let inicio = min; inicio < max; inicio += ancho) {
        const fin = +(inicio + ancho).toFixed(4);
        const cantidad = datos.filter((v) => v >= inicio && v < fin).length;
        bins.push({
            rango: `≥${redondear(inicio)} a <${redondear(fin)}`,
            inicio,
            fin,
            cantidad,
            porcentaje: +((cantidad / datos.length) * 100).toFixed(2),
        });
    }

    return bins;
}

function redondear(n) {
    return Number.isInteger(n) ? n : +n.toFixed(2);
}

const detalles = computed(() => biometria.value?.detalles || []);

const distribucionLongitud = computed(() =>
    calcularDistribucion(
        detalles.value.map((d) => d.longitud_cm),
        props.anchoLongitud,
    ),
);

const distribucionPeso = computed(() =>
    calcularDistribucion(
        detalles.value.map((d) => d.peso_g),
        props.anchoPeso,
    ),
);

// ==================== OPCIONES DE ECHARTS ====================
function construirOpcionesBarras(distribucion, colorBase) {
    return {
        color: [colorBase],
        tooltip: {
            trigger: "axis",
            axisPointer: { type: "shadow" },
            formatter: (params) => {
                const p = params[0];
                const item = distribucion[p.dataIndex];
                return `${item.rango}<br/>Cantidad: ${item.cantidad}<br/>Porcentaje: ${item.porcentaje}%`;
            },
        },
        grid: { left: 48, right: 24, top: 24, bottom: 64 },
        xAxis: {
            type: "category",
            data: distribucion.map((d) => d.rango),
            axisLabel: { rotate: 40, fontSize: 11 },
        },
        yAxis: {
            type: "value",
            name: "%",
            axisLabel: { formatter: "{value}%" },
        },
        series: [
            {
                type: "bar",
                data: distribucion.map((d) => d.porcentaje),
                barMaxWidth: 48,
                label: {
                    show: true,
                    position: "top",
                    formatter: "{c}%",
                    fontSize: 11,
                },
                itemStyle: { borderRadius: [4, 4, 0, 0] },
            },
        ],
    };
}

const opcionesChartLongitud = computed(() =>
    construirOpcionesBarras(distribucionLongitud.value, "#409EFF"),
);
const opcionesChartPeso = computed(() =>
    construirOpcionesBarras(distribucionPeso.value, "#67C23A"),
);

// ==================== INSTANCIAS ECHARTS ====================
const chartLongitudEl = ref(null);
const chartPesoEl = ref(null);
let chartLongitud = null;
let chartPeso = null;
let resizeObserver = null;

function montarOActualizarChart(instanciaRef, elRef, opciones) {
    if (!elRef) return null;
    if (!instanciaRef) {
        instanciaRef = echarts.init(elRef);
    }
    instanciaRef.setOption(opciones, true);
    return instanciaRef;
}

async function refrescarCharts() {
    await nextTick();

    if (distribucionLongitud.value.length) {
        chartLongitud = montarOActualizarChart(
            chartLongitud,
            chartLongitudEl.value,
            opcionesChartLongitud.value,
        );
    } else if (chartLongitud) {
        chartLongitud.dispose();
        chartLongitud = null;
    }

    if (distribucionPeso.value.length) {
        chartPeso = montarOActualizarChart(
            chartPeso,
            chartPesoEl.value,
            opcionesChartPeso.value,
        );
    } else if (chartPeso) {
        chartPeso.dispose();
        chartPeso = null;
    }
}

function redimensionarCharts() {
    chartLongitud?.resize();
    chartPeso?.resize();
}

onMounted(() => {
    refrescarCharts();

    resizeObserver = new ResizeObserver(() => redimensionarCharts());
    if (chartLongitudEl.value)
        resizeObserver.observe(chartLongitudEl.value.parentElement);
    if (chartPesoEl.value)
        resizeObserver.observe(chartPesoEl.value.parentElement);

    window.addEventListener("resize", redimensionarCharts);
});

watch(() => props.biometria, refrescarCharts, { deep: false });

onBeforeUnmount(() => {
    window.removeEventListener("resize", redimensionarCharts);
    resizeObserver?.disconnect();
    chartLongitud?.dispose();
    chartPeso?.dispose();
});

// ==================== EXPORTACIÓN ====================
function exportarExcel() {
    exportandoExcel.value = true;
    window.location.href = route("biometrias.excel", biometria.value.id);
    setTimeout(() => (exportandoExcel.value = false), 1500);
}

function exportarPdf() {
    exportandoPdf.value = true;
    window.location.href = route("biometrias.pdf", biometria.value.id);
    setTimeout(() => (exportandoPdf.value = false), 1500);
}
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="biometria-show">
            <!-- ==================== ENCABEZADO / ACCIONES ==================== -->
            <div class="header-actions">
                <div>
                    <h2 class="titulo">
                        Biometría —
                        {{ formatearFecha(biometria?.fecha_muestreo) }}
                    </h2>
                    <p class="subtitulo">
                        {{ biometria?.nombre_piscigranja }} /
                        {{ biometria?.nombre_campania }} /
                        {{ biometria?.nombre_especie }} /
                        {{ biometria?.nombre_etapa }}
                    </p>
                </div>
                <div class="acciones">
                    <!-- <el-button
                        type="success"
                        :icon="DocumentIcon"
                        :loading="exportandoExcel"
                        @click="exportarExcel"
                        size="small"
                    >
                        Exportar Excel
                    </el-button> -->
                    <el-button
                        type="danger"
                        :icon="DocumentIcon"
                        :loading="exportandoPdf"
                        @click="exportarPdf"
                        size="small"
                    >
                        Exportar PDF
                    </el-button>
                </div>
            </div>

            <!-- ==================== DATOS GENERALES (Campania / Especie / Etapa) ==================== -->
            <el-card shadow="never" class="seccion-card">
                <template #header>
                    <span class="seccion-titulo"
                        >Información de la Campaña</span
                    >
                </template>
                <el-descriptions :column="3" border size="default">
                    <el-descriptions-item label="Piscigranja">{{
                        biometria?.nombre_piscigranja || "-"
                    }}</el-descriptions-item>
                    <el-descriptions-item label="Campaña">{{
                        biometria?.nombre_campania || "-"
                    }}</el-descriptions-item>
                    <el-descriptions-item label="Especie">{{
                        biometria?.nombre_especie || "-"
                    }}</el-descriptions-item>
                    <el-descriptions-item label="Etapa">{{
                        biometria?.nombre_etapa || "-"
                    }}</el-descriptions-item>
                    <el-descriptions-item label="Piscina">{{
                        biometria?.nombre_piscina || "-"
                    }}</el-descriptions-item>
                    <el-descriptions-item label="Fecha de siembra">
                        {{ formatearFecha(campaniaEspecie?.fecha_siembra) }}
                    </el-descriptions-item>
                    <el-descriptions-item label="Cantidad sembrada">
                        {{ formatearNumero(campaniaEspecie?.cantidad_siembra) }}
                    </el-descriptions-item>
                    <el-descriptions-item label="Peso inicial (g)">
                        {{ formatearNumero(campaniaEspecie?.peso_inicial_gr) }}
                    </el-descriptions-item>
                    <el-descriptions-item label="Peso final (g)">
                        {{ formatearNumero(campaniaEspecie?.peso_final_gr) }}
                    </el-descriptions-item>
                </el-descriptions>
            </el-card>

            <!-- ==================== DATOS DE LA BIOMETRÍA ==================== -->
            <el-card shadow="never" class="seccion-card">
                <template #header>
                    <span class="seccion-titulo">Datos de la Biometría</span>
                </template>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-descriptions
                            title="Muestreo"
                            :column="1"
                            border
                            size="default"
                        >
                            <el-descriptions-item label="Fecha inicial">{{
                                formatearFecha(biometria?.fecha_inicial)
                            }}</el-descriptions-item>
                            <el-descriptions-item label="Fecha de muestreo">{{
                                formatearFecha(biometria?.fecha_muestreo)
                            }}</el-descriptions-item>
                            <el-descriptions-item label="Tiempo transcurrido"
                                >{{
                                    biometria?.tiempo_dias
                                }}
                                días</el-descriptions-item
                            >
                            <el-descriptions-item label="Cantidad muestreada">{{
                                formatearNumero(biometria?.cantidad_muestreo)
                            }}</el-descriptions-item>
                            <el-descriptions-item label="% de muestreo"
                                >{{
                                    formatearNumero(
                                        biometria?.muestreo_porcentaje,
                                    )
                                }}%</el-descriptions-item
                            >
                        </el-descriptions>
                    </el-col>

                    <el-col :span="12">
                        <el-descriptions
                            title="Población"
                            :column="1"
                            border
                            size="default"
                        >
                            <el-descriptions-item label="Peces iniciales">{{
                                formatearNumero(
                                    biometria?.cantidad_peces_iniciales,
                                )
                            }}</el-descriptions-item>
                            <el-descriptions-item label="Peces actuales">{{
                                formatearNumero(
                                    biometria?.cantidad_peces_actuales,
                                )
                            }}</el-descriptions-item>
                            <el-descriptions-item label="Tasa de supervivencia">
                                <el-tag :type="colorSupervivencia"
                                    >{{
                                        formatearNumero(
                                            biometria?.tasa_supervivencia_porcentaje,
                                        )
                                    }}%</el-tag
                                >
                            </el-descriptions-item>
                        </el-descriptions>
                    </el-col>
                </el-row>

                <el-row :gutter="16" style="margin-top: 16px">
                    <el-col :span="12">
                        <el-descriptions
                            title="Biomasa y alimentación"
                            :column="1"
                            border
                            size="default"
                        >
                            <el-descriptions-item
                                label="Biomasa inicial (kg)"
                                >{{
                                    formatearNumero(biometria?.bi_kg)
                                }}</el-descriptions-item
                            >
                            <el-descriptions-item label="Biomasa final (kg)">{{
                                formatearNumero(biometria?.bf_kg)
                            }}</el-descriptions-item>
                            <el-descriptions-item
                                label="Alimento consumido (kg)"
                                >{{
                                    formatearNumero(
                                        biometria?.total_alimento_consumido_kg,
                                    )
                                }}</el-descriptions-item
                            >
                            <el-descriptions-item
                                label="Conversión alimenticia (FCA)"
                                >{{
                                    formatearNumero(
                                        biometria?.conversion_alimenticia,
                                    )
                                }}</el-descriptions-item
                            >
                        </el-descriptions>
                    </el-col>

                    <el-col :span="12">
                        <el-descriptions
                            title="Crecimiento"
                            :column="1"
                            border
                            size="default"
                        >
                            <el-descriptions-item label="Peso promedio (g)">{{
                                formatearNumero(biometria?.prom_peso_g)
                            }}</el-descriptions-item>
                            <el-descriptions-item
                                label="Longitud promedio (cm)"
                                >{{
                                    formatearNumero(biometria?.prom_longitud_cm)
                                }}</el-descriptions-item
                            >
                            <el-descriptions-item
                                label="Tasa de crecimiento (g/día)"
                                >{{
                                    formatearNumero(
                                        biometria?.tasa_crecimiento_g_dia,
                                    )
                                }}</el-descriptions-item
                            >
                        </el-descriptions>
                    </el-col>
                </el-row>

                <el-descriptions
                    v-if="biometria?.observaciones"
                    :column="1"
                    border
                    style="margin-top: 16px"
                >
                    <el-descriptions-item label="Observaciones">{{
                        biometria.observaciones
                    }}</el-descriptions-item>
                </el-descriptions>
            </el-card>

            <!-- ==================== GRÁFICOS DE DISTRIBUCIÓN ==================== -->
            <el-row :gutter="16" class="seccion-card">
                <el-col :span="12">
                    <el-card shadow="never">
                        <template #header>
                            <span class="seccion-titulo"
                                >Distribución % del Crecimiento en Longitud
                                (cm)</span
                            >
                        </template>

                        <div
                            v-if="distribucionLongitud.length"
                            ref="chartLongitudEl"
                            class="chart"
                        ></div>
                        <el-empty v-else description="Sin datos suficientes" />
                    </el-card>
                </el-col>

                <el-col :span="12">
                    <el-card shadow="never">
                        <template #header>
                            <span class="seccion-titulo"
                                >Distribución % del Crecimiento en Peso
                                (g)</span
                            >
                        </template>

                        <div
                            v-if="distribucionPeso.length"
                            ref="chartPesoEl"
                            class="chart"
                        ></div>
                        <el-empty v-else description="Sin datos suficientes" />
                    </el-card>
                </el-col>
            </el-row>

            <!-- ==================== TABLA DE DETALLES ==================== -->
             <el-row :gutter="16">
                <el-col :span="8" class="d-flex">
                    <el-card shadow="never" class="seccion-card h-100 w-100">
                        <template #header>
                            <span class="seccion-titulo"
                                >Detalle de Muestras ({{
                                    biometria?.detalles?.length || 0
                                }})</span
                            >
                        </template>
                        <el-table
                            :data="biometria?.detalles || []"
                            border
                            stripe
                            size="default"
                        >
                            <el-table-column prop="numero" label="N° Pez" width="80" />
                            <el-table-column prop="peso_g" label="Peso (g)">
                                <template #default="{ row }">{{
                                    formatearNumero(row.peso_g)
                                }}</template>
                            </el-table-column>
                            <el-table-column
                                prop="longitud_cm"
                                label="Longitud (cm)"
                            >
                                <template #default="{ row }">{{
                                    formatearNumero(row.longitud_cm)
                                }}</template>
                            </el-table-column>
                        </el-table>
                    </el-card>
                </el-col>

                <el-col :span="8" class="d-flex">
                    <el-card shadow="never" class="seccion-card h-100 w-100">
                        <template #header>
                            <span class="seccion-titulo"
                                >Crecimiento en  Longitud (cm)</span
                            >
                        </template>
                        <el-table
                            v-if="distribucionLongitud.length"
                            :data="distribucionLongitud"
                            border
                            stripe
                            size="default"
                            style="margin-top: 12px"
                        >
                            <el-table-column prop="rango" label="Rango" />
                            <el-table-column
                                prop="cantidad"
                                label="Cantidad"
                                align="right"
                            />
                            <el-table-column label="%" align="right">
                                <template #default="{ row }"
                                    >{{
                                        formatearNumero(row.porcentaje)
                                    }}%</template
                                >
                            </el-table-column>
                        </el-table>
                    </el-card>
                </el-col>

                <el-col :span="8" class="d-flex">
                    <el-card shadow="never" class="seccion-card h-100 w-100">
                        <template #header>
                            <span class="seccion-titulo"
                                >Crecimiento en  Peso (g)</span
                            >
                        </template>
                        <el-table
                            v-if="distribucionPeso.length"
                            :data="distribucionPeso"
                            border
                            stripe
                            size="default"
                            style="margin-top: 12px"
                        >
                            <el-table-column prop="rango" label="Rango" />
                            <el-table-column
                                prop="cantidad"
                                label="Cantidad"
                                align="right"
                            />
                            <el-table-column label="%" align="right">
                                <template #default="{ row }"
                                    >{{
                                        formatearNumero(row.porcentaje)
                                    }}%</template
                                >
                            </el-table-column>
                        </el-table>
                    </el-card>
                </el-col>

             </el-row>
        </div>
    </App>
</template>
<style scoped>
.biometria-show {
    padding: 16px;
}

.header-actions {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
    flex-wrap: wrap;
    gap: 12px;
}

.titulo {
    margin: 0 0 4px 0;
    font-size: 20px;
    font-weight: 600;
}

.subtitulo {
    margin: 0;
    color: var(--el-text-color-secondary);
    font-size: 13px;
}

.acciones {
    display: flex;
    gap: 8px;
}

.seccion-card {
    margin-bottom: 16px;
}

.seccion-titulo {
    font-weight: 600;
    font-size: 15px;
}

.chart {
    height: 320px;
}
</style>
