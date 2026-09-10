<script setup>
// Desplegable de alarmas de modelo, hermano del de alertas.
//
// Se separan por vigencia y no por estado. Una alarma que predijo algo para
// las 14:00 deja de ser actualidad a las 14:01 aunque nadie la haya cerrado:
// su ventana paso y lo unico que queda por hacer con ella es comprobar si
// acerto. Las vigentes van arriba y con color; las demas se quedan debajo,
// apagadas, porque siguen sirviendo para revisar pero ya no piden nada.
import { computed, onMounted, onBeforeUnmount, ref } from "vue";

const alarmas = ref([]);
const pendientes = ref(0);
let temporizador = null;

const consultar = async () => {
    try {
        const { data } = await axios.get(route("monitoreo.alarmasmodelos.pendientes"));
        alarmas.value = data?.alarmas ?? [];
        pendientes.value = Number(data?.pendientes ?? 0);
    } catch {
        // Sin datos el icono sigue llevando a la pantalla; solo pierde la lista.
    }
};

const vigentes = computed(() => alarmas.value.filter((a) => a.vigencia === "vigente"));
const pasadas = computed(() => alarmas.value.filter((a) => a.vigencia !== "vigente"));

const NIVEL = {
    normal: { bg: "bg-light-success", text: "text-success", icon: "ki-check-circle" },
    advertencia: { bg: "bg-light-warning", text: "text-warning", icon: "ki-information" },
    critico: { bg: "bg-light-danger", text: "text-danger", icon: "ki-notification-status" },
    emergencia: { bg: "bg-light-danger", text: "text-danger", icon: "ki-cross-circle" },
};
const nivelDe = (n) => NIVEL[n] ?? NIVEL.advertencia;

const MODELO = {
    WATER_QUALITY_INDEX_ICA: "La nota del agua",
    TILAPIA_GROWTH_TEMPERATURE: "Cuanto crecen al dia",
    SVM_OD_FORECAST_1H: "El oxigeno que viene",
    LIGHT_FEED_RESPONSE_CLASSIFIER_V1: "Luz para la proxima toma",
    PHOTOPERIOD_GREENHOUSE_V1: "Horas de luz util",
    TILAPIA_WEIGHT_LENGTH_ML: "Si pesan lo que deben",
};
const nombreDe = (c) => MODELO[c] ?? c ?? "Modelo";

const cuando = (iso) => {
    if (!iso) return "";
    const d = new Date(iso);
    const min = Math.round((Date.now() - d.getTime()) / 60000);
    if (min < 1) return "ahora";
    if (min < 60) return `hace ${min} min`;
    if (min < 1440) return `hace ${Math.round(min / 60)} h`;
    return `hace ${Math.round(min / 1440)} d`;
};

const abrir = () => window.location.assign(route("monitoreo.alarmasmodelos.index"));

onMounted(() => {
    consultar();
    temporizador = setInterval(consultar, 60000);
});
onBeforeUnmount(() => clearInterval(temporizador));
</script>

<template>
    <div class="app-navbar-item ms-1 ms-md-4">
        <div
            class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px position-relative"
            data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
            data-kt-menu-placement="bottom-end"
            title="Alarmas de modelos"
        >
            <i class="ki-duotone ki-abstract-26 fs-2 text-primary">
                <span class="path1"></span>
                <span class="path2"></span>
            </i>
            <span
                v-if="pendientes > 0"
                class="position-absolute top-0 start-100 translate-middle badge badge-circle badge-danger"
            >
                {{ pendientes > 99 ? "99+" : pendientes }}
            </span>
        </div>

        <div class="menu menu-sub menu-sub-dropdown menu-column w-350px w-lg-375px" data-kt-menu="true">
            <div
                class="d-flex flex-column bgi-no-repeat rounded-top"
                style="background-image: url(&quot;/assets/media/misc/menu-header-bg.jpg&quot;);"
            >
                <h3 class="text-white fw-semibold px-9 mt-10 mb-6">
                    Modelos
                    <span class="fs-8 opacity-75 ps-3">{{ pendientes }} vigentes</span>
                </h3>
            </div>

            <div class="scroll-y mh-325px my-5 px-8">
                <div v-if="!alarmas.length" class="text-center py-10 text-gray-500">
                    <i class="ki-duotone ki-abstract-26 fs-3x mb-3">
                        <span class="path1"></span><span class="path2"></span>
                    </i>
                    <div>Ningun modelo esta avisando</div>
                </div>

                <div
                    v-for="a in vigentes"
                    :key="a.id"
                    class="d-flex flex-stack py-4 cursor-pointer"
                    @click="abrir"
                >
                    <div class="d-flex align-items-center">
                        <div class="symbol symbol-35px me-4">
                            <span class="symbol-label" :class="nivelDe(a.nivel).bg">
                                <i class="ki-duotone fs-2" :class="[nivelDe(a.nivel).icon, nivelDe(a.nivel).text]">
                                    <span class="path1"></span><span class="path2"></span>
                                </i>
                            </span>
                        </div>
                        <div class="mb-0 me-2">
                            <span class="fs-6 text-gray-800 fw-bold">{{ nombreDe(a.model_code) }}</span>
                            <div class="text-gray-500 fs-7">{{ a.mensaje }}</div>
                        </div>
                    </div>
                    <span class="badge badge-light fs-8">{{ cuando(a.ocurrio_en) }}</span>
                </div>

                <template v-if="pasadas.length">
                    <div class="separator my-3"></div>
                    <div class="text-gray-500 fs-8 fw-semibold text-uppercase mb-2">
                        Ya pasadas ({{ pasadas.length }})
                    </div>
                    <div
                        v-for="a in pasadas"
                        :key="a.id"
                        class="d-flex flex-stack py-3 cursor-pointer opacity-50"
                        @click="abrir"
                    >
                        <div class="d-flex align-items-center">
                            <div class="symbol symbol-30px me-4">
                                <span class="symbol-label bg-light">
                                    <i class="ki-duotone ki-time fs-4 text-gray-500">
                                        <span class="path1"></span><span class="path2"></span>
                                    </i>
                                </span>
                            </div>
                            <div class="mb-0 me-2">
                                <span class="fs-7 text-gray-700 fw-semibold">{{ nombreDe(a.model_code) }}</span>
                                <div class="text-gray-500 fs-8">
                                    {{ a.vigencia === "resuelta" ? "resuelta" : "su plazo ya paso" }}
                                    <template v-if="a.predijo !== null && a.predijo !== undefined">
                                        &middot; predijo {{ Number(a.predijo).toFixed(2) }}
                                    </template>
                                </div>
                            </div>
                        </div>
                        <span class="badge badge-light fs-8">{{ cuando(a.ocurrio_en) }}</span>
                    </div>
                </template>
            </div>

            <div class="py-3 text-center border-top">
                <span class="btn btn-color-gray-600 btn-active-color-primary" @click="abrir">
                    Ver todas
                    <i class="ki-duotone ki-arrow-right fs-5"><span class="path1"></span><span class="path2"></span></i>
                </span>
            </div>
        </div>
    </div>
</template>
