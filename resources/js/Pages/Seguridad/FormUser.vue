<script setup>
    import axios from 'axios'
    import { onMounted, ref, watch } from 'vue';
    import { ElMessage } from 'element-plus';
    import useSubmitForm from '@/Composables/useSubmitForm';
    import FormRole from './FormRole.vue';

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
    const dialogRole = ref(false);

    const form = ref({
        role_id : '',
        name : '',
        email : '',
        password : ''
    });

    const optionsRoles = ref([]);

    const emit = defineEmits(['update:modelValue', 'saved']);

    const submitFormulario = () => {
        const routeName = props.dataForm ? 'seguridad.usuarios.update' : 'seguridad.usuarios.store';
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

    const roleOptions = async () => {
        const response = await axios.get(route('seguridad.roles.options'));
        optionsRoles.value = response.data.data;
    }

    // AGREGAR SISTEMA
    const savedRole = async (role) => {
        await roleOptions();
        form.value.role_id = role.id
        dialogRole.value = false;
    }

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

    onMounted(() => {
        roleOptions();
    })
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top" class="w-100">
    <DialogForm v-model="dialogVisible" :title="props.dataForm ? 'Formulario Editar Usuario' : 'Formulario Registrar Usuario'" width="50%">
        <el-row :gutter="20">
            <el-col :lg="12">
                <el-form-item label="Nombre" :error="errors.name?.[0]" required>
                    <el-input v-model="form.name" />
                </el-form-item>
            </el-col>

            <el-col :lg="12">
                <el-form-item :error="errors.role_id?.[0]" required>
                  <template #label>
                      <span>Rol </span>
                      <a @click.prevent="dialogRole = true" class="text-primary text-hover-info cursor-pointer">[+Nuevo]</a>
                  </template>
                  <el-select  v-model="form.role_id" placeholder="Seleccione un Rol" filterable clearable>
                      <el-option
                          v-for="item in optionsRoles"
                          :key="item.id"
                          :label="item.name"
                          :value="item.id"
                      >
                          {{ item.name }}
                      </el-option>
                  </el-select>
                </el-form-item>
            </el-col>

            <el-col :lg="12">
                <el-form-item label="Correo Electrónico" :error="errors.email?.[0]" required>
                    <el-input type="email" v-model="form.email" />
                </el-form-item>
            </el-col>

            <el-col :lg="12">
                <el-form-item label="Contraseña" :error="errors.password?.[0]" :required="!dataForm">
                    <el-input type="password" v-model="form.password" show-password/>
                    <el-text v-if="dataForm" size="small" class="text-muted">* La contraseña se actualiza al llenar el campo.</el-text>
                </el-form-item>
            </el-col>
        </el-row>

        <template #footer>
            <div class="dialog-footer">
                <el-button type="primary" native-type="submit" :loading="loading">{{props.dataForm ? 'Actualizar' : 'Registrar'}}</el-button>
                <el-button type="danger" @click="dialogVisible = false">Cancelar</el-button>
            </div>
        </template>
    </DialogForm>
  </el-form>

  <FormRole v-model="dialogRole" @saved="savedRole"/>
</template>



