<script setup>
import { onMounted, ref, watch } from 'vue';
import useSubmitForm from '@/Composables/useSubmitForm';
import { ElMessage, ElMessageBox } from 'element-plus';

const { loading, progress, errors, submitForm } = useSubmitForm();

const props = defineProps({
    modelValue: Boolean,
    dataForm: {
        type: Object,
        required: false,
        default: null
    }
});

const dialogVisible = ref(props.modelValue);
const optionsPiscigranjas = ref([]);
const optionsEspecies = ref([]);

const form = ref({
    piscigranja_id: '',
    nombre: '',
    sistema_crianza: 'monofasico',
    fecha_inicio: '',
    fecha_fin_estimada: '',
    fecha_fin_real: '',
    estado: 'planificada',
    especies: []
});

const emit = defineEmits(['update:modelValue', 'saved', 'reload-table']);

// Carga de opciones
const piscigranjasOptions = async () => {
    try {
        const response = await axios.get(route('piscigranjas.options'));
        optionsPiscigranjas.value = response.data.data;
    } catch (e) {
        console.error(e);
    }
};

const especiesOptions = async () => {
    try {
        const response = await axios.get(route('especies.options'));
        optionsEspecies.value = response.data;
    } catch (e) {
        console.error(e);
    }
};

const submitFormulario = () => {
    const routeName = props.dataForm ? 'campanias.update' : 'campanias.store';
    submitForm({
        url: props.dataForm
            ? route(routeName, props.dataForm.id)
            : route(routeName),
        data: form.value,
        method: props.dataForm ? 'put' : 'post',
        emit,
        onSuccess: (response) => {
            dialogVisible.value = false;
            ElMessage({
                message: response.data.message,
                type: 'success',
            });
            resetForm();
        }
    });
};

const resetForm = () => {
    form.value = {
        piscigranja_id: '',
        nombre: '',
        sistema_crianza: 'monofasico',
        fecha_inicio: '',
        fecha_fin_estimada: '',
        fecha_fin_real: '',
        estado: 'planificada',
        especies: []
    };
    errors.value = {};
};

// Agregar nueva especie editable
const addEspecieCampania = () => {
    form.value.especies.push({
        especie_id: '',
        cantidad_siembra: 0,
        fecha_siembra: '',
        cantidad_cosechada: 0,
        peso_inicial_gr: 0.00,
        peso_final_gr: 0.00
    });
};

const delEspecieCampania = (index, id) => {
    if (id && id > 0) {
        ElMessageBox.confirm(
            '¿Estás seguro de que deseas eliminar esta especie de la campaña?',
            'Confirmación de eliminación',
            {
                confirmButtonText: 'Eliminar',
                cancelButtonText: 'Cancelar',
                type: 'warning',
                center: true,
            }
        ).then(async () => {
            try {
                const response = await axios.delete(route('campanias.especies.destroy', id));
                ElMessage({
                    message: response.data.message,
                    type: response.data.success ? 'success' : 'error',
                });

                if (response.data.success) {
                    form.value.especies.splice(index, 1);
                    emit('reload-table');
                }
            } catch (e) {
                console.error(e);
            }
        }).catch(() => {});
    } else {
        form.value.especies.splice(index, 1);
    }
};

const isRegistered = (row) => {
    return !!row.id;
};

// Sincronizar modal con prop
watch(() => props.modelValue, async (val) => {
    dialogVisible.value = val;
    if (val) {
        resetForm();
        if (props.dataForm) {
            form.value = JSON.parse(JSON.stringify(props.dataForm));
        }
    }
});

watch(dialogVisible, (val) => {
    emit('update:modelValue', val);
});

onMounted(async () => {
    await piscigranjasOptions();
    await especiesOptions();
});
</script>

