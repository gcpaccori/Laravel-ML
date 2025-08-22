<script setup>
    import { Link } from '@inertiajs/vue3';
    import { onMounted, nextTick } from 'vue';

    onMounted(() => {
        nextTick(() => {
            KTComponents.init()
        })
    })
</script>
<template>
    <div class="d-flex flex-column flex-root" id="kt_app_root">
        <div class="d-flex flex-column flex-lg-row flex-column-fluid">
            <div class="d-flex flex-column flex-lg-row-fluid w-lg-50 p-10 order-2 order-lg-1">
                <slot />
            </div>
            <div class="d-flex flex-lg-row-fluid w-lg-50 bgi-size-cover bgi-position-center order-1 order-lg-2" style="background-image: url(assets/media/misc/auth-bg.png)">
                <div class="d-flex flex-column flex-center py-7 py-lg-15 px-5 px-md-15 w-100">
                    <Link :href="route('login')" class="mb-0 mb-lg-12">
                        <img alt="Logo" src="assets/media/logos/logo_sisma.png" class="h-60px h-lg-150px" />
                    </Link>
                    <img class="d-none d-lg-block mx-auto w-275px w-md-50 w-xl-400px mb-10 mb-lg-20" src="assets/media/misc/auth-screens.png" alt="" />
                    <h1 class="d-none d-lg-block text-white fw-bolder text-center mb-7">Sistema de Monitoreo y Automatización para Piscigranjas</h1>
                </div>
            </div>
        </div>
    </div>
</template>
