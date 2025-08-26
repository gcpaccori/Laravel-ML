<script setup>
    import { onMounted, ref, watch } from 'vue';
    import useSubmitForm from '@/Composables/useSubmitForm';
    import { ElMessage } from 'element-plus';
    import useUbigeo from '@/Composables/useUbigeo';

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

    const dialogVisible = ref(props.modelValue);
    const departamentos = ref([]);
    const provincias = ref([]);
    const distritos = ref([]);

    const tableData = ref([
        { nombre: 'Piscina 1', descripcion: 'Piscina principal', superficie_m2: 50, profundidad_m: 2, estado: true },
    ]);

    const form = ref({
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

    const emit = defineEmits(['update:modelValue', 'saved']);

    // Funciones para cargar datos de ubicación dirección
    const handleDepartamentoChange = async (departamentoId) => {
        await loadProvinciasTo(departamentoId, provincias);
        form.value.provincia_id = '';
        form.value.distrito_id = '';
        distritos.value = [];
    };

    const handleProvinciaChange = async (provinciaId) => {
        await loadDistritosTo(provinciaId, distritos);
        form.value.distrito_id = '';
    };

    const submitFormulario = () => {
        const routeName = props.dataForm ? 'piscigranjas.update' : 'piscigranjas.store';
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
        for (const key in form.value) {
            form.value[key] = '';
        }
        form.value.activo = true;
        form.value.piscinas = [];
        errors.value = {};
    };

    // Función para agregar una nueva fila editable
    const addPiscina = () => {
        form.value.piscinas.push({
            nombre: '',
            descripcion: '',
            superficie_m2: 0,
            profundidad_m: 0,
            estado: 'operativa',
            editing: true, // bandera para saber si es fila en edición
        })
    }

    // Sincronizar cambios del padre
    watch(() => props.modelValue, async (val) => {
        dialogVisible.value = val;
        if (val) {
            resetForm();
            if (props.dataForm) {
                form.value = { ...props.dataForm };
                await loadProvinciasTo(form.value.departamento_id, provincias);
                await loadDistritosTo(form.value.provincia_id, distritos);
            }
        }
    });

    // Emitir cambios al padre
    watch(dialogVisible, (val) => {
        emit('update:modelValue', val);
    });

    onMounted(  async() => {
        await loadDepartamentosTo(departamentos);
    });
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top">
    <DialogForm v-model="dialogVisible" :title="props.dataForm ? 'Editar Piscigranja' : 'Registrar Piscigranja'" width="70%">

        <h6 class="text-muted">Información General</h6>
        <div class="separator separator-dotted mb-5"></div>
        <div class="row">
            <div class="col-lg-4">
                <el-form-item label="Nombre" :error="errors.nombre?.[0]" required>
                    <el-input v-model="form.nombre" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Descripción" :error="errors.descripcion?.[0]">
                    <el-input v-model="form.descripcion" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Propietario" :error="errors.propietario?.[0]">
                    <el-input v-model="form.propietario" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Teléfono" :error="errors.telefono_contacto?.[0]">
                    <el-input v-model="form.telefono_contacto" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Correo Electrónico" :error="errors.email_contacto?.[0]">
                    <el-input v-model="form.email_contacto" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Activo" :error="errors.activo?.[0]">
                    <el-switch
                        class="ml-2"
                        v-model="form.activo"
                        inline-prompt
                        style="--el-switch-on-color: #13ce66; --el-switch-off-color: #ff4949"
                        active-text="SI"
                        inactive-text="NO"
                    />
                </el-form-item>
            </div>
        </div>

        <h6 class="text-muted">Información de Ubicación</h6>
        <div class="separator separator-dotted mb-5"></div>
        <div class="row">
            <div class="col-lg-3">
                <el-form-item label="Departamento" :error="errors.departamento_id?.[0]" required>
                    <el-select v-model="form.departamento_id" placeholder="Seleccione un departamento" filterable @change="handleDepartamentoChange( form.departamento_id )">
                        <el-option
                            v-for="item in departamentos"
                            :key="item.id"
                            :label="item.name"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
            </div>
            <div class="col-lg-3">
                <el-form-item label="Provincia" :error="errors.provincia_id?.[0]" required>
                    <el-select v-model="form.provincia_id" placeholder="Seleccione una provincia" filterable @change="handleProvinciaChange( form.provincia_id )">
                        <el-option
                            v-for="item in provincias"
                            :key="item.id"
                            :label="item.name"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
            </div>
            <div class="col-lg-3">
                <el-form-item label="Distrito" :error="errors.distrito_id?.[0]" required>
                    <el-select v-model="form.distrito_id" placeholder="Seleccione un distrito" filterable>
                        <el-option
                            v-for="item in distritos"
                            :key="item.id"
                            :label="item.name"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
            </div>
            <div class="col-lg-3">
                <el-form-item label="Dirección" :error="errors.direccion?.[0]">
                    <el-input v-model="form.direccion" />
                </el-form-item>
            </div>
            <div class="col-lg-2">
                <el-form-item label="Latitud" :error="errors.latitud?.[0]">
                    <el-input  v-model="form.latitud" />
                </el-form-item>
            </div>
            <div class="col-lg-2">
                <el-form-item label="Longitud" :error="errors.longitud?.[0]">
                    <el-input  v-model="form.longitud" />
                </el-form-item>
            </div>
        </div>

        <h6 class="text-muted">Piscinas <span class="fw-normal text-primary cursor-pointer text-hover-info" @click="addPiscina">[+ Agregar]</span> </h6>
        <div class="separator separator-dotted mb-5"></div>
        <div class="row">
            <div class="col-lg-12">
                <el-table :data="form.piscinas">
                    <!-- Nombre -->
                    <el-table-column label="Nombre">
                        <template #default="{ row, $index }">
                            <el-form-item :error="errors[`piscinas.${$index}.nombre`]?.[0]">
                                <el-input v-model="row.nombre" placeholder="Nombre" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <!-- Descripción -->
                    <el-table-column label="Descripción">
                        <template #default="{ row, $index }">
                            <el-form-item :error="errors[`piscinas.${$index}.descripcion`]?.[0]">
                                <el-input v-model="row.descripcion" placeholder="Descripción" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <!-- Superficie -->
                    <el-table-column label="Superficie (m²)">
                        <template #default="{ row, $index }">
                            <el-form-item :error="errors[`piscinas.${$index}.superficie_m2`]?.[0]">
                                <el-input-number v-model="row.superficie_m2" :precision="2" :step="0.01" placeholder="m²" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <!-- Profundidad -->
                    <el-table-column label="Profundidad (m)">
                        <template #default="{ row, $index }">
                            <el-form-item :error="errors[`piscinas.${$index}.profundidad_m`]?.[0]">
                                <el-input-number v-model="row.profundidad_m" :precision="2" :step="0.01" placeholder="m" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <!-- Estado -->
                    <el-table-column label="Estado">
                        <template #default="{ row, $index }">
                            <el-form-item :error="errors[`piscinas.${$index}.estado`]?.[0]">
                                <el-select v-model="row.estado" placeholder="Seleccione">
                                    <el-option label="Operativa" value="operativa" />
                                    <el-option label="Mantenimiento" value="mantenimiento" />
                                    <el-option label="Inactiva" value="inactiva" />
                                </el-select>
                            </el-form-item>
                        </template>
                    </el-table-column>
                </el-table>
            </div>
        </div>

        <template #footer>
            <div class="dialog-footer">
                <el-button size="small" type="primary" native-type="submit" :loading="loading">{{props.dataForm ? 'Actualizar' : 'Registrar'}}</el-button>
                <el-button size="small" type="danger" @click="dialogVisible = false">Cancelar</el-button>
            </div>
        </template>
    </DialogForm>
  </el-form>
</template>
