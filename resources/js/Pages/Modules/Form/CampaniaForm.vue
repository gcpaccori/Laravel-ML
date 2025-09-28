<script setup>
    import { onMounted, ref, watch } from 'vue';
    import useSubmitForm from '@/Composables/useSubmitForm';
    import { ElMessage, ElMessageBox } from 'element-plus';
    import { Delete, Close } from '@element-plus/icons-vue'

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
    const optionsPiscigranjas = ref(null);
    const optionsEspecies = ref(null);

    const form = ref({
        piscigranja_id : '',
        nombre: '',
        sistema_crianza: 'monofasico',
        fecha_inicio: '',
        fecha_fin_estimada: '',
        fecha_fin_real: '',
        estado : 'planificada',
        especies: []
    });

    const emit = defineEmits(['update:modelValue', 'saved', 'reload-table']);

    // Funciones
    const piscigranjasOptions = async () => {
        const response = await axios.get(route('piscigranjas.options'));
        optionsPiscigranjas.value = response.data.data;
    };

    const especiesOptions = async () => {
        const response = await axios.get(route('especies.options'));
        optionsEspecies.value = response.data;
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
        for (const key in form.value) {
            form.value[key] = '';
        }
        form.value.especies = [];
        form.value.estado  = 'planificada';
        form.value.sistema_crianza = 'monofasico';
        errors.value = {};
    };

    // Función para agregar una nueva fila editable
    const addEspecieCampania = () => {
        form.value.especies.push({
            especie_id : '',
            cantidad_siembra : 0,
            fecha_siembra : '',
            cantidad_cosechada : 0,
            peso_inicial_gr : 0.00,
            peso_final_gr : 0.00
        })
    }

    const delEspecieCampania = (index, id) => {
        if (id && id > 0) {
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
                const response = await axios.delete(route('campanias.especies.destroy', id));
                ElMessage({
                    message: response.data.message,
                    type: response.data.success ? 'success' : 'error',
                });

                if (response.data.success) {
                    form.value.especies.splice(index, 1);
                    emit('reload-table');
                }

            }).catch( (e) => {
                console.log(e);
            } );
        }else{
            form.value.especies.splice(index, 1);
        }
    }

    // Sincronizar cambios del padre
    watch(() => props.modelValue, async (val) => {
        dialogVisible.value = val;
        if (val) {
            resetForm();
            if (props.dataForm) {
                form.value = { ...props.dataForm };
            }
        }
    });

    // Emitir cambios al padre
    watch(dialogVisible, (val) => {
        emit('update:modelValue', val);
    });

    onMounted(  async() => {
        await piscigranjasOptions();
        await especiesOptions();
    });
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top">
    <DialogForm v-model="dialogVisible" :title="props.dataForm ? 'Editar Campaña' : 'Registrar Campaña'" width="70%">

        <h6 class="text-muted">Información General</h6>
        <div class="separator separator-dotted mb-5"></div>
        <div class="row">

            <div class="col-lg-4">
                <el-form-item label="Piscigranja" :error="errors.piscigranja_id?.[0]" required>
                    <el-select v-model="form.piscigranja_id" placeholder="Seleccione una piscigranja" filterable>
                        <el-option
                            v-for="item in optionsPiscigranjas"
                            :key="item.id"
                            :label="item.nombre"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Nombre" :error="errors.nombre?.[0]" required>
                    <el-input v-model="form.nombre" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Sistema de Crianza" :error="errors.sistema_crianza?.[0]" required>
                    <el-select v-model="form.sistema_crianza" placeholder="Seleccione">
                        <el-option label="Monofásico" value="monofasico" />
                        <el-option label="Bifásico" value="bifasico" />
                        <el-option label="Trifásico" value="trifasico" />
                    </el-select>
                </el-form-item>
            </div>

            <div class="col-lg-2">
                <el-form-item label="Fecha de Inicio" :error="errors.fecha_inicio?.[0]" required>
                    <el-date-picker
                        class="w-100"
                        type="date"
                        v-model="form.fecha_inicio"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        :clearable="false"
                    />
                </el-form-item>
            </div>

            <div class="col-lg-2">
                <el-form-item label="Fecha Fin Estimada" :error="errors.fecha_fin_estimada?.[0]">
                    <el-date-picker
                        class="w-100"
                        type="date"
                        v-model="form.fecha_fin_estimada"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        :clearable="false"
                    />
                </el-form-item>
            </div>

            <div class="col-lg-2">
                <el-form-item label="Fecha Fin Real" :error="errors.fecha_fin_real?.[0]">
                    <el-date-picker
                        class="w-100"
                        type="date"
                        v-model="form.fecha_fin_real"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        :clearable="false"
                    />
                </el-form-item>
            </div>

            <div class="col-lg-2">
                <el-form-item label="Estado" :error="errors.estado?.[0]" required>
                    <el-select v-model="form.estado" placeholder="Seleccione">
                        <el-option label="Planificada" value="planificada" />
                        <el-option label="En Proceso" value="en_proceso" />
                        <el-option label="Finalizada" value="finalizada" />
                        <el-option label="Cancelada" value="cancelada" />
                    </el-select>
                </el-form-item>
            </div>

        </div>

        <h6 class="text-muted">Especies <span class="fw-normal text-primary cursor-pointer text-hover-info" @click="addEspecieCampania">[+ Agregar]</span> </h6>
        <div class="separator separator-dotted mb-5"></div>
        <div class="row">
            <div class="col-lg-12">
                <el-table :data="form.especies">
                    <el-table-column label="Especie">
                        <template #default="{ row, $index }">
                            <el-form-item :error="errors[`especies.${$index}.especie_id`]?.[0] ?? errors[`especies`]?.[0]">
                                <el-select v-model="row.especie_id" placeholder="Seleccionar..." filterable>
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

                    <el-table-column label="Fecha Siembra">
                        <template #default="{ row, $index }">
                            <el-form-item :error="errors[`especies.${$index}.fecha_siembra`]?.[0]">
                                <el-date-picker
                                    class="w-100"
                                    type="date"
                                    v-model="row.fecha_siembra"
                                    format="DD/MM/YYYY"
                                    value-format="YYYY-MM-DD"
                                    :clearable="false"
                                />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <el-table-column label="N° alevines inicial">
                        <template #default="{ row, $index }">
                            <el-form-item :error="errors[`especies.${$index}.cantidad_siembra`]?.[0]">
                                <el-input-number class="w-100" v-model="row.cantidad_siembra" :min="0" :step="1" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <el-table-column label="N° peces final">
                        <template #default="{ row, $index }">
                            <el-form-item :error="errors[`especies.${$index}.cantidad_cosechada`]?.[0]">
                                <el-input-number class="w-100" v-model="row.cantidad_cosechada" :min="0" :step="1" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <el-table-column label="Peso inicial Alevin(g)">
                        <template #default="{ row, $index }">
                            <el-form-item :error="errors[`especies.${$index}.peso_inicial_gr`]?.[0]">
                                <el-input-number class="w-100" v-model="row.peso_inicial_gr" :precision="2" :step="0.01" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <el-table-column label="Peso Final Pez(g)">
                        <template #default="{ row, $index }">
                            <el-form-item :error="errors[`especies.${$index}.peso_final_gr`]?.[0]">
                                <el-input-number class="w-100" v-model="row.peso_final_gr" :precision="2" :step="0.01" />
                            </el-form-item>
                        </template>
                    </el-table-column>

                    <el-table-column label="Acciones" class-name="text-center">
                        <template #default="{ row, $index }">
                            <el-button
                                type="danger"
                                :icon="row.id && row.id > 0 ? Delete : Close"
                                @click="delEspecieCampania($index, row.id)"
                                circle
                            >
                            </el-button>
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
