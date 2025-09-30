<script setup>
import { onMounted, ref, watch } from "vue";
import useSubmitForm from "@/Composables/useSubmitForm";
import { ElMessage, ElMessageBox } from "element-plus";
import { useFormReset } from "@/Composables/useFormReset";

const { loading, progress, errors, submitForm } = useSubmitForm();

const props = defineProps({
    modelValue: Boolean,
    dataForm: {
        type: Object,
        required: false,
        default: null,
    },
});

const formInitial = ref({
    campania_especie_id: "",
    piscigranja_id: "",
    etapa_id: "",
    piscina_id: "",
    area_piscigranja_m2: 0.0,
    volumen_piscigranja_m3: 0.0,
    altura_piscigranja_m: 0.0,
    fecha_inicio: "",
    fecha_fin: "",
    numero_peces_inicial: 0,
    numero_peces_final: 0,
    peso_inicial_gr: 0.0,
    peso_final_gr: 0.0,
    densidad_siembra: 0.0,
    estado: "planificada",
});

const dialogVisible = ref(props.modelValue);
const piscinasList = ref(null);
const etapas = ref(null);

const {
    form,
    resetForm: resetFormValues,
    setFormValues,
} = useFormReset(formInitial.value);

const emit = defineEmits(["update:modelValue", "saved"]);

const piscinasOptions = async () => {
    const { data } = await axios.get(
        route("piscigranjas.piscinas", form.value.piscigranja_id)
    );
    piscinasList.value = data;
};

const getEtapas = async () => {
    const { data } = await axios.get(route("etapas.options"));
    etapas.value = data;
};

const submitFormulario = () => {
    const routeName = props.dataForm?.id
        ? "campanias.etapas.update"
        : "campanias.etapas.store";
    submitForm({
        url: props.dataForm?.id
            ? route(routeName, props.dataForm.id)
            : route(routeName),
        data: form.value,
        method: props.dataForm?.id ? "put" : "post",
        emit,
        onSuccess: (response) => {
            dialogVisible.value = false;
            ElMessage({
                message: response.data.message,
                type: "success",
            });
            resetForm();
        },
    });
};

const resetForm = () => {
    resetFormValues();
    errors.value = {};
};

// Calcular Volumen m3
const calcularVolumen = () => {
    const area = form.value.area_piscigranja_m2;
    const altura = form.value.altura_piscigranja_m;

    if (area && altura && area > 0 && altura > 0) {
        form.value.volumen_piscigranja_m3 = parseFloat(
            (area * altura).toFixed(2)
        );
    }

    calcularDensidad();
};

const calcularDensidad = () => {
    const peces = form.value.numero_peces_inicial;
    const volumen = form.value.volumen_piscigranja_m3;

    if (peces && volumen && peces > 0 && volumen > 0) {
        form.value.densidad_siembra = parseFloat((peces / volumen).toFixed(4));
    }
};

// Sincronizar cambios del padre
watch(
    () => props.modelValue,
    async (val) => {
        dialogVisible.value = val;
        if (val) {
            if (props.dataForm) {
                // Modo editar: usar setFormValues del composable
                setFormValues({
                    ...props.dataForm,
                    // password: '' // Siempre limpiar password en modo editar
                });
                await piscinasOptions();
            } else {
                // Modo nuevo: resetear completamente
                resetForm();
            }
        }
    }
);

// Emitir cambios al padre
watch(dialogVisible, (val) => {
    emit("update:modelValue", val);
    if (!val) {
        resetForm(); // Resetear cuando se cierre
    }
});

onMounted(async () => {
    await getEtapas();
});
</script>

