<template>
  <div class="parametros-agua-chart">
    <div class="chart-header">
      <h3>Historial de Registros</h3>
      <div class="chart-controls">
        <select v-model="selectedPiscina" @change="updateChart" class="piscina-select">
          <option value="">Todas las piscinas</option>
          <option v-for="piscina in piscinasDisponibles" :key="piscina" :value="piscina">
            Piscina {{ piscina }}
          </option>
        </select>
        <button @click="resetChart" class="reset-btn">Reset Zoom</button>
      </div>
    </div>
    <div ref="chartContainer" class="chart-container"></div>
    <div v-if="loading" class="loading">Cargando datos...</div>
    <div v-if="error" class="error">{{ error }}</div>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, watch, computed, nextTick } from 'vue'
import * as echarts from 'echarts'

// Props
const props = defineProps({
  data: {
    type: Object,
    default: null
  },
  height: {
    type: String,
    default: '500px'
  },
  autoUpdate: {
    type: Boolean,
    default: false
  },
  updateInterval: {
    type: Number,
    default: 30000 // 30 segundos
  }
})

// Emits
const emit = defineEmits(['chartReady', 'dataRequest'])

// Refs
const chartContainer = ref()
const loading = ref(false)
const error = ref('')
const selectedPiscina = ref('')
const myChart = ref()
const updateTimer = ref()

// Computed
const piscinasDisponibles = computed(() => {
  if (!props.data?.data) return []
  const piscinas = new Set()
  props.data.data.forEach((fechaData) => {
    fechaData.registros.forEach((registro) => {
      piscinas.add(registro.piscina_id)
    })
  })
  return Array.from(piscinas).sort((a, b) => a - b)
})

// Configuración de colores y unidades
const PARAMETER_CONFIG = {
  temperatura: { color: '#1890ff', unit: '°C', type: 'line', yAxisIndex: 0 },
  ph: { color: '#722ed1', unit: '', type: 'line', yAxisIndex: 1 },
  oxigeno_disuelto: { color: '#52c41a', unit: 'mg/L', type: 'line', yAxisIndex: 0 },
  ion_nitrato: { color: '#fa8c16', unit: 'mg/L', type: 'line', yAxisIndex: 0 }
}

// Procesar datos para el gráfico
const processChartData = () => {
  if (!props.data?.data) return { categories: [], seriesData: {} }

  const allRegistros = []

  // Aplanar todos los registros y organizarlos cronológicamente
  props.data.data.forEach((fechaData) => {
    fechaData.registros.forEach((registro) => {
      if (!selectedPiscina.value || registro.piscina_id == selectedPiscina.value) {
        allRegistros.push({
          ...registro,
          fecha: fechaData.fecha,
          timestamp: new Date(registro.fecha_medicion).getTime(),
          fechaFormateada: new Date(registro.fecha_medicion).toLocaleDateString('es-ES', {
            day: '2-digit',
            month: 'short'
          })
        })
      }
    })
  })

  // Ordenar por timestamp
  allRegistros.sort((a, b) => a.timestamp - b.timestamp)

  // Crear categorías (fechas para el eje X)
  const categories = allRegistros.map(registro => registro.fechaFormateada)

  // Crear datos para cada serie
  const seriesData = {
    temperatura: allRegistros.map(r => r.temperatura || null),
    ph: allRegistros.map(r => r.ph || null),
    oxigeno_disuelto: allRegistros.map(r => r.oxigeno_disuelto || null),
    ion_nitrato: allRegistros.map(r => r.ion_nitrato || null)
  }

  return { categories, seriesData, registros: allRegistros }
}

// Configuración de opciones del gráfico
const getChartOption = () => {
  const { categories, seriesData, registros } = processChartData()

  if (!registros) {
    return {
      title: {
        text: 'Sin datos disponibles',
        left: 'center',
        top: 'middle'
      }
    }
  }

  return {
    tooltip: {
      trigger: 'axis',
      axisPointer: {
        type: 'cross',
        label: {
          backgroundColor: '#6a7985'
        }
      },
      formatter: (params) => {
        if (!params.length) return ''
        const dataIndex = params[0].dataIndex
        const registro = registros[dataIndex]

        let html = `<div style="padding: 8px;">
          <strong>Piscina ${registro.piscina_id}</strong><br/>
          <strong>${new Date(registro.fecha_medicion).toLocaleDateString('es-ES', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
          })}, ${new Date(registro.fecha_medicion).toLocaleTimeString('es-ES')}</strong><br/>
          <hr style="margin: 6px 0; border: none; border-top: 1px solid #eee;">`

        params.forEach(param => {
            console.log(param);

          if (param.value !== null && param.value !== undefined) {
            const config = PARAMETER_CONFIG[param.seriesName]
            html += `
              <div style="display: flex; align-items: center; margin: 4px 0;">
                <span style="display: inline-block; width: 10px; height: 10px; background: ${param.color}; margin-right: 8px;"></span>
                <strong>${getParameterDisplayName(param.seriesName)}:</strong> ${param.value}${config.unit}
              </div>`
          }
        })

        html += '</div>'
        return html
      }
    },
    legend: {
      data: Object.keys(PARAMETER_CONFIG).map(key => ({
        name: key,
        icon: PARAMETER_CONFIG[key].type === 'bar' ? 'rect' : 'line'
      })),
      top: 40,
      formatter: (name) => getParameterDisplayName(name)
    },
    grid: {
      left: 60,
      right: 60,
      bottom: 100,
      top: 80,
      containLabel: true
    },
    xAxis: {
      type: 'category',
      data: categories,
      boundaryGap: PARAMETER_CONFIG.oxigeno_disuelto.type === 'bar',
      axisLabel: {
        interval: 'auto',
        rotate: 45,
        formatter: (value, index) => {
          // Mostrar menos etiquetas si hay muchos datos
          const step = Math.max(1, Math.floor(categories.length / 15))
          return index % step === 0 ? value : ''
        }
      },
      axisPointer: {
        type: 'shadow'
      }
    },
    yAxis: [
      {
        type: 'value',
        name: 'Temperatura (°C) / Oxígeno / Nitrato',
        position: 'left',
        axisLabel: {
          formatter: '{value}'
        },
        splitLine: {
          show: true,
          lineStyle: {
            type: 'dashed',
            opacity: 0.3
          }
        }
      },
      {
        type: 'value',
        name: 'pH',
        position: 'right',
        axisLabel: {
          formatter: '{value}'
        },
        splitLine: {
          show: false
        }
      }
    ],
    series: Object.keys(PARAMETER_CONFIG).map(paramName => {
      const config = PARAMETER_CONFIG[paramName]
      return {
        name: paramName,
        type: config.type,
        yAxisIndex: config.yAxisIndex,
        data: seriesData[paramName],
        itemStyle: {
          color: config.color,
          ...(config.type === 'bar' && {
            borderRadius: [2, 2, 0, 0],
            opacity: 0.8
          })
        },
        lineStyle: config.type === 'line' ? {
          width: 2,
          color: config.color
        } : undefined,
        symbol: config.type === 'line' ? 'circle' : undefined,
        symbolSize: config.type === 'line' ? 4 : undefined,
        smooth: config.type === 'line',
        connectNulls: false,
        barWidth: config.type === 'bar' ? '60%' : undefined,
        emphasis: {
          focus: 'series'
        }
      }
    })
  }
}

