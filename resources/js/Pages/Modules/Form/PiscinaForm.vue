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
    const optionsPiscigranjas = ref(null);

    const form = ref({
        piscigranja_id : '',
        nombre : '',
        descripcion : '',
        superficie_m2 : 0,
        profundidad_m : 0,
        volumen_m3 : 0,
        estado : 'operativa'
    });

    const emit = defineEmits(['update:modelValue', 'saved']);

    // Funciones
    const piscigranjasOptions = async () => {
        const response = await axios.get(route('piscigranjas.options'));
        optionsPiscigranjas.value = response.data.data;
    };


    const submitFormulario = () => {
        const routeName = props.dataForm ? 'piscinas.update' : 'piscinas.store';
        submitForm({
            url: props.dataForm
                ? route(routeName, props.dataForm.id)
                : route(routeName),
            data: form.value,
            method: props.dataForm ? 'put' : 'post',
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
        form.value.superficie_m2 = 0;
        form.value.profundidad_m = 0;
        form.value.volumen_m3 = 0;
        form.value.estado = 'operativa';

        errors.value = {};
    };

    // Sincronizar cambios del padre
    watch(() => props.modelValue, async (val) => {
        dialogVisible.value = val;
        if (val) {
            resetForm();
            if (props.dataForm) {
                form.value = { ...props.dataForm };
            }
        }
    });

    // Emitir cambios al padre
    watch(dialogVisible, (val) => {
        emit('update:modelValue', val);
    });

    onMounted(  async() => {
        await piscigranjasOptions();
    });
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top">
    <DialogForm v-model="dialogVisible" :title="props.dataForm ? 'Editar Piscina' : 'Registrar Piscina'" width="50%">

        <div class="row">
            <div class="col-lg-4">
                <el-form-item label="Piscigranja" :error="errors.piscigranja_id?.[0]" required>
                    <el-select v-model="form.piscigranja_id" placeholder="Seleccione una piscigranja" filterable>
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
                <el-form-item label="Nombre" :error="errors.nombre?.[0]" required>
                    <el-input v-model="form.nombre" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Descripción" :error="errors.descripcion?.[0]">
                    <el-input v-model="form.descripcion" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Superficie (m2)" :error="errors.superficie_m2?.[0]">
                    <el-input-number style="width: 100%" v-model="form.superficie_m2" :min="0" :precision="2" :step="0.01" placeholder="m2" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Profundidad (m)" :error="errors.profundidad_m?.[0]">
                    <el-input-number style="width: 100%" v-model="form.profundidad_m" :min="0" :precision="2" :step="0.01" placeholder="m" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Volumen (m3)" :error="errors.volumen_m3?.[0]">
                    <el-input-number style="width: 100%" v-model="form.volumen_m3" :min="0" :precision="2" :step="0.01" placeholder="m3" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Estado" :error="errors.estado?.[0]" required>
                    <el-select v-model="form.estado" placeholder="Seleccione">
                        <el-option label="Operativa" value="operativa" />
                        <el-option label="Mantenimiento" value="mantenimiento" />
                        <el-option label="Inactiva" value="inactiva" />
                    </el-select>
                </el-form-item>
            </div>
        </div>

        <template #footer>
            <div class="dialog-footer">
                <el-button size="small" type="primary" native-type="submit" :loading="loading">{{props.dataForm ? 'Actualizar' : 'Registrar'}}</el-button>
                <el-button size="small" type="danger" @click="dialogVisible = false">Cancelar</el-button>
            </div>
        </template>
    </DialogForm>
  </el-form>
</template>
