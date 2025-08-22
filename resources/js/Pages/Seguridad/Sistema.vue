<script setup>
    import { ref } from 'vue';
    import FormSistema from './FormSistema.vue';
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
    const ajaxUrl = route('datatable.sistemas');
    const sistemaId = ref(null);

    // MODAL
    const dialogVisible = ref(false);

    const showModal = () => {
        dialogVisible.value = true;
        sistemaId.value = null;
    };

    //Filtros
    const formInline = ref({
        fecha_at: '',
        f_updated: ''
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
    const handleSaved = (sistema) => {
        // Para recargar formulario desde otra vista
        // console.log('Sistema creado:', sistema);
        sistemaId.value = null;
        reloadTable();
    };

    // FUNCIONES DE ACCIONES (BOTONES)
    const handleEdit = async (id) => {
        const { data } = await axios.get(route('seguridad.sistemas.edit', id));
        sistemaId.value = data;
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
                const response = await axios.delete(route('seguridad.sistemas.destroy', id));
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

    // FUNCIONES EXPUESTAS DEL BASEDATATABLE
    const methods = {
        handleEdit,
        handleDelete
    };

    const { handleDynamicAction } = useDynamicAction(methods);

    function handleActionGrilla(nombre) {
        const funciones = {
            handleNew: showModal,
        }

        if (funciones[nombre]) {
            funciones[nombre]()
        } else {
            console.warn(`No se encontró la función '${nombre}'`);
        }
    }
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
                        <el-col :lg="6">
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
                        <el-col :lg="6">
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
                        <el-col :lg="24" class="d-flex justify-content-center">
                            <el-button icon="Search" type="primary" @click="reloadTable">Buscar</el-button>
                            <el-button icon="Delete" type="info" @click="limpiarFiltros">Limpiar</el-button>
                        </el-col>
                    </el-row>
                </el-form>
            </template>

            <template #column-2="props">
                <div class="d-flex">
                    <div>
                        <el-icon :size="20">
                            <component :is="props.cellData" />
                        </el-icon>
                    </div>
                    <div class="ms-2">
                        {{ props.cellData}}
                    </div>
                </div>
            </template>
        </BaseDataTable>

        <FormSistema v-model="dialogVisible" :sistema="sistemaId" @saved="handleSaved"/>
    </App>
</template>
