<script setup>
    import { onMounted, onBeforeUnmount, ref } from "vue";

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
    });

    const tableInstance = ref(null);
    const ajaxUrl = route('datatable.historialaguas');

    // Inicializar Tabla
    const handleTableReady = (dt) => {
        tableInstance.value = dt;
    };

    const reloadTable = () => {
        tableInstance.value.ajax.reload(null, true) // Recargar y regresa a la primera pagina;
    };


    onMounted(() => {
        // Para canal público
        window.Echo.channel('parametros-agua')
        .listen('.parametro.actualizado', (data) => {
            console.log(data.message);
            reloadTable();
        });
    });

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="row">
            <div class="col-lg-12 mb-5">
                <div class="card card-flush overflow-hidden h-xl-100">
                    <div class="card-header py-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-dark">Historial de registros de parámetros del agua</span>
                            <span class="text-gray-400 mt-1 fw-semibold fs-6">Gráfico de barras y Tabla detallada</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex">
                            <div id="chart-parametros-agua" style="width: 100%; height: 300px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-12">
                <BaseDataTable
                  :ajax-url="ajaxUrl"
                  :columns="columns"
                  @tableReady="handleTableReady"
                ></BaseDataTable>
            </div>
        </div>
    </App>
</template>
