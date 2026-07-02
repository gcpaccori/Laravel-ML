<script setup>
    import { onMounted, computed, ref } from "vue";
    import GaugeChart from "@/Components/GaugeChart.vue";
    import PiscigranjasMap from "@/Components/PiscigranjasMap.vue";
    import ServerTime from "@/Components/ServerTime.vue";

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
            <div class="col-xl-3">
                <a href="#" class="card bg-danger hoverable card-xl-stretch mb-xl-3">
                    <div class="card-body">
                        <div class="text-gray-100 fw-bold fs-3">{{ parametros_agua.piscigranja?.nombre ?? '-' }}</div>
                        <div class="fw-semibold text-gray-100">Piscigranja</div>
                    </div>
                </a>
            </div>
            <div class="col-xl-3">
                <a href="#" class="card bg-dark hoverable card-xl-stretch mb-xl-3">
                    <div class="card-body">
                        <div class="text-gray-100 fw-bold fs-3">{{ parametros_agua.piscina?.nombre ?? '-' }}</div>
                        <div class="fw-semibold text-gray-100">Piscina</div>
                    </div>
                </a>
            </div>
            <div class="col-xl-3">
                <a href="#" class="card bg-success hoverable card-xl-stretch mb-xl-3">
                    <div class="card-body">
                        <div class="text-white fw-bold fs-3">{{ parametros_agua?.fecha_medicion ?? '-' }}</div>
                        <div class="fw-semibold text-white">Última fecha de medición sensor</div>
                    </div>
                </a>
            </div>
            <div class="col-xl-3">
                <a href="#" class="card bg-info hoverable card-xl-stretch mb-5 mb-xl-3">
                    <div class="card-body">
                        <div class="text-white fw-bold fs-3">{{ parametros_agua?.fecha_registro ?? '-' }}</div>
                        <div class="fw-semibold text-white">Última fecha de registro sistema</div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-md-5 g-sm-5">
            <div class="col-lg-6 col-md-12">
                <div class="card card-flush overflow-hidden h-xl-100">
                    <div class="card-header py-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-dark fs-5">Geolocalización de Piscigranjas</span>
                            <span class="text-gray-400 mt-1 fw-semibold fs-7">Ubicación de las piscigranjas por departamentos</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <PiscigranjasMap :piscigranjas="piscigranjasMapList" />
                    </div>
                </div>
            </div>

            <div class="col-lg-6 col-md-12">
                <div class="card card-flush overflow-hidden h-xl-100">
                    <div class="card-header py-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-dark fs-5">Monitoreo en tiempo real</span>
                            <span class="text-gray-400 mt-1 fw-semibold fs-7">Monitoreo en tiempo real de la calidad del agua de las piscinas</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="row">
                            <div
                                v-for="gauge in gauges"
                                :key="gauge.key"
                                class="col-lg-6 mb-5"
                            >
                                <h5 class="text-center fw-bold mb-2">
                                    {{ gauge.label }}
                                </h5>

                                <GaugeChart
                                    :value="gauge.value"
                                    :min="gauge.min"
                                    :max="gauge.max"
                                    :bandsData="gauge.bands"
                                    :unit="gauge.unit"
                                />
                            </div>
                        </div>
                    </div>
                    <!-- <div class="card-body pt-0">
                        <div class="row mb-5">
                            <div class="col-lg-6">
                                <h5 class="text-center font-bold mb-2">Temperatura</h5>
                                <GaugeChart
                                    :value="parametros_agua.temperatura"
                                    :min="props.bands.bandsTemperatura.min"
                                    :max="props.bands.bandsTemperatura.max"
                                    :bandsData="props.bands.bandsTemperatura.bands"
                                    unit="°C"
                                />
                            </div>
                            <div class="col-lg-6">
                                <h5 class="text-center font-bold mb-2">Grado de Acidez</h5>
                                <GaugeChart
                                    :value="parametros_agua.ph"
                                    :min="props.bands.bandsPh.min"
                                    :max="props.bands.bandsPh.max"
                                    :bandsData="props.bands.bandsPh.bands"
                                    unit="pH"
                                />
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-lg-6">
                                <h5 class="text-center font-bold mb-2">Oxígeno Disuelto</h5>
                                <GaugeChart
                                    :value="parametros_agua.oxigeno"
                                    :min="props.bands.bandsOxigeno.min"
                                    :max="props.bands.bandsOxigeno.max"
                                    :bandsData="props.bands.bandsOxigeno.bands"
                                    unit="mg/L"
                                />
                            </div>
                            <div class="col-lg-6">
                                <h5 class="text-center font-bold mb-2">Ion de Nitrato</h5>
                                <GaugeChart
                                    :value="parametros_agua.nitrato"
                                    :min="props.bands.bandsNitrato.min"
                                    :max="props.bands.bandsNitrato.max"
                                    :bandsData="props.bands.bandsNitrato.bands"
                                    unit="mg/L"
                                />
                            </div>
                        </div>
                    </div> -->
                </div>
            </div>
        </div>
    </App>
</template>
