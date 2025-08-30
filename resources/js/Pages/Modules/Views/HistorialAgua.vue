<script setup>
    import { onMounted, onBeforeUnmount, ref, computed } from "vue";
    import ChartFisheye from "@/Components/ChartFisheye.vue";

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
    const piscigranjas = ref(null);
    const piscinasList = ref([]);
    const form = ref({
        piscigranja_id : 'T',
        piscina_id: 'T',
        tipo: "dia", // valores: dia | mes | anio
        fecha: new Date().toISOString().substring(0, 10), // YYYY-MM-DD
        mes: new Date().toISOString().substring(0, 7), // YYYY-MM
        anio: new Date().getFullYear(), // YYYY
    });

    const labels = ref([]);
    const tooltips = ref([]);
    const series = ref([]);

    // Inicializar Tabla
    const handleTableReady = (dt) => {
        tableInstance.value = dt;
    };

    const reloadTable = () => {
        tableInstance.value.ajax.reload(null, true) // Recargar y regresa a la primera pagina;
    };

    const piscigranjasOptions = async() => {
        const {data} = await axios.get(route('piscigranjas.options'));
        piscigranjas.value = data.data;
    }

    const changePiscigranjas = () => {
        form.value.piscina_id = 'T';
        loadParametros();
    }

    const loadParametros = async() => {
        const { data } = await axios.get(route("chart.historialaguas"), {
            params: form.value,
        });
        labels.value = data.labels;
        tooltips.value = data.tooltips;
        series.value = data.series;
        // console.log(data);

        if (form.value.piscigranja_id == 'T') {
            piscinasList.value = [];
        }else{
            await piscinasOptions();
        }
    }

    const piscinasOptions = async() => {
        const {data} = await axios.get(route('piscigranjas.piscinas', form.value.piscigranja_id));
        piscinasList.value = data;
    }

    onMounted( async() => {
        await piscigranjasOptions();
        await loadParametros();

        // Para canal público
        window.Echo.channel('parametros-agua')
        .listen('.parametro.actualizado', (data) => {
            console.log(data.message);
            reloadTable();
            loadParametros();
        });
    });

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="row g-5 g-xl-8">
            <div class="col-xl-12">
                <div class="card bg-body hoverable card-xl-stretch mb-xl-1">
                    <div class="card-body">
                        <el-form :model="form" label-position="top" class="w-100">
                            <div class="row">

                                <div class="col-lg-4">
                                    <el-form-item label="Piscigranjas">
                                        <el-select
                                            filterable
                                            v-model="form.piscigranja_id"
                                            @change="changePiscigranjas"
                                        >
                                            <el-option label="Todos" value="T" />
                                            <el-option
                                                v-for="item in piscigranjas"
                                                :key="item.id"
                                                :label="item.nombre"
                                                :value="item.id"
                                            />
                                        </el-select>
                                    </el-form-item>
                                </div>

                                <div class="col-lg-4">
                                    <el-form-item label="Piscinas">
                                        <el-select
                                            filterable
                                            v-model="form.piscina_id"
                                            @change="loadParametros"
                                        >
                                            <el-option label="Todos" value="T" />
                                            <el-option
                                                v-for="item in piscinasList"
                                                :key="item.id"
                                                :label="item.nombre"
                                                :value="item.id"
                                            />
                                        </el-select>
                                    </el-form-item>
                                </div>

                            </div>
                        </el-form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-12 mb-5">
                <div class="card card-flush overflow-hidden h-xl-100">
                    <div class="card-header py-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-dark">Historial de Registros de Parámetros del Agua</span>
                            <span class="text-gray-400 mt-1 fw-semibold fs-6"></span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <ChartFisheye :labels="labels" :series="series" :tooltips="tooltips" />
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
