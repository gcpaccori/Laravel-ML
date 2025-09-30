<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import axios from 'axios'

const fecha = ref('')
const hora = ref('')
let diferencia = 0
let timer = null

// Obtener hora del servidor
const fetchHoraServidor = async () => {
  const res = await axios.get(route('server.time'))
  const serverTime = new Date(res.data.time)
  const localTime = new Date()
  diferencia = serverTime.getTime() - localTime.getTime()
}

// Formatear fecha (ej: lunes, 8 de septiembre de 2025)
const formatearFecha = (date) => {
  return date.toLocaleDateString('es-PE', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  })
}

// Formatear hora digital
const formatearHora = (date) => {
  return date.toLocaleTimeString('es-PE', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false
  })
}

// Actualizar reloj
const actualizarReloj = () => {
  const ahora = new Date(new Date().getTime() + diferencia)
  fecha.value = formatearFecha(ahora)
  hora.value = formatearHora(ahora)
}

onMounted(async () => {
  await fetchHoraServidor()
  actualizarReloj()
  timer = setInterval(actualizarReloj, 1000)
})

onUnmounted(() => {
  clearInterval(timer)
})
</script>

<template>
  <div class="d-flex flex-column">
    <div class="fs-2 font-mono text-green-400 drop-shadow-glow">
      {{ hora }}
    </div>
    <div class=" fs-5 text-gray-800 mt-2 text-uppercase">
      {{ fecha }}
    </div>
  </div>
</template>
