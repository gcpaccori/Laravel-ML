<script setup>
import { onMounted, ref } from "vue";
import { ElMessage, ElMessageBox } from 'element-plus';
import { Delete, Edit, Setting, Plus } from '@element-plus/icons-vue'
import CampaniaEtapaForm from "../Form/CampaniaEtapaForm.vue";
import ParametroProduccionForm from "../Form/ParametroProduccionForm.vue";

const props = defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false,
    },
    campania: Object
});

const isLoadingEtapas = ref({});
const especieEtapas = ref({});
const activeCollapses = ref([]);

// MODAL
const dialogVisible = ref(false);
const dialogProduccion = ref(false);

const dataForm = ref(null);
const dataFormProduccion = ref({});

const showModal = ( campania_especie_id, piscigranja_id ) => {
    dialogVisible.value = true;
    dataForm.value = {
        campania_especie_id,
        piscigranja_id
    };
};

const showProduccion = async (campania_etapa_id) => {
    const { data } = await axios.get(route('campanias.etapas.edit', campania_etapa_id));

    dataFormProduccion.value.campania_etapa_id = campania_etapa_id;
    dataFormProduccion.value.parametros_produccion = data.parametros_produccion;
    dialogProduccion.value = true;
};

const loadEspecieEtapas = async (campaniaEspecieId) => {
    try {
        isLoadingEtapas.value[campaniaEspecieId] = true;
        const { data } = await axios.get(route("campanias.etapas.options", campaniaEspecieId));
        especieEtapas.value[campaniaEspecieId] = data;
    } catch (error) {
        console.error('Error cargando etapas:', error);
        especieEtapas.value[campaniaEspecieId] = [];
    } finally {
        isLoadingEtapas.value[campaniaEspecieId] = false;
    }
};

const getEspecieEtapas = (campaniaEspecieId) => {
    return especieEtapas.value[campaniaEspecieId] || [];
};

// RECUPERAR DATOS GUARDADOS
const handleSaved = async( res ) => {
    await loadEspecieEtapas( res.campania_especie_id )
};

const handleSavedProduccion = async( res ) => {
    //
};

// FUNCIONES DE ACCIONES (BOTONES)
const handleEdit = async (campania_etapa_id, piscigranja_id) => {
    const { data } = await axios.get(route('campanias.etapas.edit', campania_etapa_id));
    dataForm.value = data;
    dataForm.value.piscigranja_id = piscigranja_id;
    dialogVisible.value = true;
};

const handleDelete = async(campania_etapa_id, campania_especie_id) => {
    ElMessageBox.confirm(
        '¿Estás seguro de que deseas eliminar este registro?',
        'Advertencia',
        {
            confirmButtonText: 'Eliminar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
            center: true,
        }
    ).then( async () => {
        const response = await axios.delete(route('campanias.etapas.destroy', campania_etapa_id));
        ElMessage({
            message: response.data.message,
            type: response.data.success ? 'success' : 'error',
        });
        await loadEspecieEtapas( campania_especie_id );
    }).catch( (e) => {
        console.log(e);
    } );
};

