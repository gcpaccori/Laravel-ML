<script setup>
// Acceso directo a las alarmas de modelo, al lado de la campana de alertas.
//
// No abre un desplegable propio a proposito: seria una segunda lista que
// mantener y que compite con la de al lado. Aqui solo hace falta saber si hay
// algo pendiente y poder llegar a la pantalla que ya lo explica todo.
import { onBeforeUnmount, onMounted, ref } from "vue";

const pendientes = ref(0);
let temporizador = null;

const consultar = async () => {
    try {
        const { data } = await axios.get(route("monitoreo.alarmasmodelos.pendientes"));
        pendientes.value = Number(data?.pendientes ?? 0);
    } catch {
        // Si falla, el acceso sigue sirviendo: solo se queda sin contador.
    }
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
            role="button"
            tabindex="0"
            title="Alarmas de modelos"
            @click="abrir"
            @keyup.enter="abrir"
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
    </div>
</template>