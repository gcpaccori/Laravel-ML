<script setup>
    import { onMounted, onBeforeUnmount, ref } from "vue";

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

    </App>
</template>
