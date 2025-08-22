<script setup>
    import { onMounted, ref } from 'vue';

    const props = defineProps({
        user: Object,
    });

    const tableInstance = ref(null);
    const columns = ref([]);
    const ajaxUrl = route('datatable.sessionlogs');

    //Filtros
    const formInline = ref({
        user_id_f: props.user.id,
    });

    // Inicializar Tabla
    const handleTableReady = (dt) => {
        tableInstance.value = dt;
    };

    const handleColumns = async () => {
        const { data } = await axios.get(route('column.sessionlogs'));
        columns.value = data;
    };

    onMounted(() => {
        handleColumns();
    })
</script>

<template>
    <div class="card mb-5 mt-0 mb-xl-5 shadow-sm">
        <div class="card-header collapsible cursor-pointer rotate" data-bs-toggle="collapse" data-bs-target="#session_logs">
            <h3 class="card-title align-items-start m-0 flex-column">
                <span class="fw-bold m-0">Historial de inicio de sesión</span>
                <span class="text-muted fs-7">
                    Administre y cierre sesión en sus sesiones activas en otros navegadores y dispositivos.
                </span>
            </h3>
            <div class="card-toolbar rotate-180">
                <i class="fa-solid fa-chevron-down"></i>
            </div>
        </div>
        <div id="session_logs" class="collapse show">
            <div class="card-body p-9">
                <BaseDataTable
                    v-if="columns.length"
                    :showCard="false"
                    :columns="columns"
                    :ajax-url="ajaxUrl"
                    :filters="formInline"
                    @tableReady="handleTableReady"
                >

                    <!-- COLUMNA DE UBICACION -->
                    <template #column-4="props">
                        <el-popover
                            v-if="props.cellData"
                            placement="right"
                            trigger="click"
                            popper-class="popover-auto-width"
                        >
                            <template #reference>
                                <el-button>
                                    <el-icon><View /></el-icon>
                                </el-button>
                            </template>
                            <template #default>
                                <ul class="list-unstyled">
                                    <li><span class="fw-bold">País:</span> {{props.cellData?.countryCode}} - {{ props.cellData?.countryName }}</li>
                                    <li><span class="fw-bold">Región:</span> {{ props.cellData?.regionName }}</li>
                                    <li><span class="fw-bold">Ciudad:</span> {{ props.cellData?.cityName }}</li>
                                    <li><span class="fw-bold">Zona Horaria:</span> {{ props.cellData?.timezone }}</li>
                                    <li><span class="fw-bold">Latitud</span> {{ props.cellData?.latitude }}</li>
                                    <li><span class="fw-bold">Longitud:</span> {{ props.cellData?.longitude }}</li>
                                </ul>
                            </template>
                        </el-popover>
                    </template>
                </BaseDataTable>
            </div>
        </div>
    </div>
</template>
