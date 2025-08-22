<script setup>
    import { onMounted, ref, watch } from 'vue';

    const props = defineProps({
    modelValue: Boolean,
    title: {
        type: String,
        default: 'Formulario',
    },
    width: {
        type: String,
        default: '50%'
    },
    fullscreen: {
        type: Boolean,
        default: false
    }
});

const emit = defineEmits(['update:modelValue']);
const dialogVisible = ref(props.modelValue);
const computedWidth = ref('90%');

const updateResponsiveWidth = () => {
  const w = window.innerWidth;
  if (w < 640) computedWidth.value = '95%';
  else if (w < 768) computedWidth.value = '85%';
  else if (w < 1024) computedWidth.value = '70%';
  else computedWidth.value = props.width || '50%';
};


// Sincroniza con el padre
watch(() => props.modelValue, (val) => {
  dialogVisible.value = val;
});
watch(dialogVisible, (val) => {
  emit('update:modelValue', val);
});

onMounted(() => {
  updateResponsiveWidth();
  window.addEventListener('resize', updateResponsiveWidth);
});

</script>

<template>
  <el-dialog
    v-model="dialogVisible"
    modal-class="overide-animation"
    :close-on-press-escape="false"
    :close-on-click-modal="false"
    @close="() => dialogVisible = false"
    :width="computedWidth"
    :fullscreen="fullscreen"
  >
    <template #header>
      <h4 class="modal-title fw-semibold">{{ title }}</h4>
    </template>
    <!-- Contenido personalizado del formulario -->
    <slot />

    <!-- Footer personalizado -->
    <template #footer>
      <slot name="footer" />
    </template>
  </el-dialog>
</template>
