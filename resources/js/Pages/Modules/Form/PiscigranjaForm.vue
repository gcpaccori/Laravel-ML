<script setup>
import { onMounted, ref, watch } from 'vue';
import useSubmitForm from '@/Composables/useSubmitForm';
import { ElMessage } from 'element-plus';
import useUbigeo from '@/Composables/useUbigeo';
import { Delete, Plus, Location, InfoFilled, HomeFilled } from '@element-plus/icons-vue';

const { loading, progress, errors, submitForm } = useSubmitForm();
const { loadDepartamentosTo, loadProvinciasTo, loadDistritosTo } = useUbigeo();

const props = defineProps({
    modelValue: Boolean,
    dataForm: {
        type: Object,
        required: false,
        default: null
    },
});

const emit = defineEmits(['update:modelValue', 'saved']);

const dialogVisible = ref(props.modelValue);
const departamentos = ref([]);
const provincias = ref([]);
const distritos = ref([]);

const initialForm = () => ({
    nombre: '',
    descripcion: '',
    departamento_id: '',
    provincia_id: '',
    distrito_id: '',
    direccion: '',
    latitud: '',
    longitud: '',
    propietario: '',
    telefono_contacto: '',
    email_contacto: '',
    activo: true,
    piscinas: []
});

const form = ref(initialForm());

// Helper para obtener mensajes de error de validación
const getError = (path) => {
    return errors.value?.[path]?.[0] || '';
};

// Carga de ubicación geográfica
const handleDepartamentoChange = async (departamentoId) => {
    form.value.provincia_id = '';
    form.value.distrito_id = '';
    provincias.value = [];
    distritos.value = [];
    if (departamentoId) {
        await loadProvinciasTo(departamentoId, provincias);
    }
};

const handleProvinciaChange = async (provinciaId) => {
    form.value.distrito_id = '';
    distritos.value = [];
    if (provinciaId) {
        await loadDistritosTo(provinciaId, distritos);
    }
};

const submitFormulario = () => {
    const routeName = props.dataForm ? 'piscigranjas.update' : 'piscigranjas.store';
    submitForm({
        url: props.dataForm ? route(routeName, props.dataForm.id) : route(routeName),
        data: form.value,
        method: props.dataForm ? 'put' : 'post',
        emit,
        onSuccess: (response) => {
            dialogVisible.value = false;
            ElMessage({
                message: response.data.message || 'Operación realizada con éxito',
                type: 'success',
            });
            resetForm();
        }
    });
};

const resetForm = () => {
    form.value = initialForm();
    if (errors.value) errors.value = {};
};

// Agregar y eliminar piscinas
const addPiscina = () => {
    const nuevaPiscina = ref({
        nombre: '',
        descripcion: '',
        superficie_m2: 0,
        profundidad_m: 0,
        volumen_m3: 0,
        estado: 'operativa',
    });

    // Recalcular volumen automáticamente cuando cambia superficie o profundidad
    watch(
        () => [nuevaPiscina.value.superficie_m2, nuevaPiscina.value.profundidad_m],
        ([sup, prof]) => {
            const superficie = parseFloat(sup) || 0;
            const profundidad = parseFloat(prof) || 0;
            nuevaPiscina.value.volumen_m3 = parseFloat((superficie * profundidad).toFixed(2));
        },
        { immediate: true }
    );

    form.value.piscinas.push(nuevaPiscina.value);
};

const eliminarPiscina = (index) => {
    form.value.piscinas.splice(index, 1);
};

// Sincronizar reactividad del modal y edición de formulario
watch(() => props.modelValue, async (val) => {
    dialogVisible.value = val;
    if (val) {
        resetForm();
        if (props.dataForm) {
            form.value = JSON.parse(JSON.stringify(props.dataForm));

            // Re-vincular watchers de volumen a los items cargados
            form.value.piscinas.forEach((piscina) => {
                watch(
                    () => [piscina.superficie_m2, piscina.profundidad_m],
                    ([sup, prof]) => {
                        const superficie = parseFloat(sup) || 0;
                        const profundidad = parseFloat(prof) || 0;
                        piscina.volumen_m3 = parseFloat((superficie * profundidad).toFixed(2));
                    }
                );
            });

            if (form.value.departamento_id) {
                await loadProvinciasTo(form.value.departamento_id, provincias);
            }
            if (form.value.provincia_id) {
                await loadDistritosTo(form.value.provincia_id, distritos);
            }
        }
    }
});

