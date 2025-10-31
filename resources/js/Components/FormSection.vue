<script setup>
import { computed, useSlots } from 'vue';

defineEmits(['submitted']);

const hasActions = computed(() => !! useSlots().actions);
const hasTitle = computed(() => !! useSlots().title);
const hasDescription = computed(() => !! useSlots().description);
const hasToolbar = computed(() => !! useSlots().toolbar);
</script>

<template>
    <div class="card shadow-sm">
        <el-form @submit.prevent="$emit('submitted')" label-position="top">
            <div class="card-header" v-if="hasTitle || hasDescription || hasToolbar" >
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-dark">
                        <slot name="title" />
                    </span>
                    <span class="text-gray-400 mt-1 fw-semibold fs-6">
                        <slot name="description" />
                    </span>
                </h3>
                <div class="card-toolbar">
                    <slot name="toolbar" />
                </div>
            </div>
            <div class="card-body">
                <slot name="form" />
            </div>
            <div v-if="hasActions" class="card-footer">
                <slot name="actions" />
            </div>
        </el-form>
    </div>
</template>
