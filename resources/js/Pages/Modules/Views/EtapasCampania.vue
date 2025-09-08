<script setup>
import { onMounted, ref } from "vue";
import CampaniaEtapaForm from "../Form/CampaniaEtapaForm.vue";
import {
  Delete,
  Edit,
} from '@element-plus/icons-vue'

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
// MODAL
const dialogVisible = ref(false);
const dataForm = ref(null);

const showModal = ( campania_especie_id, piscigranja_id ) => {
    dialogVisible.value = true;
    dataForm.value = {
        campania_especie_id,
        piscigranja_id
    };
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

onMounted(async () => {
    for (const item of props.campania.especies) {
        await loadEspecieEtapas(item.id);
    }
});

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="row">
            <div v-if="campania.especies.length > 0">
                <div  v-for="item in campania.especies" :key="item.id" class="col-lg-12 mb-4">
                    <div class="card shadow-sm">
                        <div class="card-header collapsible cursor-pointer rotate" data-bs-toggle="collapse" :data-bs-target="`#car_${item.id}`">
                            <h3 class="card-title align-items-start flex-column">
                                <span class="card-label fs-5 fw-bold text-dark">{{ item.especie.nombre }}</span>
                                <span class="text-gray-700 mt-2 fw-semibold fs-6">
                                    <span>Fecha Siembra: {{ item.fecha_siembra_formateada }}</span> |
                                    <span>Cantidad Siembra: {{ item.cantidad_siembra }}</span> |
                                    <span>Peso Promedio: {{ item.peso_promedio_gr }}</span> |
                                    <span>Cantidad Cosecha: {{ item.cantidad_cosechada }}</span>
                                </span>
                            </h3>
                            <div class="card-toolbar rotate-180">
                                <i class="ki-duotone ki-down fs-1"></i>
                            </div>
                        </div>
                        <div :id="`car_${item.id}`" class="collapse show">
                            <div class="card-body">
                                <div class="mb-2">
                                    <a @click="showModal( item.id, campania.piscigranja_id )" class="text-primary text-hover-info cursor-pointer">[+Agregar Etapa]</a>
                                </div>
                                <el-table
                                    :data="getEspecieEtapas( item.id )"
                                    style="width: 100%"
                                    v-loading="isLoadingEtapas[item.id]"
                                >
                                    <el-table-column label="Etapa">
                                        <template #default="{ row }">
                                            {{row.etapa?.nombre}}
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Piscina">
                                        <template #default="{ row }">
                                            {{ row.piscina?.nombre }}
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Fecha Inicio">
                                        <template #default="{ row }">
                                            {{ row.fecha_inicio_formato }}
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Fecha Fin">
                                        <template #default="{ row }">
                                            {{ row.fecha_fin_formato }}
                                        </template>
                                    </el-table-column>

                                    <el-table-column class-name="text-center" prop="cantidad_inicial" label="Cantidad Inicial" />
                                    <el-table-column class-name="text-center" prop="cantidad_final" label="Cantidad Final" />
                                    <el-table-column class-name="text-center" prop="peso_promedio_gr" label="Peso Promedio" />
                                    <el-table-column class-name="text-center" label="Estado">
                                        <template #default="{ row }">
                                            <el-tag v-if="row.estado === 'en_proceso'" type="warning" effect="dark" round>En Proceso</el-tag>
                                            <el-tag v-if="row.estado === 'finalizada'" type="success" effect="dark" round>Finalizada</el-tag>
                                            <el-tag v-if="row.estado === 'cancelada'" type="danger" effect="dark" round>Cancelada</el-tag>
                                        </template>
                                    </el-table-column>

                                    <el-table-column class-name="text-center"label="Opciones">
                                        <template #default="{ row }">
                                            <el-button-group class="ml-4">
                                                <el-button type="primary" size="small" :icon="Edit" />
                                                <el-button type="danger" size="small" :icon="Delete" />
                                            </el-button-group>
                                        </template>
                                    </el-table-column>

                                </el-table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div v-else="">
                <div class="alert alert-info d-flex align-items-center p-5">
                    <i class="ki-duotone ki-information-5 fs-2hx text-info me-4">
                        <span class="path1"></span>
                        <span class="path2"></span>
                        <span class="path3"></span>
                    </i>

                    <div class="d-flex flex-column">
                        <h4 class="mb-1 text-dark">Sin especies registradas</h4>
                        <span>Esta campaña no tiene especies agregadas. Agrega especies para comenzar el registro de siembra y seguimiento de la producción.</span>
                    </div>
                </div>
            </div>
        </div>
        <CampaniaEtapaForm v-model="dialogVisible" :dataForm="dataForm" @saved="handleSaved"/>
    </App>
</template>
