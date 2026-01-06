<script setup>
import { router } from "@inertiajs/vue3";
import { onMounted, onBeforeUnmount, ref } from "vue";
import { ElMessage, ElMessageBox } from 'element-plus';

const props = defineProps({
    title: String,
    stats: Object,
    toolbar: {
        type: Array,
        required: false
    },
    columns: {
        type: Object,
        required: false
    },
});

const tableInstance = ref(null);
const ajaxUrl = route('seguridad.logs.datatable');

// FUNCIONES DE ACCIONES (BOTONES)
const reloadTable = () => {
    tableInstance.value.loadData();
};

const downloadLogs = () => {
    window.location.href = route('seguridad.logs.download');
};

const handleDelete = async() => {
    ElMessageBox.confirm(
        '¿Estás seguro de que deseas limpiar todos los logs? Esta acción no se puede deshacer.',
        'Advertencia',
        {
            confirmButtonText: 'Limpiar',
            cancelButtonText: 'Cancelar',
            type: 'warning',
            center: true,
        }
    ).then(async () => {
        const response = await axios.post(route('seguridad.logs.clear'));
        ElMessage({
            message: response.data.message,
            type: response.data.success ? 'success' : 'error',
        });
        reloadTable();
    }).catch((e) => {
        console.log(e);
    });
};

// Manejar el toggle de mensajes con Bootstrap Collapse
const setupMessageToggle = () => {
    document.addEventListener('shown.bs.collapse', handleCollapseShown);
    document.addEventListener('hidden.bs.collapse', handleCollapseHidden);
};

const handleCollapseShown = (event) => {
    if (event.target.id.startsWith('log-message-')) {
        const button = document.querySelector(`[data-bs-target="#${event.target.id}"]`);
        if (button) {
            const icon = button.querySelector('i');
            const previewId = button.dataset.previewId;
            const previewElement = document.getElementById(previewId);

            if (icon) {
                icon.className = 'bi bi-x-lg';
                button.innerHTML = '<i class="bi bi-x-lg"></i> Ocultar';
            }
            if (previewElement) {
                previewElement.style.display = 'none';
            }
        }
    }
};

const handleCollapseHidden = (event) => {
    if (event.target.id.startsWith('log-message-')) {
        const button = document.querySelector(`[data-bs-target="#${event.target.id}"]`);
        if (button) {
            const icon = button.querySelector('i');
            const previewId = button.dataset.previewId;
            const previewElement = document.getElementById(previewId);

            if (icon) {
                icon.className = 'bi bi-search';
                button.innerHTML = '<i class="bi bi-search"></i> Ver completo';
            }
            if (previewElement) {
                previewElement.style.display = 'block';
            }
        }
    }
};

const cleanupMessageToggle = () => {
    document.removeEventListener('shown.bs.collapse', handleCollapseShown);
    document.removeEventListener('hidden.bs.collapse', handleCollapseHidden);
};

onMounted(() => {
    setupMessageToggle();
});

onBeforeUnmount(() => {
    cleanupMessageToggle();
});
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <!-- Estadísticas -->
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card border-primary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Total</h6>
                        <h3 class="mb-0">{{ stats.total }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-danger">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Errores</h6>
                        <h3 class="mb-0 text-danger">{{ stats.error }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-warning">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Warnings</h6>
                        <h3 class="mb-0 text-warning">{{ stats.warning }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-info">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Info</h6>
                        <h3 class="mb-0 text-info">{{ stats.info }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-secondary">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Debug</h6>
                        <h3 class="mb-0 text-secondary">{{ stats.debug }}</h3>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card border-dark">
                    <div class="card-body text-center">
                        <h6 class="text-muted mb-2">Critical</h6>
                        <h3 class="mb-0 text-dark">{{ stats.critical }}</h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Botones de acción -->
        <template #btnCreate>
            <div class="row">
                <div class="col-lg-12">
                    <el-button size="small" icon="Refresh" type="success" @click="reloadTable">
                        Recargar
                    </el-button>
                    <el-button size="small" icon="Delete" type="danger" @click="handleDelete">
                        Limpiar archivo
                    </el-button>
                    <el-button size="small" icon="Download" type="primary" @click="downloadLogs">
                        Descargar archivo
                    </el-button>
                </div>
            </div>
        </template>

        <!-- Tabla de logs -->
        <BaseDataTable
            ref="tableInstance"
            :ajax-url="ajaxUrl"
            :columns="columns"
        ></BaseDataTable>
    </App>
</template>

<style scoped>
:deep(.log-message-container) {
    width: 100%;
}

:deep(.log-message-preview) {
    word-wrap: break-word;
    overflow-wrap: break-word;
}

:deep(.log-message-full pre) {
    white-space: pre-wrap;
    word-wrap: break-word;
    margin: 0;
}

:deep(.toggle-message) {
    transition: all 0.3s ease;
    text-decoration: none;
    color: #0d6efd;
}

:deep(.toggle-message:hover) {
    text-decoration: underline;
    color: #0a58ca;
}

:deep(.collapse) {
    transition: height 0.35s ease;
}

:deep(.collapsing) {
    transition: height 0.35s ease;
}

/* Animación para el icono */
:deep(.toggle-message i) {
    transition: transform 0.3s ease;
}
</style>
