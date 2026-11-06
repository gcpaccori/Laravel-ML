<script setup>
import { router } from "@inertiajs/vue3";
import { computed, onMounted, ref, watch } from "vue";
import useSubmitForm from "@/Composables/useSubmitForm";
import { ElMessage, ElMessageBox } from "element-plus";
import { InfoFilled } from "@element-plus/icons-vue";
import { useFormReset } from "@/Composables/useFormReset";
import FormSection from "@/Components/FormSection.vue";

const props = defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false,
    },
    dataForm: {
        type: Object,
        required: false,
        default: null,
    },
    campaniaEspecie: {
        type: Object,
        required: true,
    },
    tabla: {
        type: Object,
        required: false,
        default: null,
    },
    titleTabla: {
        type: String,
        default: "-",
    },
});

const SEMANAS_POR_MES = 4;
const loadingForm = ref(true);

// Curva de Progresión Normalizada (20 Semanas) extraída del modelo BFT B-Rubio Excel
const CURVA_PESO_BASE = [
    0.33, 1.0, 2.7, 5.8, 11.0, 17.0, 24.0, 32.0, 42.0, 54.0, 68.0, 85.0, 105.0,
    128.0, 155.0, 185.0, 218.0, 255.0, 300.0, 325.0,
];

const CURVA_TA_BASE = [
    15.0, 12.0, 10.0, 9.0, 8.0, 7.0, 6.0, 5.5, 5.0, 4.5, 4.3, 4.0, 3.8, 3.5,
    3.3, 3.2, 3.2, 3.0, 2.8, 2.5,
];

// Rangos T.A.
const taInicial = ref(15);
const taFinal = ref(2.5);

// Rangos de Peso (g) extraídos de props.campaniaEspecie
const pesoInicial = ref(props.campaniaEspecie?.peso_inicial_gr ?? 1);
const pesoFinal = ref(props.campaniaEspecie?.peso_final_gr ?? 250);

const mesesInicialesForm = mesesIniciales().length
    ? mesesIniciales()
    : [
          { numero_mes: 1, tipo_alimento: "0.45" },
          { numero_mes: 2, tipo_alimento: "0.4" },
          { numero_mes: 3, tipo_alimento: "0.32" },
          { numero_mes: 4, tipo_alimento: "0.28" },
          { numero_mes: 5, tipo_alimento: "0.28" },
      ];

const formInitial = ref({
    poblacion_inicial: props.campaniaEspecie?.cantidad_siembra ?? 2000,
    mortalidad_porcentaje: 10,
    numero_semanas: mesesInicialesForm.length * SEMANAS_POR_MES,
    semanas_por_mes: SEMANAS_POR_MES,
    observaciones: "",
    horarios: [
        { hora: "08:00" },
        { hora: "11:00" },
        { hora: "14:00" },
        { hora: "17:00" },
    ],
    semanas: [],
    meses: mesesInicialesForm,
});

const { loading, progress, errors, submitForm } = useSubmitForm();
const {
    form,
    resetForm: resetFormValues,
    setFormValues,
} = useFormReset(formInitial.value);

function mesesIniciales() {
    if (!props.tabla && !props.dataForm) return [];
    const mesesSource = props.dataForm?.meses || props.tabla?.meses;
    return mesesSource
        ? mesesSource.map((mes) => ({
              numero_mes: mes.numero_mes,
              tipo_alimento: mes.tipo_alimento ?? "",
          }))
        : [];
}

function semanasIniciales() {
    if (!props.tabla && !props.dataForm) return [];
    const mesesSource = props.dataForm?.meses || props.tabla?.meses;
    if (!mesesSource) return [];
    return mesesSource.flatMap((mes) =>
        mes.semanas.map((s) => ({
            numero_semana: s.numero_semana,
            ganancia_peso_g: Number(s.ganancia_peso_g),
            tasa_alimentacion_porcentaje: Number(
                s.tasa_alimentacion_porcentaje,
            ),
        })),
    );
}

/**
 * Interpola en la curva de crecimiento progresiva exponencial/exponencial-acelerada
 * permitiendo mapear N semanas a la proporción original del Excel.
 */
