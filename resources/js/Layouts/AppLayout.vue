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

    <!--begin::App-->
    <div class="d-flex flex-column flex-root app-root" id="kt_app_root">
        <!--begin::Page-->
        <div class="app-page flex-column flex-column-fluid" id="kt_app_page">

            <Header />

            <!--begin::Wrapper-->
            <div class="app-wrapper flex-column flex-row-fluid" id="kt_app_wrapper">

                <Sidebar />

                <!--begin::Main-->
                <div class="app-main flex-column flex-row-fluid" id="kt_app_main">
                    <!--begin::Content wrapper-->
                    <div class="d-flex flex-column flex-column-fluid">

                        <!--begin::Toolbar-->
                        <div id="kt_app_toolbar" class="app-toolbar py-3 py-lg-6">
                            <!--begin::Toolbar container-->
                            <div id="kt_app_toolbar_container" class="app-container container-fluid d-flex flex-stack">

                                <Toolbar :title="title" :toolbar="toolbar" />

                                <!--begin::Actions-->
                                <div class="d-flex align-items-center gap-2 gap-lg-3">
                                    <!--begin::Primary button-->
                                    <slot name="btnCreate" />
                                    <!--end::Primary button-->
                                </div>
                                <!--end::Actions-->

                            </div>
                            <!--end::Toolbar container-->
                        </div>
                        <!--end::Toolbar-->

                        <!--begin::Content-->
                        <div id="kt_app_content" class="app-content flex-column-fluid">
                            <!--begin::Content container container-xxl-->
                            <div id="kt_app_content_container" class="app-container container-fluid">
                                <slot/>
                            </div>
                            <!--end::Content container-->
                        </div>
                        <!--end::Content-->
                    </div>
                    <!--end::Content wrapper-->
                    <!--begin::Footer-->
                    <Footer />
                    <!--end::Footer-->
                </div>
                <!--end:::Main-->
            </div>
            <!--end::Wrapper-->
        </div>
        <!--end::Page-->
    </div>
    <!--end::App-->
</template>