watch(dialogVisible, (val) => {
    emit('update:modelValue', val);
});

onMounted(async () => {
    await loadDepartamentosTo(departamentos);
});
</script>

<template>
    <DialogForm v-model="dialogVisible" :title="props.dataForm ? 'Editar Piscigranja' : 'Registrar Piscigranja'" width="75%">
        <el-form @submit.prevent="submitFormulario" :model="form" label-position="top" require-asterisk-position="right">

            <!-- Sección: Información General -->
            <el-divider content-position="left">
                <span>Información General</span>
            </el-divider>

            <div class="row">
                <div class="col-md-4">
                    <el-form-item label="Nombre" :error="getError('nombre')" required>
                        <el-input v-model="form.nombre" placeholder="Ej. Piscigranja San Pedro" clearable />
                    </el-form-item>
                </div>

                <div class="col-md-4">
                    <el-form-item label="Descripción" :error="getError('descripcion')">
                        <el-input v-model="form.descripcion" placeholder="Detalles de la instalación" />
                    </el-form-item>
                </div>

                <div class="col-md-4">
                    <el-form-item label="Propietario" :error="getError('propietario')">
                        <el-input v-model="form.propietario" placeholder="Nombre completo" />
                    </el-form-item>
                </div>

                <div class="col-md-4">
                    <el-form-item label="Teléfono de contacto" :error="getError('telefono_contacto')">
                        <el-input v-model="form.telefono_contacto" placeholder="Ej. 987654321" />
                    </el-form-item>
                </div>

                <div class="col-md-4">
                    <el-form-item label="Correo Electrónico" :error="getError('email_contacto')">
                        <el-input v-model="form.email_contacto" placeholder="correo@ejemplo.com" type="email" />
                    </el-form-item>
                </div>

                <div class="col-md-4 d-flex align-items-center">
                    <el-form-item label="Estado del registro" :error="getError('activo')" class="mb-0">
                        <el-switch
                            v-model="form.activo"
                            inline-prompt
                            style="--el-switch-on-color: #13ce66; --el-switch-off-color: #ff4949"
                            active-text="Activo"
                            inactive-text="Inactivo"
                        />
                    </el-form-item>
                </div>
            </div>

            <!-- Sección: Ubicación -->
            <el-divider content-position="left">
                <span>Ubicación Geográfica</span>
            </el-divider>

            <div class="row">
                <div class="col-md-4">
                    <el-form-item label="Departamento" :error="getError('departamento_id')" required>
                        <el-select v-model="form.departamento_id" placeholder="Seleccione departamento" filterable class="w-100" @change="handleDepartamentoChange">
                            <el-option
                                v-for="item in departamentos"
                                :key="item.id"
                                :label="item.name"
                                :value="item.id"
                            />
                        </el-select>
                    </el-form-item>
                </div>

                <div class="col-md-4">
                    <el-form-item label="Provincia" :error="getError('provincia_id')" required>
                        <el-select v-model="form.provincia_id" placeholder="Seleccione provincia" filterable class="w-100" :disabled="!form.departamento_id" @change="handleProvinciaChange">
                            <el-option
                                v-for="item in provincias"
                                :key="item.id"
                                :label="item.name"
                                :value="item.id"
                            />
                        </el-select>
                    </el-form-item>
                </div>

                <div class="col-md-4">
                    <el-form-item label="Distrito" :error="getError('distrito_id')" required>
                        <el-select v-model="form.distrito_id" placeholder="Seleccione distrito" filterable class="w-100" :disabled="!form.provincia_id">
                            <el-option
                                v-for="item in distritos"
                                :key="item.id"
                                :label="item.name"
                                :value="item.id"
                            />
                        </el-select>
                    </el-form-item>
                </div>

                <div class="col-md-6">
                    <el-form-item label="Dirección / Referencia" :error="getError('direccion')">
                        <el-input v-model="form.direccion" placeholder="Av. Principal S/N..." />
                    </el-form-item>
                </div>

                <div class="col-md-3">
                    <el-form-item label="Latitud" :error="getError('latitud')">
                        <el-input v-model="form.latitud" placeholder="-6.4889" />
                    </el-form-item>
                </div>

                <div class="col-md-3">
                    <el-form-item label="Longitud" :error="getError('longitud')">
                        <el-input v-model="form.longitud" placeholder="-76.3653" />
                    </el-form-item>
                </div>
            </div>

            <!-- Sección: Piscinas -->
            <div class="d-flex justify-content-between align-items-center mb-2">
                <el-divider content-position="left" class="mb-0">
                    <span>Lista de Piscinas</span>
                </el-divider>
            </div>

            <div class="d-flex justify-content-end mb-3">
                <el-button type="primary" size="small" :icon="Plus" plain @click="addPiscina">
                    Agregar Piscina
                </el-button>
            </div>

            <div class="table-responsive">
                <el-table :data="form.piscinas" border stripe style="width: 100%">
                    <el-table-column label="Nombre" min-width="150">
                        <template #default="{ row, $index }">
                            <el-form-item :error="getError(`piscinas.${$index}.nombre`)" class="mb-0">
                                <el-input v-model="row.nombre" placeholder="Nombre piscina" size="small" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <el-table-column label="Descripción" min-width="150">
                        <template #default="{ row, $index }">
                            <el-form-item :error="getError(`piscinas.${$index}.descripcion`)" class="mb-0">
                                <el-input v-model="row.descripcion" placeholder="Notas" size="small" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <el-table-column label="Superficie (m²)" width="150">
                        <template #default="{ row, $index }">
                            <el-form-item :error="getError(`piscinas.${$index}.superficie_m2`)" class="mb-0">
                                <el-input type="number" v-model="row.superficie_m2" :min="0" step="any" size="small" class="w-100" controls-position="right" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <el-table-column label="Profundidad (m)" width="150">
                        <template #default="{ row, $index }">
                            <el-form-item :error="getError(`piscinas.${$index}.profundidad_m`)" class="mb-0">
                                <el-input type="number" v-model="row.profundidad_m" :min="0" step="any" size="small" class="w-100" controls-position="right" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <el-table-column label="Volumen (m³)" width="140">
                        <template #default="{ row, $index }">
                            <el-form-item :error="getError(`piscinas.${$index}.volumen_m3`)" class="mb-0">
                                <el-input type="number" v-model="row.volumen_m3" disabled size="small" placeholder="Autocalculado" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <el-table-column label="Estado" width="150">
                        <template #default="{ row, $index }">
                            <el-form-item :error="getError(`piscinas.${$index}.estado`)" class="mb-0">
                                <el-select v-model="row.estado" placeholder="Estado" size="small">
                                    <el-option label="Operativa" value="operativa" />
                                    <el-option label="Mantenimiento" value="mantenimiento" />
                                    <el-option label="Inactiva" value="inactiva" />
                                </el-select>
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <el-table-column label="Acciones" width="100" align="center">
                        <template #default="{ row, $index }">
                            <el-tooltip content="Eliminar fila" placement="top" :hide-after="1000">
                                <el-button
                                    type="danger"
                                    :icon="Delete"
                                    :disabled="Boolean(row.id && row.id > 0)"
                                    @click="eliminarPiscina($index)"
                                    circle
                                    size="small"
                                />
                            </el-tooltip>
                        </template>
                    </el-table-column>
                </el-table>
            </div>

            <!-- Acciones / Footer -->
            <div class="d-flex justify-content-end gap-2 mt-4">
                <el-button size="small" type="primary" native-type="submit" :loading="loading">
                    {{ props.dataForm ? 'Actualizar Piscigranja' : 'Registrar Piscigranja' }}
                </el-button>
                <el-button size="small" type="danger" plain @click="dialogVisible = false">Cancelar</el-button>
            </div>
        </el-form>
    </DialogForm>
</template>
