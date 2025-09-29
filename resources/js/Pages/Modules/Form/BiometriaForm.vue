<script setup>
    import { onMounted, ref, watch } from 'vue';
    import useSubmitForm from '@/Composables/useSubmitForm';
    import { ElMessage, ElMessageBox } from 'element-plus';
    import { useFormReset } from "@/Composables/useFormReset";

    const { loading, progress, errors, submitForm } = useSubmitForm();

    const props = defineProps({
        modelValue: Boolean,
        dataForm: {
            type: Object,
            required: false,
            default: null
        },
    });

    const formInitial = ref({
        piscigranja_id                        : null,
        campania_id                           : null,
        campania_especie_id                   : null,
        campania_etapa_id                     : null,

        fecha_muestreo                        : null,
        numero_muestreo                       : 0,
        peso_inicial_gr                       : 0.0,
        peso_final_gr                         : 0.0,
        tamanio_inicial_cm                    : 0.0,
        tamanio_final_cm                      : 0.0,
        biomasa_inicial_kg                    : 0.0,
        biomasa_final_kg                      : 0.0,
        tasa_supervivencia_porcentaje         : 0.0,
        tasa_crecimiento_especifico_porcentaje: 0.0,
        observaciones                         : null,
    });
    const dialogVisible       = ref(props.modelValue);
    const optionsPiscigranjas = ref(null);
    const optionsCampanias    = ref(null);
    const optionsEspecies     = ref(null);
    const optionsEtapas       = ref(null);

    const { form, resetForm: resetFormValues, setFormValues } = useFormReset(formInitial.value);
    const emit = defineEmits(['update:modelValue', 'saved']);

    // Funciones
    const piscigranjasOptions = async () => {
        const response = await axios.get(route('piscigranjas.options'));
        optionsPiscigranjas.value = response.data.data;
    };

    const campaniasOptions = async () => {
        optionsEspecies.value = null;
        optionsEtapas.value = null;
        const response = await axios.get(route('campania.active.show', form.value.piscigranja_id));
        console.log(response.data);

        optionsCampanias.value = response.data;
    };

    const especiesOptions = async () => {
        optionsEtapas.value = null;
        const response = await axios.get(route('especie.active.show', form.value.campania_id));
        optionsEspecies.value = response.data;
    };

    const etapasOptions = async () => {
        const response = await axios.get(route('etapa.active.show', form.value.campania_especie_id));
        optionsEtapas.value = response.data;
    };

    const submitFormulario = () => {
        const routeName = props.dataForm ? 'biometrias.update' : 'biometrias.store';
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
        resetFormValues();
        errors.value = {};
    };

    // Sincronizar cambios del padre
    watch(() => props.modelValue, async (val) => {
        dialogVisible.value = val;
        if (val) {
            if (props.dataForm) {
                setFormValues({
                    ...props.dataForm,
                    campania_especie_id: 1,
                    campania_id: 1,
                    piscigranja_id: 1,
                });
                await campaniasOptions();
                await especiesOptions();
                await etapasOptions();
            } else {
                resetForm();
            }
        }
    });

    // Emitir cambios al padre
    watch(dialogVisible, (val) => {
        emit('update:modelValue', val);
        if (!val) {
            resetForm(); // Resetear cuando se cierre
        }
    });

    onMounted(  async() => {
        await piscigranjasOptions();
    });
</script>

