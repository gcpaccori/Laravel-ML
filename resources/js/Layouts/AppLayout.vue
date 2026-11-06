<script setup>
    import { Head, Link, router } from '@inertiajs/vue3';
    import { onMounted, nextTick } from 'vue';
    import Header from '@/Layouts/Partials/Header.vue';
    import Sidebar from '@/Layouts/Partials/Sidebar.vue';
    import Footer from './Partials/Footer.vue';
    import Toolbar from './Partials/Toolbar.vue';

    onMounted(() => {
        nextTick(() => {
            KTComponents.init();
            KTAppSidebar.init();
        })
    })

    defineProps({
        title: {
            type: String,
            required: false
        },
        toolbar: {
            type: Array,
            required: false
        }
    });

</script>

<template>
    <Head :title="title" />
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <div class="app-page flex-column flex-column-fluid" id="kt_app_page">
            <Header />
            <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">
                <Sidebar />
                <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                    <div class="d-flex flex-column flex-column-fluid">
                        <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
                            <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">
                                <Toolbar :title="title" :toolbar="toolbar" />
                                <div class="d-flex align-items-center gap-2 gap-lg-3">
                                    <slot name="btnCreate" />
                                </div>
                            </div>
                        </div>
                        <div id="kt_app_content" class="app-content flex-column-fluid">
                            <div id="kt_app_content_container" class="app-container container-fluid">
                                <slot/>
                            </div>
                        </div>
                    </div>
                    <Footer />
                </div>
            </div>
        </div>
    </div>
</template>
