<script setup>
import { onMounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import KpiCard from "@/Components/KpiCard.vue";
import { useDynamicAction } from '@/Composables/useDynamicAction';
import { ElMessage, ElMessageBox } from 'element-plus';

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
})

const alarmaStatistics = ref({});
const tableInstance = ref(null);
const ajaxUrl = route('alarmas.datatable');
const formInline = ref({
    search: '',
    estado: '',
    nivel : '',
    modulo: ''
})

const handleTableReady = (dt) => {
    tableInstance.value = dt;
};

const reloadTable = () => {
    tableInstance.value.ajax.reload(null, true)
};

const getStatistics = async() => {
    const {data} = await axios.get(route('alarmas.statistics'));
    alarmaStatistics.value = data;
}

const limpiarFiltros = () => {
    Object.keys(formInline.value).forEach(key => {
        formInline.value[key] = '';
    });
    reloadTable();
};

const handleResolver = async (id) => {
    await ElMessageBox.confirm(
        `¿Deseas marcar esta alarma como resuelta?`,
        'Resolver alarma',
        {
            confirmButtonText: 'Sí, resolver',
            cancelButtonText: 'Cancelar',
            type: 'success',
        }
    ).then( async () => {
        const response = await axios.patch(route('alarmas.resolver', id));
        console.log(response);

        ElMessage({
            message: response.data.message,
            type: response.data.success ? 'success' : 'error',
        });
        getStatistics();
        reloadTable();
    }).catch( (e) => {
        console.log(e);
    } );
};

const handleShow = (id) => {
    router.visit(route('monitoreo.alarmas.show', id));
};

const methods = {
    handleResolver,
    handleShow
};

const { handleDynamicAction } = useDynamicAction(methods);

onMounted( async() => {
    await getStatistics();

    window.Echo.channel("alertas.notificaciones").listen(
        ".alarma.generada",
        () => {
            getStatistics();
            reloadTable();
        },
    );

});

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="container-fluid">
            <div class="row g-5 mb-8">
                <div class="col-lg-3">
                    <KpiCard
                        label="Total de alertas"
                        :valor="alarmaStatistics.estadisticas?.total"
                        icon="Bell"
                        color="#009EF7"
                    />
                </div>

                <div class="col-lg-3">
                    <KpiCard
                        label="Activas"
                        :valor="alarmaStatistics.estadisticas?.activas"
                        icon="Notification"
                        color="#FFC700"
                    />
                </div>

                <div class="col-lg-3">
                    <KpiCard
                        label="Resueltas"
                        :valor="alarmaStatistics.estadisticas?.resueltas"
                        icon="Check"
                        color="#50CD89"
                    />
                </div>

                <div class="col-lg-3">
                    <KpiCard
                        label="Críticas pendientes"
                        :valor="alarmaStatistics.estadisticas?.criticas"
                        icon="CircleClose"
                        color="#F1416C"
                    />
                </div>
            </div>

            <BaseDataTable
                :ajax-url="ajaxUrl"
                :columns="columns"
                @tableReady="handleTableReady"
                @action="handleDynamicAction"
                :filters="formInline"
            >
                <template #filters>
                    <el-form :model="formInline" label-position="top" class="w-100">
                        <div class="row">
                            <div class="col-lg-4">
                                <label class="form-label fw-semibold">Buscar</label>
                                <el-input
                                    v-model="formInline.search"
                                    placeholder="Título, mensaje, parámetro..."
                                    clearable
                                    size="small"
                                />
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label fw-semibold">Estado</label>
                                <el-select
                                    v-model="formInline.estado"
                                    placeholder="Todos"
                                    clearable
                                    class="w-100"
                                    size="small"
                                >
                                    <el-option
                                        label="Activa"
                                        value="activa"
                                    />
                                    <el-option
                                        label="Resuelta"
                                        value="resuelta"
                                    />
                                </el-select>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label fw-semibold">Nivel</label>
                                <el-select
                                    v-model="formInline.nivel"
                                    placeholder="Todos"
                                    clearable
                                    class="w-100"
                                    size="small"
                                >
                                    <el-option
                                        label="Normal"
                                        value="normal"
                                    />
                                    <el-option
                                        label="Advertencia"
                                        value="advertencia"
                                    />
                                    <el-option
                                        label="Crítico"
                                        value="critico"
                                    />
                                    <el-option
                                        label="Emergencia"
                                        value="emergencia"
                                    />
                                </el-select>
                            </div>
                            <div class="col-lg-2">
                                <label class="form-label fw-semibold">Módulo</label>
                                <el-select
                                    v-model="formInline.modulo"
                                    placeholder="Todos"
                                    clearable
                                    class="w-100"
                                    size="small"
                                >
                                    <el-option
                                        label="Calidad de agua"
                                        value="calidad_agua"
                                    />
                                    <el-option
                                        label="Calidad ambiente"
                                        value="calidad_ambiente"
                                    />
                                    <el-option
                                        label="IoT / Sensores"
                                        value="iot_sensores"
                                    />
                                    <el-option
                                        label="Equipos"
                                        value="equipos"
                                    />
                                    <el-option
                                        label="Insumos"
                                        value="insumos"
                                    />
                                    <el-option
                                        label="Producción"
                                        value="produccion"
                                    />
                                    <el-option
                                        label="Inteligencia"
                                        value="inteligencia"
                                    />
                                </el-select>
                            </div>
                            <div class="col-lg-2 d-flex align-items-end">
                                <el-button
                                    icon="Delete"
                                    class="w-100"
                                    type="info"
                                    size="small"
                                    @click="limpiarFiltros"
                                >
                                    Limpiar filtros
                                </el-button>

                            </div>

                        </div>
                    </el-form>
                </template>
            </BaseDataTable>
        </div>
    </App>
</template>