<template>
  <el-form @submit.prevent="submitFormulario" :model="form" label-position="top">
    <DialogForm v-model="dialogVisible" :title="props.dataForm ? 'Editar Biometría' : 'Registrar Biometría'" width="80%">

        <div class="row mb-5">
            <div class="col-lg-3">
                <el-form-item label="Piscigranja" :error="errors.piscigranja_id?.[0]" required>
                    <el-select @change="campaniasOptions" v-model="form.piscigranja_id" placeholder="Seleccione una piscigranja" filterable>
                        <el-option
                            v-for="item in optionsPiscigranjas"
                            :key="item.id"
                            :label="item.nombre"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
            </div>

            <div class="col-lg-3">
                <el-form-item label="Campañas" :error="errors.campania_id?.[0]" required>
                    <el-select @change="especiesOptions" v-model="form.campania_id" placeholder="Seleccione una campaña" filterable>
                        <el-option
                            v-for="item in optionsCampanias"
                            :key="item.id"
                            :label="item.nombre"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
            </div>

            <div class="col-lg-3">
                <el-form-item label="Especies" :error="errors.campania_especie_id?.[0]" required>
                    <el-select @change="etapasOptions" v-model="form.campania_especie_id" placeholder="Seleccione una especie" filterable>
                        <el-option
                            v-for="item in optionsEspecies"
                            :key="item.id"
                            :label="item.especie.nombre"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
            </div>

            <div class="col-lg-3">
                <el-form-item label="Etapa" :error="errors.campania_etapa_id?.[0]" required>
                    <el-select v-model="form.campania_etapa_id" placeholder="Seleccione una etapa" filterable>
                        <el-option
                            v-for="item in optionsEtapas"
                            :key="item.id"
                            :label="`${item.etapa.nombre} - (${item.piscina.nombre})`"
                            :value="item.id"
                        />
                    </el-select>
                </el-form-item>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-lg-2">
                <el-form-item label="Fecha de Muestreo" :error="errors.fecha_muestreo?.[0]" required>
                    <el-date-picker
                        class="w-100"
                        type="date"
                        v-model="form.fecha_muestreo"
                        format="DD/MM/YYYY"
                        value-format="YYYY-MM-DD"
                        :clearable="false"
                    />
                </el-form-item>
            </div>

            <div class="col-lg-2">
                <el-form-item label="Peso Inicial (g)" :error="errors.peso_inicial_gr?.[0]">
                    <el-input-number style="width: 100%" v-model="form.peso_inicial_gr" :min="0" :precision="2" :step="0.01" placeholder="g" />
                </el-form-item>
            </div>

            <div class="col-lg-2">
                <el-form-item label="Peso Final (g)" :error="errors.peso_final_gr?.[0]">
                    <el-input-number style="width: 100%" v-model="form.peso_final_gr" :min="0" :precision="2" :step="0.01" placeholder="g" />
                </el-form-item>
            </div>

            <div class="col-lg-2">
                <el-form-item label="Tamaño Inicial (cm)" :error="errors.tamanio_inicial_cm?.[0]">
                    <el-input-number style="width: 100%" v-model="form.tamanio_inicial_cm" :min="0" :precision="2" :step="0.01" placeholder="cm" />
                </el-form-item>
            </div>

            <div class="col-lg-2">
                <el-form-item label="Tamaño Final (cm)" :error="errors.tamanio_final_cm?.[0]">
                    <el-input-number style="width: 100%" v-model="form.tamanio_final_cm" :min="0" :precision="2" :step="0.01" placeholder="cm" />
                </el-form-item>
            </div>

            <div class="col-lg-2">
                <el-form-item label="Biomasa Inicial (Kg)" :error="errors.biomasa_inicial_kg?.[0]">
                    <el-input-number style="width: 100%" v-model="form.biomasa_inicial_kg" :min="0" :precision="2" :step="0.01" placeholder="Kg" />
                </el-form-item>
            </div>
        </div>

        <div class="row mb-5">
            <div class="col-lg-2">
                <el-form-item label="Biomasa Final (Kg)" :error="errors.biomasa_final_kg?.[0]">
                    <el-input-number style="width: 100%" v-model="form.biomasa_final_kg" :min="0" :precision="2" :step="0.01" placeholder="Kg" />
                </el-form-item>
            </div>

            <div class="col-lg-3">
                <el-form-item label="Tasa de supervivencia (%)" :error="errors.tasa_supervivencia_porcentaje?.[0]">
                    <el-input-number style="width: 100%" v-model="form.tasa_supervivencia_porcentaje" :min="0" :precision="2" :step="0.01" placeholder="%" />
                </el-form-item>
            </div>

            <div class="col-lg-3">
                <el-form-item label="Tasa específica de crecimiento (%)" :error="errors.tasa_crecimiento_especifico_porcentaje?.[0]">
                    <el-input-number style="width: 100%" v-model="form.tasa_crecimiento_especifico_porcentaje" :min="0" :precision="2" :step="0.01" placeholder="%" />
                </el-form-item>
            </div>

            <div class="col-lg-4">
                <el-form-item label="Observaciones" :error="errors.observaciones?.[0]">
                    <el-input type="textarea" v-model="form.observaciones" />
                </el-form-item>
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
