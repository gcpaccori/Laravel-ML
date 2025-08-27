<script setup>
    import axios from 'axios'
    import { onMounted, ref, watch } from 'vue';
    import { ElMessage } from 'element-plus'
    import useSubmitForm from '@/Composables/useSubmitForm';

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
    const treeRef = ref();
    const defaultProps = {
        children: 'children',
        label: 'label',
        disabled: 'disabled',
    }
    const permisosOptions = ref([]);
    const checkedKeys = ref([]);
    const form = ref({
        name : '',
        active : true
    });

    const emit = defineEmits(['update:modelValue', 'saved']);

    const submitFormulario = () => {
        const checkedKeys = treeRef.value.getCheckedKeys(true); // true: get leaf and non-leaf
        const checkedNodes = treeRef.value.getCheckedNodes(true); // true: include partially selected

        const permisosAcciones = checkedKeys
        .filter(id => id.startsWith('permiso_'))
        .map(id => id.replace('permiso_', ''));

        const permisosModulos = checkedKeys
        .filter(id => id.startsWith('modulo_'))
        .map(id => id.replace('modulo_', ''))
        .filter(Boolean); // elimina nulos

        // También agregamos módulos base desde permisosAcciones
        const modulosDesdeAcciones = permisosAcciones.map(p => p.split('.')[0]);

        // Unimos y eliminamos duplicados
        const permisos = [...new Set([
            ...permisosAcciones,
            ...modulosDesdeAcciones,
            ...permisosModulos
        ])];

        const routeName = props.dataForm ? 'seguridad.roles.update' : 'seguridad.roles.store';
        submitForm({
            url: props.dataForm
                ? route(routeName, props.dataForm.id)
                : route(routeName),
            data: {
                ...form.value,
                permisos: permisos
            },
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
        form.value.active = true;
        treeRef.value?.setCheckedKeys([]);
    };

    const cargarArbolPermisos = async () => {
        try {
            const response = await axios.get(route('seguridad.permisos.arbol'));
            permisosOptions.value = response.data;
        } catch (error) {
            ElMessage.error('Error al cargar estructura de permisos');
        }
    };

    // Sincronizar cambios del padre
    watch(() => props.modelValue, (val) => {
        dialogVisible.value = val;
        if (val) {
            resetForm();

            if (props.dataForm) {
                form.value = { ...props.dataForm };
            };

            // Marcar permisos
            // if (props.dataForm?.permisos) {
            //     checkedKeys.value = props.dataForm.permisos.map(p => `permiso_${p}`);
            // }

            if (props.dataForm?.permisos) {
                const permisos = props.dataForm.permisos;

                // Extraer IDs o claves de permisos
                const clavesPermisos = permisos.map(p => `permiso_${p}`);

                // Extraer módulos base desde los permisos (antes del punto)
                const clavesModulos = permisos
                    .map(p => p.split('.')[0])       // usuario.index -> usuario
                    .filter((v, i, a) => a.indexOf(v) === i) // únicos
                    .map(m => `modulo_${m}`);        // asumimos que así están los IDs de nodos de módulo

                checkedKeys.value = [...clavesPermisos, ...clavesModulos];
            }
        }
    });

    // Emitir cambios al padre
    watch(dialogVisible, (val) => {
        emit('update:modelValue', val);
    });

    onMounted(() => {
        cargarArbolPermisos();
    });
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top" class="w-100">
    <DialogForm v-model="dialogVisible" :title="props.dataForm ? 'Editar Rol' : 'Registrar Rol'" width="60%">
        <el-row :gutter="20">
            <el-col :lg="12">
                <el-row :gutter="20">
                    <el-col :lg="24">
                        <el-form-item label="Nombre Rol" :error="errors.name?.[0]" required>
                            <el-input v-model="form.name" />
                        </el-form-item>
                    </el-col>
                    <el-col :lg="24">
                        <el-form-item label="Activo" :error="errors.active?.[0]">
                            <el-switch class="ml-2" v-model="form.active" style="--el-switch-on-color: #13ce66; --el-switch-off-color: #ff4949"/>
                        </el-form-item>
                    </el-col>
                </el-row>
            </el-col>

            <el-col :lg="12">
                <el-form-item label="Sistemas / Módulos / Acciones" :error="errors.permisos?.[0]" required>
                    <el-tree
                        ref="treeRef"
                        style="max-width: 100%"
                        :data="permisosOptions"
                        :props="defaultProps"
                        node-key="id"
                        show-checkbox
                        :default-expanded-keys="checkedKeys"
                        :default-checked-keys="checkedKeys"
                    />
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
</template>



