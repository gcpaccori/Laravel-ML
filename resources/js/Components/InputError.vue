<script setup>
defineProps({
    message: String,
});
</script>

<template>
    <div v-show="message">
        <p class="text-danger fs-7">
            {{ message }}
        </p>
    </div>
</template>
