<script setup>
    import { ref, watch } from 'vue';
    import useSubmitForm from '@/Composables/useSubmitForm';
    import { ElMessage } from 'element-plus';
    import * as ElementPlusIconsVue from '@element-plus/icons-vue';

    const { loading, progress, errors, submitForm } = useSubmitForm();

    const props = defineProps({
        modelValue: Boolean,
        sistema: {
            type: Object,
            required: false,
            default: null
        },
    });

    const dialogVisible = ref(props.modelValue);
    const iconOptions = Object.entries(ElementPlusIconsVue).map(([name, component]) => ({
        label: name,
        value: name,
        component,
    }));
    const form = ref({
        name: '',
        icon: '',
        url: '',
        order: ''
    });

    const emit = defineEmits(['update:modelValue', 'saved']);


    // Sincronizar cambios del padre
    watch(() => props.modelValue, (val) => {
        dialogVisible.value = val;
        if (val) {
            resetForm();
            if (props.sistema) {
                form.value = { ...props.sistema };
            }
        }
    });

    // Emitir cambios al padre
    watch(dialogVisible, (val) => {
        emit('update:modelValue', val);
    });

    const submitFormulario = () => {
        const routeName = props.sistema ? 'seguridad.sistemas.update' : 'seguridad.sistemas.store';
        submitForm({
            url: props.sistema
                ? route(routeName, props.sistema.id)
                : route(routeName),
            data: form.value,
            method: props.sistema ? 'put' : 'post',
            emit,
            onSuccess: (response) => {
                dialogVisible.value = false;
                ElMessage({
                    message: response.data.message,
                    type: 'success',
                });
            // Otros efectos colaterales si quieres
            }
        });
    };

    const resetForm = () => {
        for (const key in form.value) {
            form.value[key] = '';
        }

        errors.value = {};
    };
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top">
    <DialogForm v-model="dialogVisible" :title="props.sistema ? 'Formulario Editar Sistema' : 'Formulario Registrar Sistema'" width="30%">
      <el-form-item label="Descripción" :error="errors.name?.[0]" required>
        <el-input v-model="form.name" />
      </el-form-item>

      <el-form-item label="Icono" :error="errors.icon?.[0]" required>
        <el-select v-model="form.icon" placeholder="Seleccione un ícono" filterable clearable>
            <el-option
                v-for="item in iconOptions"
                :key="item.value"
                :label="item.label"
                :value="item.value"
            >
                <div class="d-flex align-items-center">
                    <el-icon :size="20">
                        <component :is="item.label" />
                    </el-icon>
                    <div class="ms-2">
                        {{ item.label }}
                    </div>
                </div>
            </el-option>
        </el-select>
      </el-form-item>

      <el-form-item label="URL" :error="errors.url?.[0]" required>
        <el-input v-model="form.url" />
        <el-text size="small" class="text-muted">* Ingresar url en singular: Ejem. 'usuario'</el-text>
      </el-form-item>

      <el-form-item label="Orden" :error="errors.order?.[0]">
        <el-input v-model="form.order" />
      </el-form-item>

      <template #footer>
        <div class="dialog-footer">
            <!-- <el-progress
                v-if="progress"
                :percentage="progress.percentage"
                :status="progress.percentage === 100 ? 'success' : 'active'"
                :text-inside="true"
                :stroke-width="20"
                style="margin-top: 1rem"
            /> -->
                <el-button type="primary" native-type="submit" :loading="loading">{{props.sistema ? 'Actualizar' : 'Registrar'}}</el-button>
                <el-button type="danger" @click="dialogVisible = false">Cancelar</el-button>
        </div>
      </template>
    </DialogForm>
  </el-form>
</template>
