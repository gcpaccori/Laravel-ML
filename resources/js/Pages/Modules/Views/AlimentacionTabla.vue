<script setup>
    import { router } from "@inertiajs/vue3";
    import { onMounted, onBeforeUnmount, ref } from "vue";
    import { ElMessage, ElMessageBox } from 'element-plus';
    import { useDynamicAction } from '@/Composables/useDynamicAction';

    const props = defineProps({
        title: String,
        toolbar: {
            type: Array,
            required: false
        },
        columns: {
            type: Object,
            required: false
        },
        accionesGrilla: {
            type: Array,
            required: false,
            default: []
        }
    });

    const tableInstance = ref(null);
    const ajaxUrl = route('alimentacionbfts.datatable');

    const handleGenerar = (campaniaEspecie) => {
        router.visit(route('alimentacionbfts.create', campaniaEspecie));
    };

    const handleTableReady = (dt) => {
        tableInstance.value = dt;
    };

    const handleEdit = (campaniaEspecie) => {
        router.visit(route('alimentacionbfts.edit', campaniaEspecie));
    };

    const handleShow = (campaniaEspecie) => {
        router.visit(route('alimentacionbfts.show', campaniaEspecie));
    };

    const methods = {
        handleEdit,
        handleShow,
        handleGenerar
    };

    const { handleDynamicAction } = useDynamicAction(methods);

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <BaseDataTable
          :ajax-url="ajaxUrl"
          :columns="columns"
          @tableReady="handleTableReady"
          @action="handleDynamicAction"
        ></BaseDataTable>
    </App>
</template>