function obtenerFactorProgresivo(index, totalSemanas, listaBase) {
    if (totalSemanas <= 1) return 0;
    const posicionRelativa =
        (index / (totalSemanas - 1)) * (listaBase.length - 1);
    const idxInferior = Math.floor(posicionRelativa);
    const idxSuperior = Math.min(idxInferior + 1, listaBase.length - 1);
    const fraccion = posicionRelativa - idxInferior;

    const valInf = listaBase[idxInferior];
    const valSup = listaBase[idxSuperior];

    // Retorna el valor interpolado sobre la curva base BFT
    const valInterpolado = valInf + (valSup - valInf) * fraccion;

    // Normalización [0, 1]
    const baseMin = listaBase[0];
    const baseMax = listaBase[listaBase.length - 1];
    return (valInterpolado - baseMin) / (baseMax - baseMin);
}

// Calcula de forma progresiva la tabla de semanas
function recaularSemanas() {
    const totalSemanas = form.value.meses.length * SEMANAS_POR_MES;
    form.value.numero_semanas = totalSemanas;

    const pMin = Number(pesoInicial.value) || 0;
    const pMax = Number(pesoFinal.value) || 0;
    const taMin = Number(taInicial.value) || 0;
    const taMax = Number(taFinal.value) || 0;

    const nuevasSemanas = [];
    for (let i = 0; i < totalSemanas; i++) {
        const numSemana = i + 1;

        // Factor progresivo según curva BFT
        const factorPeso = obtenerFactorProgresivo(
            i,
            totalSemanas,
            CURVA_PESO_BASE,
        );
        const factorTA = obtenerFactorProgresivo(
            i,
            totalSemanas,
            CURVA_TA_BASE,
        );

        const pesoCalc = Number((pMin + factorPeso * (pMax - pMin)).toFixed(2));
        const taCalc = Number((taMin + factorTA * (taMax - taMin)).toFixed(2));

        const semanaExistente = form.value.semanas.find(
            (s) => s.numero_semana === numSemana,
        );
        nuevasSemanas.push({
            numero_semana: numSemana,
            ganancia_peso_g:
                semanaExistente && props.dataForm
                    ? semanaExistente.ganancia_peso_g
                    : pesoCalc,
            tasa_alimentacion_porcentaje:
                semanaExistente && props.dataForm
                    ? semanaExistente.tasa_alimentacion_porcentaje
                    : taCalc,
        });
    }
    form.value.semanas = nuevasSemanas;
}

function aplicarCalculosAutoguiados() {
    const totalSemanas = form.value.semanas.length;
    if (totalSemanas === 0) return;

    const pMin = Number(pesoInicial.value) || 0;
    const pMax = Number(pesoFinal.value) || 0;
    const taMin = Number(taInicial.value) || 0;
    const taMax = Number(taFinal.value) || 0;

    form.value.semanas.forEach((semana, i) => {
        const factorPeso = obtenerFactorProgresivo(
            i,
            totalSemanas,
            CURVA_PESO_BASE,
        );
        const factorTA = obtenerFactorProgresivo(
            i,
            totalSemanas,
            CURVA_TA_BASE,
        );

        semana.ganancia_peso_g = Number(
            (pMin + factorPeso * (pMax - pMin)).toFixed(2),
        );
        semana.tasa_alimentacion_porcentaje = Number(
            (taMin + factorTA * (taMax - taMin)).toFixed(2),
        );
    });

    ElMessage.success(
        "Semanas recalculadas según la curva de crecimiento progresivo BFT.",
    );
}

function agregarHorario() {
    form.value.horarios.push({ hora: "12:00" });
}

function quitarHorario(index) {
    if (form.value.horarios.length <= 1) {
        ElMessage.warning("Debe quedar al menos un horario de alimentación.");
        return;
    }
    form.value.horarios.splice(index, 1);
}

function agregarMes() {
    const numeroMes = form.value.meses.length + 1;
    form.value.meses.push({ numero_mes: numeroMes, tipo_alimento: "" });
    recaularSemanas();
}

function quitarUltimoMes() {
    if (form.value.meses.length <= 1) {
        ElMessage.warning("Debe quedar al menos un mes.");
        return;
    }
    form.value.meses.pop();
    recaularSemanas();
}

const semanasAgrupadasPorMes = computed(() => {
    const grupos = {};
    form.value.semanas.forEach((semana) => {
        const numeroMes =
            Math.ceil(semana.numero_semana / SEMANAS_POR_MES) || 1;
        if (!grupos[numeroMes]) grupos[numeroMes] = [];
        grupos[numeroMes].push(semana);
    });
    return grupos;
});

