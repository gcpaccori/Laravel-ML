<script setup>
    import { onMounted, ref, watch } from 'vue';
    import useSubmitForm from '@/Composables/useSubmitForm';
    import { ElMessage, ElMessageBox } from 'element-plus';

    const { loading, progress, errors, submitForm } = useSubmitForm();

    const props = defineProps({
        modelValue: Boolean,
        dataForm: {
            type: Object,
            required: false,
            default: null
        },
    });

    const dialogVisible = ref(props.modelValue);

    const form = ref({
        fecha_fin: '',
        cantidad_final: 0,
        peso_promedio_gr: 0.0,
    });

    const emit = defineEmits(['update:modelValue', 'saved']);


    const submitFormulario = () => {
        submitForm({
            url: route('campanias.etapas.close', props.dataForm.campania_etapa_id),
            data: form.value,
            method: 'put',
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
        for (const key in form.value) {
            form.value[key] = '';
        }
        form.value.fecha_fin = 0;
        form.value.cantidad_final = 0;
        form.value.peso_promedio_gr = 0.0;
        errors.value = {};
    };

    // Sincronizar cambios del padre
    watch(() => props.modelValue, async (val) => {
        dialogVisible.value = val;
        if (val) {
            resetForm();
            if (props.dataForm) {
                form.value = { ...props.dataForm };
                form.value.fecha_fin = props.dataForm.fecha_fin ?? '';
                form.value.cantidad_final = props.dataForm.cantidad_final ?? 0;
                form.value.peso_promedio_gr = props.dataForm.peso_promedio_gr ?? 0.0;
            }
        }
    });

    // Emitir cambios al padre
    watch(dialogVisible, (val) => {
        emit('update:modelValue', val);
    });
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top">
    <DialogForm v-model="dialogVisible" title="Finalizar Etapa" width="50%">
        <div class="row mb-5">
            <div class="col-lg-4">
                <el-form-item label="Fecha Fin" :error="errors.fecha_fin?.[0]">
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
            <div class="col-lg-4">
                <el-form-item label="Cantidad Final" :error="errors.cantidad_final?.[0]">
                    <el-input-number class="w-100" v-model="form.cantidad_final" :min="0" :step="1" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Peso Promedio (g)" :error="errors.peso_promedio_gr?.[0]">
                    <el-input-number class="w-100" v-model="form.peso_promedio_gr" :precision="2" :step="0.01" :min="0"/>
                </el-form-item>
            </div>
        </div>
        <template #footer>
            <div class="dialog-footer">
                <el-button size="small" type="success" native-type="submit" :loading="loading">Finalizar</el-button>
                <el-button size="small" type="danger" @click="dialogVisible = false">Cancelar</el-button>
            </div>
        </template>
    </DialogForm>
  </el-form>
</template>
