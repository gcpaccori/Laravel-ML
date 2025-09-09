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
        },
    });

    const dialogVisible = ref(props.modelValue);
    const piscinasList = ref(null);
    const etapas = ref(null);

    const form = ref({
        campania_especie_id: '',
        piscigranja_id: '',
        etapa_id: '',
        piscina_id: '',
        fecha_inicio: '',
        fecha_fin: '',
        cantidad_inicial: 0,
        cantidad_final: 0,
        peso_promedio_gr: 0.0,
        estado: 'en_proceso'
    });

    const emit = defineEmits(['update:modelValue', 'saved']);

    const piscinasOptions = async () => {
        const { data } = await axios.get(
            route("piscigranjas.piscinas", form.value.piscigranja_id)
        );
        piscinasList.value = data;
    };

    const getEtapas = async () => {
        const { data } = await axios.get(route("etapas.options"));
        etapas.value = data;
    };

    const submitFormulario = () => {
        const routeName = props.dataForm?.id ? 'campanias.etapas.update' : 'campanias.etapas.store';
        submitForm({
            url: props.dataForm?.id
                ? route(routeName, props.dataForm.id)
                : route(routeName),
            data: form.value,
            method: props.dataForm?.id ? 'put' : 'post',
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
        form.value.cantidad_inicial = 0;
        form.value.cantidad_final = 0;
        form.value.peso_promedio_gr = 0.0;
        form.value.estado = 'en_proceso';
        errors.value = {};
    };

    // Sincronizar cambios del padre
    watch(() => props.modelValue, async (val) => {
        dialogVisible.value = val;
        if (val) {
            resetForm();
            if (props.dataForm) {
                form.value = { ...props.dataForm };
                form.value.campania_especie_id = props.dataForm.campania_especie_id;
                form.value.piscigranja_id = props.dataForm.piscigranja_id;
                // form.value.etapa_id = props.dataForm.etapa_id ?? '';
                // form.value.piscina_id = props.dataForm.piscina_id ?? '';
                // form.value.fecha_inicio = props.dataForm.fecha_inicio ?? '';
                // form.value.fecha_fin = props.dataForm.fecha_fin ?? '';
                form.value.cantidad_inicial = props.dataForm.cantidad_inicial ?? 0;
                form.value.cantidad_final = props.dataForm.cantidad_final ?? 0;
                form.value.peso_promedio_gr = props.dataForm.peso_promedio_gr ?? 0.0;
                form.value.estado = props.dataForm.estado ?? 'en_proceso';

                await piscinasOptions();
            }
        }
    });

    // Emitir cambios al padre
    watch(dialogVisible, (val) => {
        emit('update:modelValue', val);
    });

    onMounted(  async() => {
        await getEtapas();
    });
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top">
    <DialogForm v-model="dialogVisible" :title="props.dataForm?.estado === 'finalizada' ? 'Visualizar Etapa': props.dataForm?.id ? 'Editar Etapa' : 'Registrar Etapa'" width="50%">
        <div class="row mb-5">
            <div class="col-lg-3">
                <el-form-item label="Etapa" :error="errors.etapa_id?.[0]" required>
                    <el-select
                        filterable
                        v-model="form.etapa_id"
                        :disabled="form.estado === 'finalizada'"
                    >
                        <el-option
                            v-for="item in etapas"
                            :key="item.id"
                            :label="item.nombre"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
            </div>

            <div class="col-lg-3">
                <el-form-item label="Piscinas"  :error="errors.piscina_id?.[0]" required>
                    <el-select
                        filterable
                        v-model="form.piscina_id"
                        :disabled="form.estado === 'finalizada'"
                    >
                        <el-option
                            v-for="item in piscinasList"
                            :key="item.id"
                            :label="item.nombre"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
            </div>

            <div class="col-lg-3">
                <el-form-item label="Fecha de Inicio" :error="errors.fecha_inicio?.[0]" required>
                    <el-date-picker
                        class="w-100"
                        type="date"
                        v-model="form.fecha_inicio"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        :clearable="false"
                        :disabled="form.estado === 'finalizada'"
                    />
                </el-form-item>
            </div>

            <div class="col-lg-3">
                <el-form-item label="Fecha Fin" :error="errors.fecha_fin?.[0]">
                    <el-date-picker
                        class="w-100"
                        type="date"
                        v-model="form.fecha_fin"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        :clearable="false"
                        disabled
                    />
                </el-form-item>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-3">
                <el-form-item label="Cantidad Inicial" :error="errors.cantidad_inicial?.[0]" required>
                    <el-input-number class="w-100" v-model="form.cantidad_inicial" :min="0" :step="1" :disabled="form.estado === 'finalizada'"/>
                </el-form-item>
            </div>

            <div class="col-lg-3">
                <el-form-item label="Cantidad Final" :error="errors.cantidad_final?.[0]">
                    <el-input-number class="w-100" v-model="form.cantidad_final" :min="0" :step="1" disabled />
                </el-form-item>
            </div>

            <div class="col-lg-3">
                <el-form-item label="Peso Promedio (g)" :error="errors.peso_promedio_gr?.[0]">
                    <el-input-number class="w-100" v-model="form.peso_promedio_gr" :precision="2" :step="0.01" :min="0" :disabled="form.estado === 'finalizada'"/>
                </el-form-item>
            </div>

            <div class="col-lg-3">
                <el-form-item label="Estado" :error="errors.estado?.[0]" required>
                    <el-select v-model="form.estado" placeholder="Seleccione" :disabled="form.estado === 'finalizada'">
                        <el-option label="En Proceso" value="en_proceso" />
                        <el-option label="Finalizada" value="finalizada" v-show="form.estado === 'finalizada'"/>
                        <el-option label="Cancelada" value="cancelada" />
                    </el-select>
                </el-form-item>
            </div>
        </div>

        <template #footer>
            <div class="dialog-footer">
                <el-button v-if="form.estado !== 'finalizada'" size="small" type="primary" native-type="submit" :loading="loading">{{props.dataForm?.id ? 'Actualizar' : 'Registrar'}}</el-button>
            <el-button size="small" type="danger" @click="dialogVisible = false">{{  form.estado === 'finalizada' ? 'Cerrar' : 'Cancelar'}}</el-button>
            </div>
        </template>
    </DialogForm>
  </el-form>
</template>
