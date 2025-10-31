<script setup>
    import { Link } from "@inertiajs/vue3";
    import { onMounted, ref } from "vue";


    const props = defineProps({
        title: String,
        toolbar: {
            type: Array,
            required: false
        }
    });


    onMounted(() => {

    });

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="row">
            <div class="col-xxl-12">
                <div class="card card-flush h-md-100">
                    <div class="card-body d-flex flex-column justify-content-between mt-9 bgi-no-repeat bgi-size-cover bgi-position-x-center pb-0" style="background-position: 100% 50%; background-image:url('assets/media/stock/900x600/42.png')">
                        <div class="mb-10">
                            <div class="fs-2hx fw-bold text-gray-800 text-center mb-13">
                            <span class="me-2">Bienvenido al Sistema de Monitoreo y Automatización para Piscigranjas
                            <br />
                            <span class="position-relative d-inline-block text-danger">
                                <a href="#" class="text-danger opacity-75-hover">SISMA PISCIS</a>
                                <span class="position-absolute opacity-15 bottom-0 start-0 border-4 border-danger border-bottom w-100"></span>
                            </span></span>Perú</div>
                            <div class="text-center">
                                <Link :href="route('monitoreo.historialaguas.index')" class="btn btn-dark fw-bold">Monitorear Parámetros</Link>
                            </div>
                        </div>
                        <img class="mx-auto h-150px h-lg-200px theme-light-show" src="/assets/media/illustrations/misc/upgrade.svg" alt="" />
                        <img class="mx-auto h-150px h-lg-200px theme-dark-show" src="/assets/media/illustrations/misc/upgrade-dark.svg" alt="" />
                    </div>
                </div>
            </div>
        </div>
    </App>
</template>
