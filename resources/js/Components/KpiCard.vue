<!-- resources/js/Components/Dashboard/KpiCard.vue -->
<template>
  <el-card shadow="hover" class="kpi-card" :body-style="{ padding: '16px' }">
    <div class="kpi-content">
      <div class="kpi-icon" :style="{ backgroundColor: colorSuave }">
        <el-icon :size="22" :color="color"><component :is="icon" /></el-icon>
      </div>
      <div class="kpi-texto">
        <p class="kpi-label">{{ label }}</p>
        <p class="kpi-valor">
          {{ valorFormateado }}<span v-if="sufijo" class="kpi-sufijo">{{ sufijo }}</span>
        </p>
        <p v-if="ayuda" class="kpi-ayuda">{{ ayuda }}</p>
      </div>
    </div>
  </el-card>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  label: { type: String, required: true },
  valor: { type: [Number, String, null], default: null },
  sufijo: { type: String, default: '' },
  ayuda: { type: String, default: '' },
  icon: { type: [String, Object, Function], required: true },
  color: { type: String, default: '#409EFF' },
  decimales: { type: Number, default: 0 },
})

const valorFormateado = computed(() => {
  if (props.valor === null || props.valor === undefined) return '—'
  return Number(props.valor).toLocaleString('es-PE', {
    minimumFractionDigits: props.decimales,
    maximumFractionDigits: props.decimales,
  })
})

const colorSuave = computed(() => `${props.color}1A`) // color + alpha hex (~10%)
</script>

<style scoped>
.kpi-card {
  height: 100%;
}

.kpi-content {
  display: flex;
  align-items: flex-start;
  gap: 12px;
}

.kpi-icon {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.kpi-texto {
  min-width: 0;
}

.kpi-label {
  margin: 0;
  font-size: 12px;
  color: var(--el-text-color-secondary);
  text-transform: uppercase;
  letter-spacing: 0.03em;
}

.kpi-valor {
  margin: 4px 0 0 0;
  font-size: 22px;
  font-weight: 700;
  line-height: 1.1;
  color: var(--el-text-color-primary);
}

.kpi-sufijo {
  font-size: 14px;
  font-weight: 500;
  margin-left: 2px;
  color: var(--el-text-color-secondary);
}

.kpi-ayuda {
  margin: 4px 0 0 0;
  font-size: 11px;
  color: var(--el-text-color-placeholder);
}
</style>
