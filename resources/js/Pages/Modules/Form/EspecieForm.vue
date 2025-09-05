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
        nombre : '',
        descripcion : ''
    });

    const emit = defineEmits(['update:modelValue', 'saved']);

    // Funciones para cargar datos de ubicación dirección
    const piscigranjasOptions = async () => {
        const response = await axios.get(route('piscigranjas.options'));
        optionsPiscigranjas.value = response.data.data;
    };


    const submitFormulario = () => {
        const routeName = props.dataForm ? 'especies.update' : 'especies.store';
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
    <DialogForm v-model="dialogVisible" :title="props.dataForm ? 'Editar Especie' : 'Registrar Especie'" width="50%">

        <div class="row">
            <div class="col-lg-6">
                <el-form-item label="Especie" :error="errors.nombre?.[0]" required>
                    <el-input v-model="form.nombre" />
                </el-form-item>
            </div>

            <div class="col-lg-6">
                <el-form-item label="Descripción" :error="errors.descripcion?.[0]">
                    <el-input type="textarea" :rows="3" v-model="form.descripcion" />
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
