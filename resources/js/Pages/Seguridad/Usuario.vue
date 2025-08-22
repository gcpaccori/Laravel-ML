<script setup>
    import { onMounted, ref, watch } from 'vue';
    import FormUser from './FormUser.vue';
    import { ElMessage, ElMessageBox } from 'element-plus';
    import { useDynamicAction } from '@/Composables/useDynamicAction'

    const props = defineProps({
        title: String,
        toolbar: {
            type: Array,
            required: false
        },
        columns: {
            type: Object,
            required: false
        },
        accionesGrilla: {
            type: Array,
            required: false,
            default: []
        }
    });

    const tableInstance = ref(null);
    const ajaxUrl = route('datatable.usuarios');
    const dataForm = ref(null);
    const optionsRoles = ref([]);
    // MODAL
    const dialogVisible = ref(false);

    const showModal = () => {
        dialogVisible.value = true;
        dataForm.value = null;
    };

    const exportarExcel = () => {
        console.log('Exportar a Excel');
    };

    //Filtros
    const formInline = ref({
        fecha_at: '',
        f_updated: '',
        role_id_f: ''
    })

    const limpiarFiltros = () => {
        Object.keys(formInline.value).forEach(key => {
            formInline.value[key] = '';
        });
        reloadTable();
    };

    // Inicializar Tabla
    const handleTableReady = (dt) => {
        tableInstance.value = dt;
    };

    const reloadTable = () => {
        tableInstance.value.ajax.reload(null, true) // Recargar y regresa a la primera pagina;
    };

    // RECUPERAR DATOS GUARDADOS
    const handleSaved = (registro) => {
        dataForm.value = null;
        reloadTable();
    };

    // FUNCIONES DE ACCIONES (BOTONES)
    const handleEdit = async (id) => {
        const { data } = await axios.get(route('seguridad.usuarios.edit', id));
        dataForm.value = data;
        dialogVisible.value = true;
    };

    const handleDelete = async(id) => {
        ElMessageBox.confirm(
            '¿Estás seguro de que deseas eliminar este registro?',
            'Advertencia',
            {
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                type: 'warning',
                center: true,
            }
        ).then( async () => {
            try {
                const response = await axios.delete(route('seguridad.usuarios.destroy', id));
                ElMessage({
                    message: response.data.message,
                    type: 'success',
                });
            reloadTable();
            } catch (error) {
                ElMessage({
                    message: error.message+' : '+error.response.data.message,
                    type: 'error',
                });
            }
        }).catch( (e) => {
            console.log(e);
        } );
    };

    // ROLES FILTER
    const roleOptions = async () => {
        const response = await axios.get(route('seguridad.roles.options'));
        optionsRoles.value = response.data.data;
    }

    // FUNCIONES EXPUESTAS DEL BASEDATATABLE
    const methods = {
        handleEdit,
        handleDelete
    };
    const { handleDynamicAction } = useDynamicAction(methods);

    function handleActionGrilla(nombre) {
        const funciones = {
            handleNew: showModal,
            // más funciones aquí...
        }

        if (funciones[nombre]) {
            funciones[nombre]()
        } else {
            console.warn(`No se encontró la función '${nombre}'`);
        }
    }

    onMounted(() => {
        roleOptions();
    })

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <template #btnCreate>
            <template v-for="(action, index) in accionesGrilla" :key="index">
                <el-button
                    :icon="action.icon"
                    :type="action.type"
                    class="mr-2"
                    @click="handleActionGrilla(action.name_funcion)"
                >
                    {{ action.action }}
                </el-button>
            </template>
        </template>
        <BaseDataTable
          :ajax-url="ajaxUrl"
          :columns="columns.original"
          :filters="formInline"
          @tableReady="handleTableReady"
          @action="handleDynamicAction"
        >
            <template #filters>
                <el-form :model="formInline" label-position="top" class="w-100">
                    <el-row :gutter="20">
                        <el-col :md="8" :lg="4">
                            <el-form-item label="Fecha Creación">
                                <el-date-picker
                                    v-model="formInline.fecha_at"
                                    type="date"
                                    format="DD/MM/YYYY"
                                    value-format="YYYY-MM-DD"
                                    placeholder="DD/MM/YYYY"
                                    clearable
                                    style="width: 100%;"
                                />
                            </el-form-item>
                        </el-col>
                        <el-col :md="8" :lg="4">
                            <el-form-item label="Fecha Actualización">
                                <el-date-picker
                                    v-model="formInline.f_updated"
                                    type="date"
                                    format="DD/MM/YYYY"
                                    value-format="YYYY-MM-DD"
                                    placeholder="DD/MM/YYYY"
                                    clearable
                                    style="width: 100%;"
                                />
                            </el-form-item>
                        </el-col>
                        <el-col :md="6" :lg="6">
                            <el-form-item label="Rol">
                            <el-select  v-model="formInline.role_id_f" placeholder="Seleccione un Rol" filterable clearable>
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
                        <el-col :lg="24" class="d-flex justify-content-center">
                            <el-button icon="Search" type="primary" @click="reloadTable">Buscar</el-button>
                            <el-button icon="Delete" type="info" @click="limpiarFiltros">Limpiar</el-button>
                        </el-col>
                    </el-row>
                </el-form>
            </template>
        </BaseDataTable>
        <FormUser v-model="dialogVisible" :dataForm="dataForm" @saved="handleSaved"/>
    </App>
</template>