<template>
    <el-form @submit.prevent="submitFormulario" :model="form" label-position="top">
        <DialogForm v-model="dialogVisible" :title="props.dataForm ? 'Editar Campaña' : 'Registrar Campaña'" width="75%">
            <el-divider content-position="left">
                <span class="fw-bold text-uppercase fs-7 text-muted">Información General</span>
            </el-divider>
            <div class="row g-3">
                <div class="col-12 col-md-4">
                    <el-form-item label="Piscigranja" :error="errors.piscigranja_id?.[0]" required>
                        <el-select v-model="form.piscigranja_id" placeholder="Seleccione una piscigranja" filterable class="w-100">
                            <el-option
                                v-for="item in optionsPiscigranjas"
                                :key="item.id"
                                :label="item.nombre"
                                :value="item.id"
                            />
                        </el-select>
                    </el-form-item>
                </div>
                <div class="col-12 col-md-4">
                    <el-form-item label="Nombre de Campaña" :error="errors.nombre?.[0]" required>
                        <el-input v-model="form.nombre" placeholder="Ej. Campaña Verano 2026" />
                    </el-form-item>
                </div>
                <div class="col-12 col-md-4">
                    <el-form-item label="Sistema de Crianza" :error="errors.sistema_crianza?.[0]" required>
                        <el-select v-model="form.sistema_crianza" placeholder="Seleccione sistema" class="w-100">
                            <el-option label="Monofásico" value="monofasico" />
                            <el-option label="Bifásico" value="bifasico" />
                            <el-option label="Trifásico" value="trifasico" />
                        </el-select>
                    </el-form-item>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <el-form-item label="Fecha de Inicio" :error="errors.fecha_inicio?.[0]" required>
                        <el-date-picker
                            class="w-100"
                            type="date"
                            v-model="form.fecha_inicio"
                            format="DD/MM/YYYY"
                            value-format="YYYY-MM-DD"
                            placeholder="dd/mm/aaaa"
                            :clearable="false"
                        />
                    </el-form-item>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <el-form-item label="Fecha Fin Estimada" :error="errors.fecha_fin_estimada?.[0]">
                        <el-date-picker
                            class="w-100"
                            type="date"
                            v-model="form.fecha_fin_estimada"
                            format="DD/MM/YYYY"
                            value-format="YYYY-MM-DD"
                            placeholder="dd/mm/aaaa"
                            :clearable="false"
                        />
                    </el-form-item>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <el-form-item label="Fecha Fin Real" :error="errors.fecha_fin_real?.[0]">
                        <el-date-picker
                            class="w-100"
                            type="date"
                            v-model="form.fecha_fin_real"
                            format="DD/MM/YYYY"
                            value-format="YYYY-MM-DD"
                            placeholder="dd/mm/aaaa"
                            :clearable="false"
                        />
                    </el-form-item>
                </div>
                <div class="col-12 col-sm-6 col-md-3">
                    <el-form-item label="Estado" :error="errors.estado?.[0]" required>
                        <el-select v-model="form.estado" placeholder="Seleccione estado" class="w-100">
                            <el-option label="Planificada" value="planificada" />
                            <el-option label="En Proceso" value="en_proceso" />
                            <el-option label="Finalizada" value="finalizada" />
                            <el-option label="Cancelada" value="cancelada" />
                        </el-select>
                    </el-form-item>
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-4 mb-5">
                <el-divider content-position="left" class="mb-0 flex-grow-1 me-3">
                    <span class="fw-bold text-uppercase fs-7 text-muted">Especies Asociadas</span>
                </el-divider>
                <el-button
                    type="primary"
                    plain
                    size="small"
                    icon="Plus"
                    @click="addEspecieCampania"
                >
                    Agregar Especie
                </el-button>
            </div>
            <div class="row">
                <div class="col-12">
                    <el-table
                        :data="form.especies"
                        border
                        stripe
                        class="w-100"
                        empty-text="No hay especies registradas. Haga clic en 'Agregar Especie'."
                    >
                        <el-table-column label="Especie" min-width="180">
                            <template #default="{ row, $index }">
                                <el-form-item size="small" class="mb-0" :error="errors[`especies.${$index}.especie_id`]?.[0] ?? errors[`especies`]?.[0]">
                                    <el-select
                                        v-model="row.especie_id"
                                        placeholder="Seleccionar..."
                                        filterable
                                        class="w-100"
                                        :disabled="isRegistered(row)"
                                    >
                                        <el-option
                                            v-for="item in optionsEspecies"
                                            :key="item.id"
                                            :label="item.nombre"
                                            :value="item.id"
                                        />
                                    </el-select>
                                </el-form-item>
                            </template>
                        </el-table-column>

                        <el-table-column label="Fecha Siembra" min-width="140">
                            <template #default="{ row, $index }">
                                <el-form-item size="small" class="mb-0" :error="errors[`especies.${$index}.fecha_siembra`]?.[0]">
                                    <el-date-picker
                                        class="w-100"
                                        type="date"
                                        v-model="row.fecha_siembra"
                                        format="DD/MM/YYYY"
                                        value-format="YYYY-MM-DD"
                                        :clearable="false"
                                        :disabled="isRegistered(row)"
                                    />
                                </el-form-item>
                            </template>
                        </el-table-column>

                        <el-table-column label="Alevines Inicial" min-width="130">
                            <template #default="{ row, $index }">
                                <el-form-item size="small" class="mb-0" :error="errors[`especies.${$index}.cantidad_siembra`]?.[0]">
                                    <el-input-number
                                        v-model="row.cantidad_siembra"
                                        :min="0"
                                        :controls="false"
                                        class="w-100 text-start"
                                        :disabled="isRegistered(row)"
                                    />
                                </el-form-item>
                            </template>
                        </el-table-column>

                        <el-table-column min-width="130">
                            <template #header>
                                <span class="d-inline-flex align-items-center gap-1">
                                    Peces Final
                                    <el-tooltip
                                        content="Cantidad esperada al finalizar (valor de referencia / aproximación)"
                                        placement="top"
                                    >
                                        <el-icon class="text-muted"><QuestionFilled /></el-icon>
                                    </el-tooltip>
                                </span>
                            </template>

                            <template #default="{ row, $index }">
                                <el-form-item size="small" class="mb-0" :error="errors[`especies.${$index}.cantidad_cosechada`]?.[0]">
                                    <el-input-number
                                        v-model="row.cantidad_cosechada"
                                        :min="0"
                                        :controls="false"
                                        class="w-100 text-start"
                                        :disabled="isRegistered(row)"
                                    />
                                </el-form-item>
                            </template>
                        </el-table-column>

                        <el-table-column label="Peso Inicial (g)" min-width="130">
                            <template #default="{ row, $index }">
                                <el-form-item size="small" class="mb-0" :error="errors[`especies.${$index}.peso_inicial_gr`]?.[0]">
                                    <el-input-number
                                        v-model="row.peso_inicial_gr"
                                        :precision="2"
                                        :step="0.1"
                                        :min="0"
                                        :controls="false"
                                        class="w-100 text-start"
                                        :disabled="isRegistered(row)"
                                    />
                                </el-form-item>
                            </template>
                        </el-table-column>

                        <el-table-column min-width="130">
                            <template #header>
                                <span class="d-inline-flex align-items-center gap-1">
                                    Peso Final (g)
                                    <el-tooltip
                                        content="Peso esperado al finalizar (valor de referencia / aproximación)"
                                        placement="top"
                                    >
                                        <el-icon class="text-muted"><QuestionFilled /></el-icon>
                                    </el-tooltip>
                                </span>
                            </template>

                            <template #default="{ row, $index }">
                                <el-form-item size="small" class="mb-0" :error="errors[`especies.${$index}.peso_final_gr`]?.[0]">
                                    <el-input-number
                                        v-model="row.peso_final_gr"
                                        :precision="2"
                                        :step="0.1"
                                        :min="0"
                                        :controls="false"
                                        class="w-100 text-start"
                                        :disabled="isRegistered(row)"
                                    />
                                </el-form-item>
                            </template>
                        </el-table-column>

                        <el-table-column label="Acciones" width="100" align="center" fixed="right">
                            <template #default="{ row, $index }">
                                <el-button
                                    type="danger"
                                    plain
                                    icon="Delete"
                                    @click="delEspecieCampania($index, row.id)"
                                    size="small"
                                />
                            </template>
                        </el-table-column>
                    </el-table>
                </div>
            </div>

            <!-- Footer con acciones del Dialog -->
            <template #footer>
                <div class="d-flex justify-content-end gap-2 pt-3">
                    <el-button size="small" @click="dialogVisible = false">Cancelar</el-button>
                    <el-button size="small" type="primary" native-type="submit" :loading="loading">
                        {{ props.dataForm ? 'Actualizar' : 'Guardar Registros' }}
                    </el-button>
                </div>
            </template>
        </DialogForm>
    </el-form>
</template>

<style scoped>
/* Elimina espacio excedente dentro de las celdas de la tabla para optimizar espacio */
:deep(.el-table .el-form-item__error) {
    position: relative;
    font-size: 11px;
    padding-top: 2px;
}
</style>
