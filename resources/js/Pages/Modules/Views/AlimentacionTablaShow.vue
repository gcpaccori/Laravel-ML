<script setup>
import { computed } from "vue";
import { router } from "@inertiajs/vue3";

const props = defineProps({
    title: String,
    toolbar: { type: Array, required: false },
    campaniaEspecie: { type: Object, required: true },
    tabla: { type: Object, default: null },
    titleTabla: { type: String, default: "-" },
});

const filasTabla = computed(() => {
    if (!props.tabla) return [];
    const filas = [];
    props.tabla.meses.forEach((mes) => {
        mes.semanas.forEach((semana, idx) => {
            filas.push({
                ...semana,
                numero_mes: mes.numero_mes,
                tipo_alimento: mes.tipo_alimento,
                consumo_mensual_kg: mes.consumo_mensual_kg,
                esPrimeraSemanaDelMes: idx === 0,
                totalSemanasDelMes: mes.semanas.length,
            });
        });
    });
    return filas;
});

function combinarCeldas({ row, column }) {
    const columnasAgrupadas = [
        "numero_mes",
        "tipo_alimento",
        "consumo_mensual_kg",
    ];
    if (columnasAgrupadas.includes(column.property)) {
        return row.esPrimeraSemanaDelMes ? [row.totalSemanasDelMes, 1] : [0, 0];
    }
}

const back = () => {
    router.visit(route("produccion.alimentacionbfts.index"));
};

const handleEdit = () => {
    router.visit(route("alimentacionbfts.edit", props.campaniaEspecie.id));
};
</script>
<template>
    <App :title="title" :toolbar="toolbar">
        <div class="container-fluid p-0 d-flex flex-column gap-3" v-if="tabla">
            <div
                class="d-flex align-items-center justify-content-between gap-2"
            >
                <el-button type="info" @click="back" size="small" icon="Back">
                    Regresar
                </el-button>

                <div class="d-flex align-items-center gap-2">
                    <el-button
                        type="warning"
                        icon="EditPen"
                        @click="handleEdit"
                        size="small"
                    >
                        Editar
                    </el-button>

                    <el-dropdown size="small">
                        <el-button type="primary" size="small" icon="Download">
                            Exportar
                            <el-icon class="el-icon--right"
                                ><ArrowDown
                            /></el-icon>
                        </el-button>
                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item>
                                    <a
                                        :href="
                                            route(
                                                'alimentacionbfts.pdf',
                                                campaniaEspecie.id,
                                            )
                                        "
                                        class="dropdown-link text-dark"
                                    >
                                        <el-icon class="me-1"
                                            ><Document
                                        /></el-icon>
                                        Exportar PDF
                                    </a>
                                </el-dropdown-item>
                                <el-dropdown-item>
                                    <a
                                        :href="
                                            route(
                                                'alimentacionbfts.excel',
                                                campaniaEspecie.id,
                                            )
                                        "
                                        class="dropdown-link text-dark"
                                    >
                                        <el-icon class="me-1"
                                            ><DocumentChecked
                                        /></el-icon>
                                        Exportar Excel
                                    </a>
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </div>
            </div>

            <el-card shadow="never" class="w-100">
                <div>
                    <h5 class="card-title fw-bold text-dark mb-0">
                        {{ titleTabla }}
                    </h5>
                </div>
                <el-descriptions
                    title="Resumen General de Alimentación"
                    :column="2"
                    border
                    class="mb-4"
                    size="small"
                >
                    <el-descriptions-item label="Población inicial">
                        <span class="fw-semibold">{{
                            tabla.tabla.poblacion_inicial?.toLocaleString()
                        }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="Mortalidad">
                        <span class="fw-semibold"
                            >{{ tabla.tabla.mortalidad_porcentaje }}%</span
                        >
                    </el-descriptions-item>
                    <el-descriptions-item label="N° semanas">
                        <span class="fw-semibold">{{
                            tabla.tabla.numero_semanas
                        }}</span>
                    </el-descriptions-item>
                    <el-descriptions-item label="Semanas por mes">
                        <span class="fw-semibold">{{
                            tabla.tabla.semanas_por_mes
                        }}</span>
                    </el-descriptions-item>
                </el-descriptions>

                <el-divider class="my-3" />

                <div class="table-responsive w-100">
                    <el-table
                        :data="filasTabla"
                        border
                        size="small"
                        :span-method="combinarCeldas"
                        class="w-100"
                    >
                        <el-table-column
                            prop="numero_mes"
                            label="Mes"
                            min-width="80"
                            align="center"
                            :formatter="
                                (row) =>
                                    `Mes ${String(row.numero_mes).padStart(2, '0')}`
                            "
                        />
                        <el-table-column
                            prop="numero_semana"
                            label="Semana"
                            min-width="80"
                            align="center"
                        />
                        <el-table-column
                            prop="ganancia_peso_g"
                            label="Ganancia peso (g)"
                            min-width="140"
                            align="right"
                        />
                        <el-table-column
                            prop="poblacion"
                            label="Población"
                            min-width="110"
                            align="center"
                            :formatter="
                                (row) => Number(row.poblacion).toLocaleString()
                            "
                        />
                        <el-table-column
                            prop="biomasa_kg"
                            label="Biomasa (Kg)"
                            min-width="110"
                            align="right"
                        />
                        <el-table-column
                            prop="tasa_alimentacion_porcentaje"
                            label="T.A (%)"
                            min-width="80"
                            align="center"
                        />
                        <el-table-column
                            prop="consumo_diario_kg"
                            label="Consumo diario (Kg)"
                            min-width="140"
                            align="right"
                        />
                        <el-table-column
                            prop="consumo_semanal_kg"
                            label="Consumo semanal (Kg)"
                            min-width="160"
                            align="right"
                        />
                        <el-table-column
                            prop="tipo_alimento"
                            label="Tipo de alimento"
                            min-width="130"
                            align="center"
                        />
                        <el-table-column
                            prop="consumo_mensual_kg"
                            label="Consumo mensual (Kg)"
                            min-width="160"
                            align="center"
                        />
                        <el-table-column
                            v-for="(horario, index) in tabla.horarios"
                            :key="horario.hora"
                            :label="horario.hora"
                            min-width="90"
                            align="right"
                            :formatter="
                                (row) => row.frecuencias[index]?.gramos ?? '-'
                            "
                        />
                    </el-table>
                </div>
            </el-card>
        </div>

        <div class="py-5" v-else>
            <el-empty
                description="Aún no hay una tabla de alimentación para esta campaña/especie."
            />
        </div>
    </App>
</template>

<style scoped>
:deep(.el-descriptions__label) {
    font-weight: 500;
}
</style>
