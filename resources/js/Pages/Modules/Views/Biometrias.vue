<script setup>
import { router } from "@inertiajs/vue3";
import { onMounted, watch, ref } from "vue";
import { ElMessage, ElMessageBox } from "element-plus";
import { useDynamicAction } from "@/Composables/useDynamicAction";

const props = defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false,
    },
    columns: {
        type: Object,
        required: false,
    },
    accionesGrilla: {
        type: Array,
        required: false,
        default: [],
    },
});

const tableInstance = ref(null);
const ajaxUrl = route("datatable.biometrias");
const optionsPiscigranjas = ref(null);
const optionsCampanias = ref(null);
const optionsEspecies = ref(null);
const loadingExport = ref(false);
const formInline = ref({
    piscigranja_id: null,
    campania_id: null,
    especie_id: null,
    rango_fecha: [],
});

const showModal = () => {
    router.visit(route("biometrias.create"));
};

// Inicializar Tabla
const handleTableReady = (dt) => {
    tableInstance.value = dt;
};

const reloadTable = () => {
    if (tableInstance.value) {
        tableInstance.value.ajax.reload(null, true);
    }
};

// FUNCIONES DE ACCIONES (BOTONES)
const handleEdit = (id) => {
    router.visit(route("biometrias.edit", id));
};

const handleShow = (id) => {
    router.visit(route("biometrias.show", id));
};

const handleDelete = async (id) => {
    ElMessageBox.confirm(
        "¿Estás seguro de que deseas eliminar este registro?",
        "Advertencia",
        {
            confirmButtonText: "Eliminar",
            cancelButtonText: "Cancelar",
            type: "warning",
            center: true,
        },
    )
        .then(async () => {
            const response = await axios.delete(
                route("biometrias.destroy", id),
            );
            ElMessage({
                message: response.data.message,
                type: response.data.success ? "success" : "error",
            });
            reloadTable();
        })
        .catch((e) => {
            console.log(e);
        });
};

const handlePdf = (id) => {
    const url = route("biometrias.pdf", id);
    window.open(url, "_blank");
};

const piscigranjasOptions = async () => {
    optionsCampanias.value = null;
    optionsEspecies.value = null;
    try {
        const response = await axios.get(route("piscigranjas.options"));
        optionsPiscigranjas.value = response.data.data;
    } catch (error) {
        console.error("Error al cargar piscigranjas:", error);
    }
};

const campaniasOptions = async () => {
    const piscigranjaId = formInline.value?.piscigranja_id;

    if (!piscigranjaId) {
        optionsCampanias.value = null;
        optionsEspecies.value = null;
        formInline.value.campania_id = null;
        formInline.value.especie_id = null;
        return;
    }

    try {
        optionsEspecies.value = null;
        const response = await axios.get(
            route("campania.active.show", piscigranjaId),
        );
        optionsCampanias.value = response.data;
    } catch (error) {
        console.error("Error al cargar campañas:", error);
    }
};

const especiesOptions = async () => {
    const campaniaId = formInline.value?.campania_id;

    if (!campaniaId) {
        optionsEspecies.value = null;
        formInline.value.especie_id = null;
        return;
    }

    try {
        const response = await axios.get(
            route("especie.active.show", campaniaId),
        );
        optionsEspecies.value = response.data;
    } catch (error) {
        console.error("Error al cargar especies:", error);
    }
};

// FUNCIONES EXPUESTAS DEL BASEDATATABLE
const methods = {
    handleEdit,
    handleDelete,
    handlePdf,
    handleShow,
};

const { handleDynamicAction } = useDynamicAction(methods);

function handleActionGrilla(nombre) {
    const funciones = {
        handleNew: showModal,
    };

    if (funciones[nombre]) {
        funciones[nombre]();
    } else {
        console.warn(`No se encontró la función '${nombre}'`);
    }
}

const limpiarFiltros = () => {
    formInline.value.piscigranja_id = null;
    formInline.value.campania_id = null;
    formInline.value.especie_id = null;
    formInline.value.rango_fecha = [];
    optionsCampanias.value = null;
    optionsEspecies.value = null;
};

const exportarArchivo = async (routeName, fileName) => {
    loadingExport.value = true;
    try {
        if (fileName === "excel") {
            window.location.href = route(routeName, formInline.value);
        } else {
            window.location.href = route(routeName, formInline.value);
        }
    } catch (error) {
        console.error("Error al exportar:", error);
    } finally {
        setTimeout(() => (loadingExport.value = false), 1500);
    }
};

onMounted(() => {
    piscigranjasOptions();
});

