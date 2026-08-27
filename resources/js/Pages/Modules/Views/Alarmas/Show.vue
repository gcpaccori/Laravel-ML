<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { ElMessage, ElMessageBox } from 'element-plus';
import { Check } from '@element-plus/icons-vue';

const props = defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false
    },
    alarma: {
        type: Object,
        required: true,
    },
})

const cargando = ref(false)

const resolver = async () => {
    await ElMessageBox.confirm(
        `¿Deseas marcar esta alarma como resuelta?`,
        'Resolver alarma',
        {
            confirmButtonText: 'Sí, resolver',
            cancelButtonText: 'Cancelar',
            type: 'success',
        }
    ).then( async () => {
        const response = await axios.patch(route('alarmas.resolver', props.alarma.id))
        ElMessage({
            message: response.data.message,
            type: response.data.success ? 'success' : 'error',
        });
        router.reload({ only: ['alarma'] });
    }).catch( (e) => {
        ElMessage.error(e);
    } );
}
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <el-card shadow="never">
            <template #header>
                <div class="d-flex justify-content-between align-items-center">
                    <h4 class="m-0">{{ alarma.titulo }}</h4>
                    <el-tag size="large" :type="alarma.estado_info.type" effect="plain">
                        {{ alarma.estado_info.label }}
                    </el-tag>
                </div>
            </template>

            <div class="space-y-6">
                <!-- Descripción -->
                <div>
                    <span class="block mb-1 text-sm font-semibold">Descripción del problema</span>
                    <p class="m-0 bg-gray-50 p-3 rounded-md border border-gray-100">
                        {{ alarma.mensaje || 'Sin descripción detallada.' }}
                    </p>
                </div>

                <!-- Detalles Consolidados en ElDescriptions -->
                <el-descriptions :column="2" border>
                    <!-- Ubicación -->
                    <el-descriptions-item label="Piscigranja">
                        <span class="font-bold text-gray-800">{{ alarma.piscigranja?.nombre || '—' }}</span>
                    </el-descriptions-item>

                    <el-descriptions-item label="Piscina">
                        <span class="font-bold text-gray-800">{{ alarma.piscina?.nombre || 'General / Ninguna' }}</span>
                    </el-descriptions-item>

                    <!-- Datos Técnicos -->
                    <el-descriptions-item label="Módulo">
                        <span class="text-capitalize">{{ alarma.modulo.replace('_', ' ') }}</span>
                    </el-descriptions-item>

                    <el-descriptions-item label="Parámetro medido">
                        <span>{{ alarma.parametro || '—' }}</span>
                    </el-descriptions-item>

                    <el-descriptions-item label="Valor detectado">
                        <el-tag type="info" effect="plain">{{ alarma.valor_detectado ?? '—' }}</el-tag>
                    </el-descriptions-item>

                    <el-descriptions-item label="Nivel de alerta">
                        <el-tag :type="alarma.nivel_info.type" effect="light">
                            {{ alarma.nivel_info.label }}
                        </el-tag>
                    </el-descriptions-item>

                    <!-- Tiempos y Estado -->
                    <el-descriptions-item label="Fecha de generación">
                        <span class="text-gray-700">{{ alarma.created_at }}</span>
                    </el-descriptions-item>

                    <el-descriptions-item label="Estado de resolución">
                        <span v-if="alarma.resuelta_en" class="text-gray-700">
                            Resuelta el {{ alarma.resuelta_en }}
                            <span v-if="alarma.resuelta_por" class="text-gray-400 text-xs block">
                                Por: {{ alarma.resuelta_por.name }}
                            </span>
                        </span>
                        <span v-else>Pendiente de resolución</span>
                    </el-descriptions-item>
                </el-descriptions>

                <!-- Botón de Acción -->
                <div v-if="alarma.estado === 'activa'" class="d-flex justify-content-end mt-2">
                    <el-button
                        type="success"
                        size="default"
                        :icon="Check"
                        :loading="cargando"
                        @click="resolver"
                    >
                        Marcar como resuelta
                    </el-button>
                </div>
            </div>
        </el-card>
    </App>
</template>