function mesForm(numeroMes) {
    return form.value.meses.find((m) => m.numero_mes === numeroMes);
}

const resetForm = () => {
    resetFormValues();
    errors.value = {};
    recaularSemanas();
};

const back = () => {
    router.visit(route("produccion.alimentacionbfts.index"));
};

const getError = (path) => {
    return errors.value?.[path]?.[0] || '';
};

const submit = () => {
    submitForm({
        url: route("alimentacionbfts.store", props.campaniaEspecie.id),
        data: {
            ...form.value,
            peso_inicial_gr: pesoInicial.value,
            peso_final_gr: pesoFinal.value,
            ta_inicial: taInicial.value,
            ta_final: taFinal.value,
        },
        method: "post",
        onSuccess: (response) => {
            const campaniaEspecieId = response.data.data.campania_especie_id;
            ElMessageBox.confirm(response.data.message, "Éxito", {
                confirmButtonText: "Visualizar Resultados",
                cancelButtonText: "Mostrar Tabla",
                type: "success",
                center: true,
                closeOnPressEscape: false,
                closeOnClickModal: false,
                showClose: false,
            })
                .then(() => {
                    window.location.href = route(
                        "alimentacionbfts.show",
                        campaniaEspecieId,
                    );
                })
                .catch(() => {
                    back();
                });

            resetForm();
        },
    });
};