onMounted(async () => {
    if (props.campania?.especies) {
        // Inicializar todos los paneles desplegados
        activeCollapses.value = props.campania.especies.map(item => item.id);
        for (const item of props.campania.especies) {
            await loadEspecieEtapas(item.id);
        }
    }
});
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="row">
            <div v-if="campania.especies && campania.especies.length > 0" class="col-12">
                <el-collapse v-model="activeCollapses" class="mb-4 border-0">
                    <el-collapse-item
                        v-for="item in campania.especies"
                        :key="item.id"
                        :name="item.id"
                        class="mb-3 border rounded shadow-sm overflow-hidden"
                    >
                        <template #title>
                            <div class="d-flex align-items-center justify-content-between w-100 px-3">
                                <span class="fs-4 fw-bold text-dark">
                                    {{ item.especie.nombre }} - <span class="text-capitalize">{{ campania.sistema_crianza }}</span>
                                </span>
                            </div>
                        </template>

                        <div class="p-3 bg-light-subtle border-top">
                            <el-descriptions
                                class="mb-4"
                                :column="3"
                                border
                                size="small"
                            >
                                <el-descriptions-item label="Fecha Siembra">
                                    {{ item.fecha_siembra_formateada }}
                                </el-descriptions-item>
                                <el-descriptions-item label="N° Alevines Inicial">
                                    {{ item.cantidad_siembra }}
                                </el-descriptions-item>
                                <el-descriptions-item label="N° Peces Final">
                                    {{ item.cantidad_cosechada }}
                                </el-descriptions-item>
                                <el-descriptions-item label="Peso Inicial Alevín">
                                    {{ item.peso_inicial_gr }} g
                                </el-descriptions-item>
                                <el-descriptions-item label="Peso Final Pez">
                                    {{ item.peso_final_gr }} g
                                </el-descriptions-item>
                            </el-descriptions>

                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="m-0 fw-bold text-muted">Etapas de Producción</h6>
                                <el-button
                                    type="primary"
                                    size="small"
                                    :icon="Plus"
                                    @click="showModal(item.id, campania.piscigranja_id)"
                                >
                                    Agregar Etapa
                                </el-button>
                            </div>

                            <!-- Tabla de Etapas -->
                            <el-table
                                :data="getEspecieEtapas(item.id)"
                                class="w-100"
                                v-loading="isLoadingEtapas[item.id]"
                                border
                                stripe
                                size="small"
                            >
                                <el-table-column label="Etapa" min-width="120">
                                    <template #default="{ row }">
                                        <span class="fw-semibold">{{ row.etapa?.nombre }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Piscina" min-width="130">
                                    <template #default="{ row }">
                                        {{ row.piscina?.nombre }}
                                    </template>
                                </el-table-column>

                                <el-table-column label="Fecha Inicio" min-width="120" align="center">
                                    <template #default="{ row }">
                                        {{ row.fecha_inicio_formato }}
                                    </template>
                                </el-table-column>

                                <el-table-column label="Fecha Fin" min-width="120" align="center">
                                    <template #default="{ row }">
                                        {{ row.fecha_fin_formato }}
                                    </template>
                                </el-table-column>

                                <el-table-column align="center" prop="numero_peces_inicial" label="Cant. Inicial" min-width="110"/>
                                <el-table-column align="center" prop="numero_peces_final" label="Cant. Final" min-width="110"/>
                                <el-table-column align="center" prop="peso_inicial_gr" label="Peso Inc. (g)" min-width="110"/>
                                <el-table-column align="center" prop="peso_final_gr" label="Peso Fin. (g)" min-width="110"/>
                                <el-table-column align="center" prop="densidad_siembra" label="Densidad (Peces/m³)" min-width="140"/>

                                <el-table-column align="center" label="Parámetros" min-width="130">
                                    <template #default="{ row }">
                                        <el-button
                                            @click="showProduccion(row.id)"
                                            :icon="Setting"
                                            type="success"
                                            size="small"
                                            plain
                                        >
                                            Ver
                                        </el-button>
                                    </template>
                                </el-table-column>

                                <el-table-column align="center" label="Estado" min-width="120">
                                    <template #default="{ row }">
                                        <el-tag v-if="row.estado === 'planificada'" type="info" effect="light" round>Planificada</el-tag>
                                        <el-tag v-else-if="row.estado === 'en_proceso'" type="warning" effect="light" round>En Proceso</el-tag>
                                        <el-tag v-else-if="row.estado === 'finalizada'" type="success" effect="light" round>Finalizada</el-tag>
                                        <el-tag v-else-if="row.estado === 'cancelada'" type="danger" effect="light" round>Cancelada</el-tag>
                                    </template>
                                </el-table-column>

                                <el-table-column align="center" label="Opciones" min-width="110">
                                    <template #default="{ row }">
                                        <el-button-group>
                                            <el-button @click="handleEdit(row.id, campania.piscigranja_id)" type="primary" size="small" :icon="Edit" />
                                            <el-button @click="handleDelete(row.id, row.campania_especie_id)" type="danger" size="small" :icon="Delete" />
                                        </el-button-group>
                                    </template>
                                </el-table-column>
                            </el-table>
                        </div>
                    </el-collapse-item>
                </el-collapse>
            </div>

            <!-- Estado Vacío -->
            <div v-else class="col-12">
                <el-card shadow="never" class="text-center py-4">
                    <el-empty
                        description="Sin especies registradas"
                        :image-size="120"
                    >
                        <template #description>
                            <p class="text-muted fs-6">
                                Esta campaña no tiene especies agregadas.<br>
                                Agrega especies para comenzar el registro de siembra y seguimiento de la producción.
                            </p>
                        </template>
                    </el-empty>
                </el-card>
            </div>
        </div>

        <CampaniaEtapaForm v-model="dialogVisible" :dataForm="dataForm" @saved="handleSaved"/>
        <ParametroProduccionForm v-model="dialogProduccion" :dataForm="dataFormProduccion" @saved="handleSavedProduccion"/>
    </App>
</template>

<style scoped>
:deep(.el-collapse-item__header) {
    font-size: 1rem;
    height: auto;
    padding: 12px 0;
}
:deep(.el-descriptions__label) {
    font-weight: 600;
}
</style>
