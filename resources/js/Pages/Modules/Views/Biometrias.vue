<script setup>
    import { router } from "@inertiajs/vue3";
    import { onMounted, onBeforeUnmount, ref } from "vue";
    import { ElMessage, ElMessageBox } from 'element-plus';
    import { useDynamicAction } from '@/Composables/useDynamicAction';

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

    const showModal = () => {
        router.visit(route('biometrias.create'));
    };

    // Inicializar Tabla
    const handleTableReady = (dt) => {
        tableInstance.value = dt;
    };

    const reloadTable = () => {
        tableInstance.value.ajax.reload(null, true) // Recargar y regresa a la primera pagina;
    };

    // FUNCIONES DE ACCIONES (BOTONES)
    const handleEdit = (id) => {
        router.visit(route('biometrias.edit', id));
    };

    const handleShow = (id) => {
        router.visit(route('biometrias.show', id));
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

    const handlePdf = (id) => {
        const url = route('biometrias.pdf', id);
        window.open(url, '_blank');
    };

    // FUNCIONES EXPUESTAS DEL BASEDATATABLE
    const methods = {
        handleEdit,
        handleDelete,
        handlePdf,
        handleShow
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
    </App>
</template>
