<script setup>
import { ref, onMounted } from "vue";
import { router } from "@inertiajs/vue3";
import axios from "axios";

const alarmas = ref([]);
const alarmasActivas = ref(0);

const fetchAlarmasDropdown = async () => {
    try {
        const response = await axios.get(route("alarmas.dropdown"));
        alarmas.value = response.data.alarmas;
        alarmasActivas.value = response.data.alarmas_activas;
    } catch (error) {
        console.error("Error al cargar las alarmas:", error);
    }
};

onMounted(() => {
    fetchAlarmasDropdown();
    window.Echo.channel("alertas.notificaciones").listen(
        ".alarma.generada",
        () => {
            fetchAlarmasDropdown();
        },
    );
});

const nivelConfig = {
    normal: {
        bg: "bg-light-success",
        text: "text-success",
        icon: "ki-check-circle",
    },
    advertencia: {
        bg: "bg-light-warning",
        text: "text-warning",
        icon: "ki-information",
    },
    critico: {
        bg: "bg-light-danger",
        text: "text-danger",
        icon: "ki-notification-status",
    },
    emergencia: {
        bg: "bg-light-danger",
        text: "text-danger",
        icon: "ki-cross-circle",
    },
};

const configNivel = (nivel) => {
    return nivelConfig[nivel] ?? nivelConfig.advertencia;
};

const verAlarma = (alarma) => {
    router.visit(route("monitoreo.alarmas.show", alarma.id));
};
</script>

<template>
    <div class="app-navbar-item ms-1 ms-md-4">
        <div
            class="btn btn-icon btn-custom btn-icon-muted btn-active-light btn-active-color-primary w-35px h-35px position-relative"
            data-kt-menu-trigger="{default: 'click', lg: 'hover'}"
            data-kt-menu-placement="bottom-end"
        >
            <i class="ki-duotone ki-notification-status fs-2 text-success">
                <span class="path1"></span>
                <span class="path2"></span>
                <span class="path3"></span>
            </i>

            <span
                v-if="alarmasActivas > 0"
                class="position-absolute top-0 start-100 translate-middle badge badge-circle badge-danger"
            >
                {{ alarmasActivas > 99 ? "99+" : alarmasActivas }}
            </span>
        </div>

        <div
            class="menu menu-sub menu-sub-dropdown menu-column w-350px w-lg-375px"
            data-kt-menu="true"
        >
            <div
                class="d-flex flex-column bgi-no-repeat rounded-top"
                style="
                    background-image: url(&quot;/assets/media/misc/menu-header-bg.jpg&quot;);
                "
            >
                <h3 class="text-white fw-semibold px-9 mt-10 mb-6">
                    Alertas
                    <span class="fs-8 opacity-75 ps-3">
                        {{ alarmasActivas }} activas
                    </span>
                </h3>
            </div>

            <div class="tab-content">
                <div class="tab-pane fade show active">
                    <div class="scroll-y mh-325px my-5 px-8">
                        <div
                            v-if="alarmas.length === 0"
                            class="text-center py-10 text-gray-500"
                        >
                            <i
                                class="ki-duotone ki-notification-status fs-3x mb-3"
                            >
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                            <div>No tienes alertas pendientes</div>
                        </div>

                        <div
                            v-for="alarma in alarmas"
                            :key="alarma.id"
                            class="d-flex flex-stack py-4 cursor-pointer"
                            @click="verAlarma(alarma)"
                        >
                            <div class="d-flex align-items-center">
                                <div class="symbol symbol-35px me-4">
                                    <span
                                        class="symbol-label"
                                        :class="configNivel(alarma.nivel).bg"
                                    >
                                        <i
                                            class="ki-duotone fs-2"
                                            :class="[
                                                configNivel(alarma.nivel).icon,
                                                configNivel(alarma.nivel).text,
                                            ]"
                                        >
                                            <span class="path1"></span>
                                            <span class="path2"></span>
                                        </i>
                                    </span>
                                </div>

                                <div class="mb-0 me-2">
                                    <div
                                        class="fs-6 text-gray-800 text-hover-primary fw-bold"
                                    >
                                        {{ alarma.titulo }}
                                    </div>
                                    <div class="text-gray-400 fs-7">
                                        <span v-if="alarma.piscina">
                                            {{ alarma.piscina }}
                                        </span>
                                        <span v-else>
                                            {{ alarma.piscigranja }}
                                        </span>
                                        <span class="mx-1">·</span>
                                        {{ alarma.created_at }}
                                    </div>
                                </div>
                            </div>

                            <span
                                class="badge badge-light fs-8 text-capitalize"
                                :class="configNivel(alarma.nivel).text"
                            >
                                {{ alarma.nivel }}
                            </span>
                        </div>
                    </div>

                    <div class="py-3 text-center border-top">
                        <button
                            type="button"
                            class="btn btn-color-gray-600 btn-active-color-primary"
                            @click="router.visit(route('monitoreo.alarmas.index'))"
                        >
                            Ver todas las alertas
                            <i class="ki-duotone ki-arrow-right fs-5">
                                <span class="path1"></span>
                                <span class="path2"></span>
                            </i>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