watch(
    formInline,
    () => {
        if (tableInstance.value) {
            tableInstance.value.ajax.reload(null, true);
        }
    },
    { deep: true },
);
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <template #btnCreate>
            <template v-for="(action, index) in accionesGrilla" :key="index">
                <el-button
                    :icon="action.icon"
                    :type="action.type"
                    class="mr-2"
                    size="small"
                    @click="handleActionGrilla(action.name_funcion)"
                >
                    {{ action.action }}
                </el-button>
            </template>
        </template>

        <BaseDataTable
            :ajax-url="ajaxUrl"
            :columns="columns"
            :filters="formInline"
            @tableReady="handleTableReady"
            @action="handleDynamicAction"
        >
            <template #filters>
                <el-form
                    :model="formInline"
                    label-position="top"
                    class="w-100"
                    size="small"
                >
                    <el-row :gutter="20">
                        <el-col :xs="24" :sm="12" :md="6">
                            <el-form-item label="Fecha de Muestreo">
                                <el-date-picker
                                    v-model="formInline.rango_fecha"
                                    type="daterange"
                                    range-separator="a"
                                    start-placeholder="Inicio"
                                    end-placeholder="Fin"
                                    format="DD/MM/YYYY"
                                    value-format="YYYY-MM-DD"
                                    class="w-100"
                                />
                            </el-form-item>
                        </el-col>
                        
                        <el-col :xs="24" :sm="12" :md="6">
                            <el-form-item label="Piscigranja">
                                <el-select
                                    v-model="formInline.piscigranja_id"
                                    placeholder="Seleccionar"
                                    clearable
                                    class="w-100"
                                    @change="campaniasOptions"
                                >
                                    <el-option
                                        v-for="item in optionsPiscigranjas"
                                        :key="item.id"
                                        :label="item.nombre"
                                        :value="item.id"
                                    />
                                </el-select>
                            </el-form-item>
                        </el-col>

                        <el-col :xs="24" :sm="12" :md="6">
                            <el-form-item label="Campaña">
                                <el-select
                                    v-model="formInline.campania_id"
                                    placeholder="Seleccionar"
                                    clearable
                                    class="w-100"
                                    @change="especiesOptions"
                                >
                                    <el-option
                                        v-for="item in optionsCampanias"
                                        :key="item.id"
                                        :label="item.nombre"
                                        :value="item.id"
                                    />
                                </el-select>
                            </el-form-item>
                        </el-col>

                        <el-col :xs="24" :sm="12" :md="6">
                            <el-form-item label="Especie">
                                <el-select
                                    v-model="formInline.especie_id"
                                    placeholder="Seleccionar"
                                    clearable
                                    class="w-100"
                                >
                                    <el-option
                                        v-for="item in optionsEspecies"
                                        :key="item.id"
                                        :label="item.especie.nombre"
                                        :value="item.especie.id"
                                    />
                                </el-select>
                            </el-form-item>
                        </el-col>

                        <el-col
                            :span="24"
                            class="d-flex justify-content-center mt-2 mb-3"
                        >
                            <el-button
                                class="me-2"
                                icon="Delete"
                                type="info"
                                @click="limpiarFiltros"
                                >Limpiar</el-button
                            >
                            <el-dropdown
                                type="success"
                                size="small"
                                :disabled="loadingExport"
                            >
                                <el-button
                                    type="success"
                                    size="small"
                                    :loading="loadingExport"
                                >
                                    <el-icon v-if="!loadingExport" class="me-1"
                                        ><Download
                                    /></el-icon>
                                    {{
                                        loadingExport
                                            ? "Exportando..."
                                            : "Exportar"
                                    }}
                                    <el-icon class="el-icon--right"
                                        ><ArrowDown
                                    /></el-icon>
                                </el-button>
                                <template #dropdown>
                                    <el-dropdown-menu>
                                        <el-dropdown-item
                                            @click="
                                                exportarArchivo(
                                                    'biometrias.excel',
                                                    'excel',
                                                )
                                            "
                                        >
                                            <el-icon class="me-1"
                                                ><DocumentChecked
                                            /></el-icon>
                                            Exportar a EXCEL
                                        </el-dropdown-item>
                                        <el-dropdown-item
                                            @click="
                                                exportarArchivo(
                                                    'biometrias.csv',
                                                    'csv',
                                                )
                                            "
                                        >
                                            <el-icon class="me-1"
                                                ><Document
                                            /></el-icon>
                                            Exportar a CSV
                                        </el-dropdown-item>
                                    </el-dropdown-menu>
                                </template>
                            </el-dropdown>
                        </el-col>
                    </el-row>
                </el-form>
            </template>
        </BaseDataTable>
    </App>
</template>