onMounted(async () => {
    if (props.dataForm) {
        const semanasObtenidas = semanasIniciales();
        setFormValues({
            ...props.dataForm.tabla,
            horarios: props.dataForm?.horarios,
            semanas: semanasObtenidas.length ? semanasObtenidas : [],
            meses: props.dataForm?.meses,
        });
    } else {
        resetForm();
    }

    if (!form.value.semanas || form.value.semanas.length === 0) {
        recaularSemanas();
    }

    loadingForm.value = false;
});
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <template v-if="loadingForm">
            <el-skeleton :rows="15" animated class="p-3" />
        </template>
        <template v-else>
            <FormSection @submitted="submit">
                <template #form>
                    <div class="container-fluid p-0">
                        <div>
                            <h5 class="card-title fw-bold text-dark mb-0">
                                {{ titleTabla }}
                            </h5>
                        </div>

                        <el-divider content-position="left">Parámetros Generales</el-divider>

                        <div class="row g-3">
                            <div class="col-12 col-md-4 col-lg-3">
                                <el-form-item
                                    :error="getError('poblacion_inicial')"
                                    required
                                >
                                    <template #label>
                                        <span class="fw-medium">Población inicial</span>
                                        <el-tooltip
                                            content="Cantidad de siembra ingresada al inicio de la campaña"
                                            placement="top"
                                        >
                                            <el-icon
                                                class="ms-1 align-middle text-muted cursor-pointer"
                                                ><InfoFilled
                                            /></el-icon>
                                        </el-tooltip>
                                    </template>
                                    <el-input
                                        type="number"
                                        v-model="form.poblacion_inicial"
                                        :min="0"
                                        disabled
                                    />
                                </el-form-item>
                            </div>

                            <div class="col-12 col-md-4 col-lg-3">
                                <el-form-item
                                    label="Mortalidad (%)"
                                    :error="getError('mortalidad_porcentaje')"
                                    required
                                >
                                    <el-input
                                        type="number"
                                        v-model="form.mortalidad_porcentaje"
                                        :min="0"
                                        :max="100"
                                        :step="0.5"
                                    />
                                </el-form-item>
                            </div>

                            <div class="col-12 col-md-4 col-lg-6">
                                <el-form-item
                                    label="Observaciones"
                                    :error="getError('observaciones')"
                                >
                                    <el-input
                                        v-model="form.observaciones"
                                        type="textarea"
                                        :rows="1"
                                        placeholder="Añade notas complementarias..."
                                    />
                                </el-form-item>
                            </div>
                        </div>

                        <el-divider content-position="left">
                            Rangos y Proyección de Crecimiento Progresivo BFT
                        </el-divider>

                        <div class="row g-3 align-items-end mb-2">
                            <div class="col-lg-3">
                                <el-form-item
                                    :error="getError('peso_inicial_gr')"
                                    required
                                >
                                    <template #label>
                                        <span class="fw-medium">Peso Inicial (g)</span>
                                        <el-tooltip
                                            content="Peso inicial ingresado al inicio de la campaña"
                                            placement="top"
                                        >
                                            <el-icon
                                                class="ms-1 align-middle text-muted cursor-pointer"
                                                ><InfoFilled
                                            /></el-icon>
                                        </el-tooltip>
                                    </template>
                                    <el-input
                                        type="number"
                                        v-model="pesoInicial"
                                        :min="0"
                                        step="any"
                                    />
                                </el-form-item>
                            </div>

                            <div class="col-lg-3">
                                <el-form-item
                                    :error="getError('peso_final_gr')"
                                    required
                                >
                                    <template #label>
                                        <span class="fw-medium">Peso Final Esperado (g)</span>
                                        <el-tooltip
                                            content="Peso proyectado al finalizar la campaña"
                                            placement="top"
                                        >
                                            <el-icon
                                                class="ms-1 align-middle text-muted cursor-pointer"
                                                ><InfoFilled
                                            /></el-icon>
                                        </el-tooltip>
                                    </template>
                                    <el-input
                                        type="number"
                                        v-model="pesoFinal"
                                        :min="0"
                                        step="any"
                                    />
                                </el-form-item>
                            </div>

                            <div class="col-lg-3">
                                <el-form-item
                                    :error="getError('ta_inicial')"
                                    required
                                >
                                    <template #label>
                                        <span class="fw-medium">T.A. Inicial (%)</span>
                                        <el-tooltip
                                            content="Tasa de Alimentación calculada para el inicio de la campaña"
                                            placement="top"
                                        >
                                            <el-icon
                                                class="ms-1 align-middle text-muted cursor-pointer"
                                                ><InfoFilled
                                            /></el-icon>
                                        </el-tooltip>
                                    </template>
                                    <el-input
                                        type="number"
                                        v-model="taInicial"
                                        :min="0"
                                        :max="100"
                                        step="0.1"
                                    />
                                </el-form-item>
                            </div>

                            <div class="col-lg-3">
                                <el-form-item
                                    :error="getError('ta_final')"
                                    required
                                >
                                    <template #label>
                                        <span class="fw-medium">T.A. Final (%)</span>
                                        <el-tooltip
                                            content="Tasa de Alimentación proyectada para el cierre de la campaña"
                                            placement="top"
                                        >
                                            <el-icon
                                                class="ms-1 align-middle text-muted cursor-pointer"
                                                ><InfoFilled
                                            /></el-icon>
                                        </el-tooltip>
                                    </template>
                                    <el-input
                                        type="number"
                                        v-model="taFinal"
                                        :min="0"
                                        :max="100"
                                        step="0.1"
                                    />
                                </el-form-item>
                            </div>

                            <div class="col-lg-12">
                                <el-button
                                    type="primary"
                                    plain
                                    class="w-100"
                                    size="small"
                                    @click="aplicarCalculosAutoguiados"
                                >
                                    Recalcular Tabla
                                </el-button>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-12 col-xl-5">
                                <el-divider content-position="left">Horarios de alimentación</el-divider>

                                <div class="row">
                                    <div
                                        v-for="(horario, index) in form.horarios"
                                        :key="index"
                                        class="col-12 col-lg-6"
                                    >
                                        <el-form-item
                                            :error="getError(`horarios.${index}.hora`)"
                                        >
                                            <div class="d-flex align-items-center justify-content-between gap-1 p-1 bg-light rounded border w-100">
                                                <el-time-picker
                                                    v-model="horario.hora"
                                                    format="HH:mm"
                                                    value-format="HH:mm"
                                                    placeholder="Hora"
                                                    class="w-100"
                                                />
                                                <el-button
                                                    icon="Delete"
                                                    circle
                                                    type="danger"
                                                    size="small"
                                                    plain
                                                    @click="quitarHorario(index)"
                                                />
                                            </div>
                                        </el-form-item>
                                    </div>
                                </div>

                                <el-button
                                    icon="Plus"
                                    size="small"
                                    type="primary"
                                    plain
                                    @click="agregarHorario"
                                >
                                    Agregar horario
                                </el-button>
                            </div>

                            <div class="col-12 col-xl-7">
                                <el-divider content-position="left">
                                    Planificación por Meses
                                    <span class="text-muted small ms-2">
                                        ({{ form.meses.length }}
                                        {{
                                            form.meses.length === 1
                                                ? "mes"
                                                : "meses"
                                        }}
                                        &bull; {{ form.numero_semanas }} semanas
                                        totales)
                                    </span>
                                </el-divider>

                                <div class="d-flex mb-3 gap-2">
                                    <el-button
                                        type="danger"
                                        icon="Delete"
                                        plain
                                        :disabled="form.meses.length <= 1"
                                        size="small"
                                        @click="quitarUltimoMes"
                                    >
                                        Quitar último mes
                                    </el-button>
                                    <el-button
                                        type="success"
                                        icon="Plus"
                                        size="small"
                                        @click="agregarMes"
                                    >
                                        Agregar mes
                                    </el-button>
                                </div>

                                <el-collapse accordion>
                                    <el-collapse-item
                                        v-for="(
                                            semanasDelMes, numeroMes
                                        ) in semanasAgrupadasPorMes"
                                        :key="numeroMes"
                                        :name="numeroMes"
                                    >
                                        <template #title>
                                            <div
                                                class="d-flex align-items-center w-100 justify-content-between pe-3"
                                            >
                                                <span class="fw-bold text-dark"
                                                    >Mes
                                                    {{
                                                        String(
                                                            numeroMes,
                                                        ).padStart(2, "0")
                                                    }}</span
                                                >
                                                <span
                                                    class="badge bg-secondary-subtle text-secondary rounded-pill"
                                                >
                                                    {{
                                                        semanasDelMes.length
                                                    }}
                                                    semanas
                                                </span>
                                            </div>
                                        </template>

                                        <div class="p-2">
                                            <el-form-item
                                                v-if="
                                                    mesForm(Number(numeroMes))
                                                "
                                                label="Tipo de alimento (%)"
                                                :error="getError(`meses.${Number(numeroMes) - 1}.tipo_alimento`)"
                                                class="mb-3"
                                            >
                                                <el-input
                                                    v-model="
                                                        mesForm(
                                                            Number(numeroMes),
                                                        ).tipo_alimento
                                                    "
                                                    placeholder="Porcentaje alimento"
                                                    size="small"
                                                />
                                            </el-form-item>

                                            <el-table
                                                :data="semanasDelMes"
                                                size="small"
                                                border
                                                class="w-100"
                                            >
                                                <el-table-column
                                                    prop="numero_semana"
                                                    label="Semana"
                                                    width="75"
                                                    align="center"
                                                />
                                                <el-table-column
                                                    label="Ganancia de peso (g)"
                                                >
                                                    <template
                                                        #default="{
                                                            row,
                                                            $index,
                                                        }"
                                                    >
                                                        <el-form-item
                                                            :error="getError(`semanas.${$index}.ganancia_peso_g`)"
                                                            class="mb-0"
                                                        >
                                                            <el-input
                                                                type="number"
                                                                v-model="
                                                                    row.ganancia_peso_g
                                                                "
                                                                :min="0"
                                                                step="any"
                                                                size="small"
                                                            />
                                                        </el-form-item>
                                                    </template>
                                                </el-table-column>

                                                <el-table-column
                                                    label="Tasa de alimentación T.A (%)"
                                                >
                                                    <template
                                                        #default="{
                                                            row,
                                                            $index,
                                                        }"
                                                    >
                                                        <el-form-item
                                                            :error="getError(`semanas.${$index}.tasa_alimentacion_porcentaje`)"
                                                            class="mb-0"
                                                        >
                                                            <el-input
                                                                type="number"
                                                                v-model="
                                                                    row.tasa_alimentacion_porcentaje
                                                                "
                                                                :min="0"
                                                                :max="100"
                                                                step="any"
                                                                size="small"
                                                            />
                                                        </el-form-item>
                                                    </template>
                                                </el-table-column>
                                            </el-table>
                                        </div>
                                    </el-collapse-item>
                                </el-collapse>
                            </div>
                        </div>
                    </div>
                </template>

                <template #actions>
                    <div
                        class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top w-100"
                    >
                        <el-button
                            size="small"
                            type="primary"
                            native-type="submit"
                            :loading="loading"
                        >
                            {{
                                props.dataForm
                                    ? "Actualizar Tabla y Guardar"
                                    : "Generar Tabla y Guardar"
                            }}
                        </el-button>
                        <el-button
                            size="small"
                            type="danger"
                            plain
                            @click.prevent="back()"
                        >
                            Cancelar
                        </el-button>
                    </div>
                </template>
            </FormSection>
        </template>
    </App>
</template>
