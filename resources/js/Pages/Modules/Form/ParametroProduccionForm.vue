<script setup>
    import { onMounted, ref, watch } from 'vue';
    import useSubmitForm from '@/Composables/useSubmitForm';
    import { ElMessage, ElMessageBox } from 'element-plus';
    import { useFormReset } from "@/Composables/useFormReset";

    const { loading, progress, errors, submitForm } = useSubmitForm();

    const props = defineProps({
        modelValue: Boolean,
        dataForm: {
            type: Object,
            required: false,
            default: null
        },
    });

    const formInitial = ref({
        campania_etapa_id         : '',
        dias_alimentacion         : 0,
        dias_muestreo             : 0,
        numero_muestreos          : 0,
        cantidad_alimento_total_kg: 0.0,
        racion_diaria_gr          : 0.0,
        frecuencia_diaria         : 0,
        cantidad_por_frecuencia_gr: 0.0,
    });

    const dialogVisible = ref(props.modelValue);
    const emit = defineEmits(['update:modelValue', 'saved']);
    const { form, resetForm: resetFormValues, setFormValues } = useFormReset(formInitial.value);


    // dias_muestreo =  (dias_alimentacion / dias_muestreo)
     const calcularDiasMuestreo = () => {
        const dias_alimentacion = form.value.dias_alimentacion
        const dias_muestreo = form.value.dias_muestreo

        if (dias_alimentacion && dias_muestreo && dias_alimentacion > 0 && dias_muestreo > 0) {
            form.value.numero_muestreos = Math.round(dias_alimentacion / dias_muestreo);
        }
    }

    // racion_diaria_gr = (cantidad_alimento_total_kg / dias_alimentacion)*1000
     const calcularRacionDia = () => {
        const dias_alimentacion = form.value.dias_alimentacion
        const cantidad_alimento_total_kg = form.value.cantidad_alimento_total_kg

        if (dias_alimentacion && cantidad_alimento_total_kg && dias_alimentacion > 0 && cantidad_alimento_total_kg > 0) {
            form.value.racion_diaria_gr = parseFloat(((cantidad_alimento_total_kg / dias_alimentacion)*1000).toFixed(6))
        }
    }

    // cantidad_por_frecuencia_gr // (racion_diaria_gr / frecuencia_diaria)
     const calcularFrecuencia = () => {
        const racion_diaria_gr = form.value.racion_diaria_gr
        const frecuencia_diaria = form.value.frecuencia_diaria

        if (racion_diaria_gr && frecuencia_diaria && racion_diaria_gr > 0 && frecuencia_diaria > 0) {
            form.value.cantidad_por_frecuencia_gr = parseFloat((racion_diaria_gr / frecuencia_diaria).toFixed(6))
        }
    }

    const submitFormulario = () => {
        const routeName = props.dataForm?.parametros_produccion ? 'parametros.produccion.update' : 'parametros.produccion.store';
        submitForm({
            url: props.dataForm?.parametros_produccion
                ? route(routeName, props.dataForm.parametros_produccion.id)
                : route(routeName),
            data: form.value,
            method: props.dataForm?.parametros_produccion ? 'put' : 'post',
            emit,
            onSuccess: (response) => {
                dialogVisible.value = false;
                ElMessage({
                    message: response.data.message,
                    type: 'success',
                });
                resetForm();
            }
        });
    };

    const resetForm = () => {
        resetFormValues();
        errors.value = {};
    };

    // Sincronizar cambios del padre
    watch(() => props.modelValue, async (val) => {
        dialogVisible.value = val;
        formInitial.value.campania_etapa_id = props.dataForm.campania_etapa_id;
        if (val) {
            if (props.dataForm.parametros_produccion) {
                setFormValues({
                    ...props.dataForm.parametros_produccion,
                });
            } else {
                resetForm();
            }
        }
    });

    // Emitir cambios al padre
    watch(dialogVisible, (val) => {
        emit('update:modelValue', val);
        if (!val) {
            resetForm(); // Resetear cuando se cierre
        }
    });
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top">
    <DialogForm v-model="dialogVisible" :title="props.dataForm?.parametros_produccion ? 'Editar Parámetros' : 'Registrar Parámetros'" width="50%">
        <h6 class="text-muted">Muestreo y Alimentación</h6>
        <div class="separator separator-dotted mb-5"></div>
        <div class="row mb-5">
            <div class="col-lg-4">
                <el-form-item label="Tiempo de alimentación (Días)" :error="errors.dias_alimentacion?.[0]">
                    <el-input-number @change="calcularDiasMuestreo" class="w-100" v-model="form.dias_alimentacion" :min="0" :step="1" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Tiempo de muestreo (Días)" :error="errors.dias_muestreo?.[0]">
                    <el-input-number @change="calcularDiasMuestreo" class="w-100" v-model="form.dias_muestreo" :min="0" :step="1" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="N° de muestreos" :error="errors.numero_muestreos?.[0]">
                    <el-input-number class="w-100" v-model="form.numero_muestreos" :min="0" :step="1" disabled/>
                </el-form-item>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-lg-4">
                <el-form-item label="Alimento total (Kg)" :error="errors.cantidad_alimento_total_kg?.[0]">
                    <el-input-number @change="calcularRacionDia" class="w-100" v-model="form.cantidad_alimento_total_kg" :precision="2" :step="0.01" :min="0"/>
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Ración diaria (g/día)" :error="errors.racion_diaria_gr?.[0]">
                    <el-input-number @change="calcularFrecuencia" class="w-100" v-model="form.racion_diaria_gr" :precision="6" :step="0.01" :min="0" disabled/>
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="N° de veces" :error="errors.frecuencia_diaria?.[0]">
                    <el-input-number @change="calcularFrecuencia" class="w-100" v-model="form.frecuencia_diaria" :min="0" :step="1" />
                </el-form-item>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-lg-4">
                <el-form-item label="Frecuencia de alimentación (g)" :error="errors.cantidad_por_frecuencia_gr?.[0]">
                    <el-input-number class="w-100" v-model="form.cantidad_por_frecuencia_gr" :precision="6" :step="0.01" :min="0" disabled/>
                </el-form-item>
            </div>
        </div>
        <template #footer>
            <div class="dialog-footer">
                <el-button size="small" type="success" native-type="submit" :loading="loading">{{props.dataForm?.parametros_produccion ? 'Actualizar' : 'Registrar'}}</el-button>
                <el-button size="small" type="danger" @click="dialogVisible = false">Cancelar</el-button>
            </div>
        </template>
    </DialogForm>
  </el-form>
</template>
