<script setup>
    import { onMounted, onBeforeUnmount, ref } from "vue";
    import { ElMessage, ElMessageBox } from 'element-plus';
    import { useDynamicAction } from '@/Composables/useDynamicAction';
    import BiometriaForm from "../Form/BiometriaForm.vue";

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
    const ajaxUrl = route('datatable.biometrias');
    const dataForm = ref(null);

    // MODAL
    const dialogVisible = ref(false);

    const showModal = () => {
        dialogVisible.value = true;
        dataForm.value = null;
    };

    // Inicializar Tabla
    const handleTableReady = (dt) => {
        tableInstance.value = dt;
    };

    const reloadTable = () => {
        tableInstance.value.ajax.reload(null, true) // Recargar y regresa a la primera pagina;
    };

    // RECUPERAR DATOS GUARDADOS
    const handleSaved = ( res ) => {
        // Para recargar formulario desde otra vista
        reloadTable();
    };

    // FUNCIONES DE ACCIONES (BOTONES)
    const handleEdit = async (id) => {
        const { data } = await axios.get(route('biometrias.edit', id));
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
            const response = await axios.delete(route('biometrias.destroy', id));
            ElMessage({
                message: response.data.message,
                type: response.data.success ? 'success' : 'error',
            });
            reloadTable();
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



    onMounted(() => {

    });

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <template #btnCreate>
            <template v-for="(action, index) in accionesGrilla" :key="index">
                <el-button
                    :icon="action.icon"
                    :type="action.type"
                    class="mr-2"
                    size="small"
                    @click="handleActionGrilla(action.name_funcion)"
                >
                    {{ action.action }}
                </el-button>
            </template>
        </template>

        <BaseDataTable
          :ajax-url="ajaxUrl"
          :columns="columns"
          @tableReady="handleTableReady"
          @action="handleDynamicAction"
        ></BaseDataTable>

        <BiometriaForm v-model="dialogVisible" :dataForm="dataForm" @saved="handleSaved"/>
    </App>
</template>
