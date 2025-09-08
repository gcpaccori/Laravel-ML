<script setup>
    const props = defineProps({
        title: String,
        toolbar: {
            type: Array,
            required: false
        },
        menssage: {
            type: String,
            required: false,
            default: 'Error Desconocido'
        },
        redirect: {
            type: Object,
            default: {'route' : 'dashboard', 'text' : 'Regresar a inicio'}
        }
    });
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="d-flex flex-column flex-center text-center p-10">
            <!--begin::Wrapper-->
            <div class="card card-flush w-lg-650px py-5">
                <div class="card-body py-15 py-lg-20">
                    <!--begin::Title-->
                    <h1 class="fw-bolder fs-2hx text-gray-900 mb-4">Oops!</h1>
                    <!--end::Title-->
                    <!--begin::Text-->
                    <div class="fw-semibold fs-6 text-gray-500 mb-7">{{menssage}}</div>
                    <!--end::Text-->
                    <!--begin::Illustration-->
                    <div class="mb-3">
                        <img src="/assets/media/auth/404-error.png" class="mw-100 mh-300px theme-light-show" alt="" />
                        <img src="/assets/media/auth/404-error-dark.png" class="mw-100 mh-300px theme-dark-show" alt="" />
                    </div>
                    <!--end::Illustration-->
                    <!--begin::Link-->
                    <div class="mb-0">
                        <a :href="route(redirect.route)" class="btn btn-sm btn-primary">{{ redirect.text }}</a>
                    </div>
                    <!--end::Link-->
                </div>
            </div>
            <!--end::Wrapper-->
        </div>
    </App>
</template>
