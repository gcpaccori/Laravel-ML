<script setup>
    import { onMounted, computed, ref } from "vue";
    import GaugeChart from "@/Components/GaugeChart.vue";
    import PiscigranjasMap from "@/Components/PiscigranjasMap.vue";
    import ServerTime from "@/Components/ServerTime.vue";
    import KpiCard from "@/Components/KpiCard.vue";

    const props = defineProps({
        title: String,
        toolbar: {
            type: Array,
            required: false
        },
        bands: {
            type: Object,
            default: {}
        }
    });

    const form = ref({
        piscigranja_id : 'T',
        piscina_id: 'T'
    });

    const parametros_agua = ref({});
    const piscigranjas = ref(null);
    const piscigranjasMapList = ref([]);

    const piscinasList = ref(null);

    const gauges = computed(() =>
        Object.entries(props.bands).map(([key, config]) => ({
            key,
            value: parametros_agua.value[key],
            ...config,
        }))
    );

    const piscigranjasOptions = async() => {
        const {data} = await axios.get(route('piscigranjas.options'));
        piscigranjas.value = data.data;
    }

    const changePiscigranjas = () => {
        form.value.piscina_id = 'T';
        loadParametros();
    }

    const loadParametros = async() => {
        const {data} = await axios.get(route('calidadaguas.parametros', form.value));

        piscigranjasMapList.value = data.piscigranjas;
        parametros_agua.value = data.parametros;

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

    // let intervalId = null;

    onMounted( async() => {
        await piscigranjasOptions();
        await loadParametros();

        // Para canal público
        window.Echo.channel('parametros-agua')
        .listen('.parametro.actualizado', (data) => {
            console.log(data.message);
            loadParametros();
        });
    });

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <!-- FILTROS -->
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

                                <div class="col-lg-4 d-flex justify-content-end">
                                    <ServerTime/>
                                </div>

                            </div>
                        </el-form>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-5">
            <div class="col-lg-4">
                <KpiCard
                    label="Piscigranja"
                    :valor="parametros_agua.piscigranja?.nombre"
                    icon="OfficeBuilding"
                    color="#F1416C"
                />
            </div>

            <div class="col-lg-4">
                <KpiCard
                    label="Piscina"
                    :valor="parametros_agua.piscina?.nombre"
                    icon="Grid"
                    color="#67C23A"
                />
            </div>

            <div class="col-lg-4">
                <KpiCard
                    label="Último registro"
                    :valor="parametros_agua?.fecha_registro"
                    icon="Calendar"
                    color="#009EF7"
                />
            </div>
        </div>

        <div class="row mt-5 d-flex align-items-stretch">
            <div class="col-lg-6 d-flex">
                <el-card shadow="hover" class="w-100">
                    <template #header>
                        <span class="fw-bold text-uppercase">Geolocalización de Piscigranjas</span>
                    </template>
                    <PiscigranjasMap :piscigranjas="piscigranjasMapList" />
                </el-card>
            </div>

            <div class="col-lg-6 d-flex">
                <el-card shadow="hover" class="w-100">
                    <template #header>
                        <span class="fw-bold text-uppercase">
                            Monitoreo en tiempo real
                        </span>
                    </template>

                    <div class="row">
                        <div
                            v-for="gauge in gauges"
                            :key="gauge.key"
                            class="col-lg-6 mb-2"
                        >
                            <el-card
                                shadow="hover"
                                class="h-100"
                            >
                                <h5 class="text-center fw-bold">
                                    {{ gauge.label }}
                                </h5>

                                <GaugeChart
                                    :value="gauge.value"
                                    :min="gauge.min"
                                    :max="gauge.max"
                                    :bandsData="gauge.bands"
                                    :unit="gauge.unit"
                                />
                            </el-card>
                        </div>
                    </div>
                </el-card>
            </div>
        </div>
    </App>
</template>