<template>
    <el-form
        @submit.prevent="submitFormulario"
        :model="form"
        label-position="top"
    >
        <DialogForm
            v-model="dialogVisible"
            :title="
                props.dataForm?.estado === 'finalizada'
                    ? 'Visualizar Etapa'
                    : props.dataForm?.id
                    ? 'Editar Etapa'
                    : 'Registrar Etapa'
            "
            width="70%"
        >
            <h6 class="text-muted">Información General</h6>
            <div class="separator separator-dotted mb-5"></div>
            <div class="row mb-5">
                <div class="col-lg-3">
                    <el-form-item
                        label="Etapa"
                        :error="errors.etapa_id?.[0]"
                        required
                    >
                        <el-select filterable v-model="form.etapa_id">
                            <el-option
                                v-for="item in etapas"
                                :key="item.id"
                                :label="item.nombre"
                                :value="item.id"
                            />
                        </el-select>
                    </el-form-item>
                </div>
                <div class="col-lg-3">
                    <el-form-item
                        label="Piscinas"
                        :error="errors.piscina_id?.[0]"
                        required
                    >
                        <el-select filterable v-model="form.piscina_id">
                            <el-option
                                v-for="item in piscinasList"
                                :key="item.id"
                                :label="item.nombre"
                                :value="item.id"
                            />
                        </el-select>
                    </el-form-item>
                </div>
                <div class="col-lg-2">
                    <el-form-item
                        label="Fecha de Inicio"
                        :error="errors.fecha_inicio?.[0]"
                        required
                    >
                        <el-date-picker
                            class="w-100"
                            type="date"
                            v-model="form.fecha_inicio"
                            format="DD/MM/YYYY"
                            value-format="YYYY-MM-DD"
                            :clearable="false"
                        />
                    </el-form-item>
                </div>
                <div class="col-lg-2">
                    <el-form-item
                        label="Fecha Fin"
                        :error="errors.fecha_fin?.[0]"
                    >
                        <el-date-picker
                            class="w-100"
                            type="date"
                            v-model="form.fecha_fin"
                            format="DD/MM/YYYY"
                            value-format="YYYY-MM-DD"
                            :clearable="false"
                        />
                    </el-form-item>
                </div>
                <div class="col-lg-2">
                    <el-form-item
                        label="Estado"
                        :error="errors.estado?.[0]"
                        required
                    >
                        <el-select
                            v-model="form.estado"
                            placeholder="Seleccione"
                        >
                            <el-option
                                label="Planificada"
                                value="planificada"
                            />
                            <el-option label="En Proceso" value="en_proceso" />
                            <el-option label="Finalizada" value="finalizada" />
                            <el-option label="Cancelada" value="cancelada" />
                        </el-select>
                    </el-form-item>
                </div>
            </div>
            <div class="row mb-5">

            </div>

            <h6 class="text-muted">Características de la Piscigranja</h6>
            <div class="separator separator-dotted mb-5"></div>
            <div class="row mb-5">
                <div class="col-lg-3">
                    <el-form-item
                        label="Área (m2)"
                        :error="errors.area_piscigranja_m2?.[0]"
                    >
                        <el-input-number
                            @change="calcularVolumen"
                            class="w-100"
                            v-model="form.area_piscigranja_m2"
                            :min="0"
                            :precision="2"
                            :step="0.01"
                            placeholder="m2"
                        />
                    </el-form-item>
                </div>
                <div class="col-lg-3">
                    <el-form-item
                        label="Altura (m)"
                        :error="errors.altura_piscigranja_m?.[0]"
                    >
                        <el-input-number
                            @change="calcularVolumen"
                            class="w-100"
                            v-model="form.altura_piscigranja_m"
                            :min="0"
                            :precision="2"
                            :step="0.01"
                            placeholder="m"
                        />
                    </el-form-item>
                </div>
                <div class="col-lg-3">
                    <el-form-item
                        label="Volumen (m3)"
                        :error="errors.volumen_piscigranja_m3?.[0]"
                    >
                        <el-input-number
                            class="w-100"
                            v-model="form.volumen_piscigranja_m3"
                            :min="0"
                            :precision="2"
                            :step="0.01"
                            disabled
                        />
                    </el-form-item>
                </div>
            </div>

            <h6 class="text-muted">Población y Crecimiento</h6>
            <div class="separator separator-dotted mb-5"></div>
            <div class="row mb-5">
                <div class="col-lg-3">
                    <el-form-item
                        label="Cantidad Inicial"
                        :error="errors.numero_peces_inicial?.[0]"
                        required
                    >
                        <el-input-number
                            @change="calcularDensidad"
                            class="w-100"
                            v-model="form.numero_peces_inicial"
                            :min="0"
                            :step="1"
                        />
                    </el-form-item>
                </div>
                <div class="col-lg-3">
                    <el-form-item
                        label="Cantidad Final"
                        :error="errors.numero_peces_final?.[0]"
                    >
                        <el-input-number
                            class="w-100"
                            v-model="form.numero_peces_final"
                            :min="0"
                            :step="1"
                        />
                    </el-form-item>
                </div>
                <div class="col-lg-3">
                    <el-form-item
                        label="Peso Inicial (g)"
                        :error="errors.peso_inicial_gr?.[0]"
                        required
                    >
                        <el-input-number
                            class="w-100"
                            v-model="form.peso_inicial_gr"
                            :precision="2"
                            :step="0.01"
                            :min="0"
                        />
                    </el-form-item>
                </div>
                <div class="col-lg-3">
                    <el-form-item
                        label="Peso Final (g)"
                        :error="errors.peso_final_gr?.[0]"
                    >
                        <el-input-number
                            class="w-100"
                            v-model="form.peso_final_gr"
                            :precision="2"
                            :step="0.01"
                            :min="0"
                        />
                    </el-form-item>
                </div>
            </div>
            <div class="row">
                <div class="col-lg-3">
                    <el-form-item
                        label="Densidad (Peces/m3)"
                        :error="errors.densidad_siembra?.[0]"
                    >
                        <el-input-number
                            class="w-100"
                            v-model="form.densidad_siembra"
                            :min="0"
                            :precision="2"
                            :step="0.01"
                            disabled
                        />
                    </el-form-item>
                </div>
            </div>

            <template #footer>
                <div class="dialog-footer">
                    <el-button
                        size="small"
                        type="primary"
                        native-type="submit"
                        :loading="loading"
                        >{{
                            props.dataForm?.id ? "Actualizar" : "Registrar"
                        }}</el-button
                    >
                    <el-button
                        size="small"
                        type="danger"
                        @click="dialogVisible = false"
                        >Cancelar</el-button
                    >
                </div>
            </template>
        </DialogForm>
    </el-form>
</template>
