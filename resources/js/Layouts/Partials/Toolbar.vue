<script setup>
    import { Link } from '@inertiajs/vue3';

    defineProps({
        title: {
            type: String,
            required: false,
            default: 'Modulo',
        },
        toolbar: {
            type: Array,
            required: false,
            default: () => [],
        }
    });
</script>

<template>
    <!--begin::Page title-->
    <div class="page-title d-flex flex-column justify-content-center flex-wrap me-3">
        <!--begin::Title-->
        <h1 class="page-heading d-flex text-dark fw-bold fs-3 flex-column justify-content-center my-0">{{ title }}</h1>
        <!--end::Title-->
        <!--begin::Breadcrumb-->
            <ul class="breadcrumb breadcrumb-separatorless fw-semibold fs-7 my-0 pt-1" v-if="toolbar.length">
                <li
                    v-for="(item, index) in toolbar"
                    :key="index"
                    class="breadcrumb-item"
                >
                    <template v-if="index !== 0">
                        <span class="bullet bg-gray-400 w-6px h-2px mx-2"></span>
                    </template>

                    <template v-if="item.route">
                        <Link :href="route(item.route)" class="text-muted text-hover-primary">{{ item.label }}</Link>
                    </template>
                    <template v-else>
                        <span class="text-muted">{{ item.label }}</span>
                    </template>
                </li>
            </ul>
        <!--end::Breadcrumb-->
    </div>
    <!--end::Page title-->
</template>
