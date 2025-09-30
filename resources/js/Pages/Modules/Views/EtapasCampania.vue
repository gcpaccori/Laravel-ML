<script setup>
import { onMounted, ref } from "vue";
import { ElMessage, ElMessageBox } from 'element-plus';
import { Delete, Edit, Check, View } from '@element-plus/icons-vue'
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
    console.log(data);

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
    console.log(res);
    // await loadEspecieEtapas( res.campania_especie_id )
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
                                <span class="card-label fs-5 fw-bold text-dark">{{ item.especie.nombre }} - <span class="text-uppercase">{{ campania.sistema_crianza }}</span></span>
                                <span class="text-gray-700 mt-3 fw-semibold fs-6">
                                    <span>Fecha Siembra: {{ item.fecha_siembra_formateada }}</span> |
                                    <span>N° alevines inicial: {{ item.cantidad_siembra }}</span> |
                                    <span>N° peces final: {{ item.cantidad_cosechada }}</span> |
                                    <span>Peso inicial Alevin: {{ item.peso_inicial_gr }} g</span> |
                                    <span>Peso Final Pez: {{ item.peso_final_gr }} g</span>
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
                                    <el-table-column label="Etapa" min-width="100">
                                        <template #default="{ row }">
                                            {{row.etapa?.nombre}}
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Piscina" min-width="130">
                                        <template #default="{ row }">
                                            {{ row.piscina?.nombre }}
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Fecha Inicio" min-width="130">
                                        <template #default="{ row }">
                                            {{ row.fecha_inicio_formato }}
                                        </template>
                                    </el-table-column>

                                    <el-table-column label="Fecha Fin" min-width="130">
                                        <template #default="{ row }">
                                            {{ row.fecha_fin_formato }}
                                        </template>
                                    </el-table-column>

                                    <el-table-column class-name="text-center" prop="numero_peces_inicial" label="Cantidad Inicial" min-width="130"/>
                                    <el-table-column class-name="text-center" prop="numero_peces_final" label="Cantidad Final"  min-width="130"/>
                                    <el-table-column class-name="text-center" prop="peso_inicial_gr" label="Peso Inicial (g)" min-width="130"/>
                                    <el-table-column class-name="text-center" prop="peso_final_gr" label="Peso Final (g)" min-width="130"/>
                                    <el-table-column class-name="text-center" prop="densidad_siembra" label="Densidad (Peces/m3)" min-width="130"/>
                                    <el-table-column class-name="text-center" label="Parámetros" min-width="130">
                                        <template #default="{ row }">
                                            <el-button @click="showProduccion( row.id )" icon="Setting" type="success" size="small" round>Parámetros</el-button>
                                        </template>
                                    </el-table-column>
                                    <el-table-column class-name="text-center" label="Estado" min-width="130">
                                        <template #default="{ row }">
                                            <el-tag v-if="row.estado === 'planificada'" type="info" effect="dark" size="small" round>Planificada</el-tag>
                                            <el-tag v-if="row.estado === 'en_proceso'" type="warning" effect="dark" size="small" round>En Proceso</el-tag>
                                            <el-tag v-if="row.estado === 'finalizada'" type="success" effect="dark" size="small" round>Finalizada</el-tag>
                                            <el-tag v-if="row.estado === 'cancelada'" type="danger" effect="dark" size="small" round>Cancelada</el-tag>
                                        </template>
                                    </el-table-column>

                                    <el-table-column class-name="text-center" label="Opciones" min-width="130">
                                        <template #default="{ row }">
                                            <el-button-group class="ml-4">
                                                <el-button @click="handleEdit( row.id, campania.piscigranja_id )" type="primary" size="small" :icon="Edit" />
                                                <el-button @click="handleDelete( row.id, row.campania_especie_id )" type="danger" size="small" :icon="Delete" />
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
        <ParametroProduccionForm v-model="dialogProduccion" :dataForm="dataFormProduccion" @saved="handleSavedProduccion"/>
    </App>
</template>
