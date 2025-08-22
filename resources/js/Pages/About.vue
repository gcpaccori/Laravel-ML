<script setup>
    import { ref, onMounted, computed } from 'vue'
    import DataTable from 'datatables.net-vue3'
    import DataTablesLib from 'datatables.net'
    import App from '@/Layouts/AppLayout.vue';
    import axios from 'axios';

    DataTable.use(DataTablesLib);

    const props = defineProps({
        title: String,
        toolbar: {
            type: Array,
            required: false
        },
        columns: {
            type: Object,
            required: false
        }
    });

    let dt;
    const tableRef = ref(null);
    const ajaxUrl = route('datatable.usuarios');

    const processedColumns = ref([]);

    onMounted( () => {
        dt = tableRef.value.dt;
        processedColumns.value = props.columns.original.map(col => {
            if (col.isHtml) {
                return {
                    ...col,
                    render: (data) => data
                }
            }
            return col
        })
    });

    const handleSearch = (event) => {
        const value = event.target.value
        dt.search(value).draw();
    }

</script>

<template>

    <App :title="title" :toolbar="toolbar">

        <template #btnCreate>
            <a href="#" class="btn btn-sm fw-bold btn-primary">Nuevo</a>
        </template>

        <h1>{{processedColumns}}</h1>

        <DataTable
            ref="tableRef"
            :ajax="ajaxUrl"
            :columns="columns.original"
            class="display"
            :options="{
                searching: true,
                processing: true,
                serverSide: true,
                responsive: true,
                language: {
                url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
            }
        }"
        />
    </App>
</template>
