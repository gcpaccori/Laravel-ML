<script setup>
import { computed } from "vue";
import katex from "katex";
import "katex/dist/katex.min.css";

const props = defineProps({
    latex: {
        type: String,
        required: true,
    },
});

const renderedFormula = computed(() => {
    try {
        return katex.renderToString(props.latex, {
            displayMode: true,
            throwOnError: false,
            strict: "ignore",
        });
    } catch {
        return props.latex;
    }
});
</script>

<template>
    <div class="latex-formula" v-html="renderedFormula" />
</template>

<style scoped>
.latex-formula :deep(.katex-display) {
    margin: 0;
    overflow-x: auto;
    overflow-y: hidden;
    padding: 0.25rem 0;
}

.latex-formula :deep(.katex) {
    font-size: 1.15em;
}
</style>
