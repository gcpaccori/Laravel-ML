<script setup>
    import { ref, watch } from 'vue';
    import useSubmitForm from '@/Composables/useSubmitForm';
    import { ElMessage } from 'element-plus'
    import * as ElementPlusIconsVue from '@element-plus/icons-vue'

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
    const iconOptions = Object.entries(ElementPlusIconsVue).map(([name, component]) => ({
        label: name,
        value: name,
        component,
    }));
    const form = ref({
        accion : '',
        name : '',
        icon : '',
        type : '',
        name_funcion : '',
        active: true,
        location: ''
    });

    const arrayType = ([
        { value: 'default', label: 'Default' },
        { value: 'primary', label: 'Primary' },
        { value: 'success', label: 'Success' },
        { value: 'info', label: 'Info' },
        { value: 'warning', label: 'Warning' },
        { value: 'danger', label: 'Danger' },
    ]);

    const emit = defineEmits(['update:modelValue', 'saved']);


    // Sincronizar cambios del padre
    watch(() => props.modelValue, (val) => {
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

    const submitFormulario = () => {
        const routeName = props.dataForm ? 'seguridad.accions.update' : 'seguridad.accions.store';
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
        form.value.active = true
        errors.value = {};
    };
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top">
    <DialogForm v-model="dialogVisible" :title="props.dataForm ? 'Formulario Editar Acciones' : 'Formulario Registrar Acciones'" width="30%">
      <el-form-item label="Accion" :error="errors.accion?.[0]" required>
        <el-input v-model="form.accion" />
      </el-form-item>

      <el-form-item label="Nombre" :error="errors.name?.[0]" required>
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

      <el-form-item label="Tipo" :error="errors.type?.[0]" required>
        <el-select v-model="form.type" placeholder="Seleccione un estilo" filterable clearable>
            <el-option
                v-for="item in arrayType"
                :key="item.value"
                :label="item.label"
                :value="item.value"
                type="primary"
            >
                <el-text v-if="item.value !== 'default'" class="mx-1" :type="item.value">{{ item.label }}</el-text>
                <el-text v-else class="mx-1">{{ item.label }}</el-text>
            </el-option>
        </el-select>
      </el-form-item>

      <el-form-item label="Función" :error="errors.name_funcion?.[0]" required>
        <el-input v-model="form.name_funcion" />
      </el-form-item>

    <el-form-item label="Locación" :error="errors.location?.[0]" required>
        <el-select v-model="form.location" clearable>
            <el-option label="TABLA" value="T" />
            <el-option label="MARCO" value="M" />
        </el-select>
    </el-form-item>

      <el-form-item label="Activo" :error="errors.active?.[0]">
          <el-switch class="ml-2" v-model="form.active" style="--el-switch-on-color: #13ce66; --el-switch-off-color: #ff4949"/>
      </el-form-item>

      <template #footer>
        <div class="dialog-footer">
            <el-button type="primary" native-type="submit" :loading="loading">{{props.dataForm ? 'Actualizar' : 'Registrar'}}</el-button>
            <el-button type="danger" @click="dialogVisible = false">Cancelar</el-button>
        </div>
      </template>
    </DialogForm>
  </el-form>
</template>