// Obtener nombre de parámetro para mostrar
const getParameterDisplayName = (paramName) => {
  const names = {
    temperatura: 'Temperatura (°C)',
    ph: 'pH',
    oxigeno_disuelto: 'Oxígeno Disuelto (mg/L)',
    ion_nitrato: 'Ion Nitrato (mg/L)'
  }
  return names[paramName] || paramName
}

// Inicializar gráfico
const initChart = async () => {
  if (!chartContainer.value) return

  try {
    myChart.value = echarts.init(chartContainer.value)
    updateChart()

    // Manejar redimensionamiento
    window.addEventListener('resize', handleResize)

    emit('chartReady', myChart.value)
  } catch (err) {
    error.value = 'Error al inicializar el gráfico'
    console.error('Error inicializando gráfico:', err)
  }
}

// Actualizar gráfico
const updateChart = () => {
  if (!myChart.value) return

  loading.value = true
  error.value = ''

  try {
    const option = getChartOption()
    myChart.value.setOption(option, true)
  } catch (err) {
    error.value = 'Error al actualizar el gráfico'
    console.error('Error actualizando gráfico:', err)
  } finally {
    loading.value = false
  }
}

// Reset zoom
const resetChart = () => {
  if (!myChart.value) return
  myChart.value.dispatchAction({
    type: 'dataZoom',
    start: 0,
    end: 100
  })
}

// Manejar redimensionamiento
const handleResize = () => {
  myChart.value?.resize()
}

// Configurar auto-actualización
const setupAutoUpdate = () => {
  if (props.autoUpdate && props.updateInterval > 0) {
    updateTimer.value = setInterval(() => {
      emit('dataRequest')
    }, props.updateInterval)
  }
}

// Limpiar auto-actualización
const clearAutoUpdate = () => {
  if (updateTimer.value) {
    clearInterval(updateTimer.value)
    updateTimer.value = undefined
  }
}

// Watchers
watch(() => props.data, () => {
  nextTick(() => updateChart())
}, { deep: true })

watch(() => props.autoUpdate, (newVal) => {
  if (newVal) {
    setupAutoUpdate()
  } else {
    clearAutoUpdate()
  }
})

// Lifecycle
onMounted(async () => {
  await nextTick()
  initChart()
  setupAutoUpdate()
})

onUnmounted(() => {
  clearAutoUpdate()
  window.removeEventListener('resize', handleResize)
  myChart.value?.dispose()
})
</script>

<style scoped>
.parametros-agua-chart {
  width: 100%;
  position: relative;
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.chart-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 20px;
  border-bottom: 1px solid #f0f0f0;
}

.chart-header h3 {
  margin: 0;
  color: #666;
  font-size: 1.2rem;
  font-weight: normal;
}

.chart-controls {
  display: flex;
  gap: 15px;
  align-items: center;
}

.piscina-select {
  padding: 6px 12px;
  border: 1px solid #d9d9d9;
  border-radius: 4px;
  background: white;
  font-size: 14px;
  min-width: 140px;
  color: #666;
}

.piscina-select:focus {
  outline: none;
  border-color: #1890ff;
  box-shadow: 0 0 0 2px rgba(24, 144, 255, 0.2);
}

.reset-btn {
  padding: 6px 12px;
  background: #f5f5f5;
  border: 1px solid #d9d9d9;
  border-radius: 4px;
  cursor: pointer;
  font-size: 12px;
  color: #666;
  transition: all 0.2s;
}

.reset-btn:hover {
  background: #e6f7ff;
  border-color: #91d5ff;
  color: #1890ff;
}

.chart-container {
  width: 100%;
  height: v-bind(height);
  background: #fafafa;
}

.loading {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  background: rgba(255, 255, 255, 0.95);
  padding: 20px 30px;
  border-radius: 6px;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  color: #666;
  font-size: 14px;
}

.error {
  color: #ff4d4f;
  text-align: center;
  padding: 20px;
  background: #fff2f0;
  border: 1px solid #ffccc7;
  border-radius: 6px;
  margin: 20px;
  font-size: 14px;
}
</style>
