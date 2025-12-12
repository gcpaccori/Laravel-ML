<script setup>
    import { router } from "@inertiajs/vue3";
    import { onMounted, ref } from "vue";
    import useSubmitForm from "@/Composables/useSubmitForm";
    import { ElMessageBox } from "element-plus";
    import { useFormReset } from "@/Composables/useFormReset";
    import FormSection from "@/Components/FormSection.vue";

    const props = defineProps({
        title: String,
        toolbar: {
            type: Array,
            required: false
        },
        dataForm: {
            type: Object,
            required: false,
            default: null,
        },
    });

    const optionsPiscigranjas = ref(null);
    const optionsCampanias    = ref(null);
    const optionsEspecies     = ref(null);
    const optionsEtapas       = ref(null);
    const etapaParametros     = ref(null);
    const loadingForm = ref(true);

    const formInitial = ref({
        piscigranja_id: null,
        campania_id: null,
        campania_especie_id: null,
        campania_etapa_id: null,

        cantidad_peces_inicial: 0,
        cantidad_peces_final: 0,
        fecha_muestreo: null,
        peso_inicial_gr: 0.0,
        peso_final_gr: 0.0,
        tamanio_inicial_cm: 0.0,
        tamanio_final_cm: 0.0,
        biomasa_inicial_kg: 0.0,
        biomasa_final_kg: 0.0,
        tasa_supervivencia_porcentaje: 0.0,
        tasa_crecimiento_especifico_porcentaje: 0.0,
        observaciones: null,
        cantidad_muestreo: 0,
    });

    const { loading, progress, errors, submitForm } = useSubmitForm();
    const {
        form,
        resetForm: resetFormValues,
        setFormValues,
    } = useFormReset(formInitial.value);

    // Funciones
    const piscigranjasOptions = async () => {
        const response = await axios.get(route("piscigranjas.options"));
        optionsPiscigranjas.value = response.data.data;
    };

    const campaniasOptions = async () => {
        optionsEspecies.value = null;
        optionsEtapas.value = null;
        etapaParametros.value = null;
        const response = await axios.get(
            route("campania.active.show", form.value.piscigranja_id)
        );
        optionsCampanias.value = response.data;
    };

    const especiesOptions = async () => {
        optionsEtapas.value = null;
        etapaParametros.value = null;
        const response = await axios.get(
            route("especie.active.show", form.value.campania_id)
        );
        optionsEspecies.value = response.data;
    };

    const etapasOptions = async () => {
        etapaParametros.value = null;

        const response = await axios.get(
            route("etapa.active.show", form.value.campania_especie_id)
        );
        optionsEtapas.value = response.data;
    };

    const parametrosEtapa = async () => {
        const response = await axios.get(
            route("parametro.active.show", form.value.campania_especie_id)
        );
        etapaParametros.value = response.data;
        calcularTasaCrecimiento();
    };

    // Biomasa Inicial (Kg) = Numero Peces Inicial * Peso Inicial / 1000
    const calcularBiomasaInicial = () => {
        const cantidad_peces_inicial = form.value.cantidad_peces_inicial;
        const peso_inicial_gr = form.value.peso_inicial_gr;
        form.value.biomasa_inicial_kg = 0.0;

        if ( cantidad_peces_inicial && peso_inicial_gr && cantidad_peces_inicial > 0 && peso_inicial_gr > 0 ) {
            form.value.biomasa_inicial_kg = parseFloat(((cantidad_peces_inicial*peso_inicial_gr)/1000).toFixed(4))
        }

        calcularTasaCrecimiento();
        calcularTasaSupervivencia();
    };

    // Biomasa Final (Kg) = Numero Peces Final * Peso Final / 1000
    const calcularBiomasaFinal = () => {
        const cantidad_peces_final = form.value.cantidad_peces_final;
        const peso_final_gr = form.value.peso_final_gr;
        form.value.biomasa_final_kg = 0.0;

        if ( cantidad_peces_final && peso_final_gr && cantidad_peces_final > 0 && peso_final_gr > 0 ) {
            form.value.biomasa_final_kg = parseFloat(((cantidad_peces_final*peso_final_gr)/1000).toFixed(4))
        }

        calcularTasaCrecimiento();
        calcularTasaSupervivencia();
    };

    // Tasa Crecimiento (%) = (peso final-peso inicial)/DiasT
    const calcularTasaCrecimiento = () => {
        const peso_inicial_gr = form.value.peso_inicial_gr;
        const peso_final_gr = form.value.peso_final_gr;
        const dias_muestreo = etapaParametros.value?.dias_muestreo ?? 0;
        form.value.tasa_crecimiento_especifico_porcentaje = 0.0;

        if ( peso_inicial_gr && peso_final_gr && dias_muestreo && peso_inicial_gr > 0 && peso_final_gr > 0 && dias_muestreo > 0) {
            form.value.tasa_crecimiento_especifico_porcentaje = parseFloat(((peso_final_gr-peso_inicial_gr)/dias_muestreo).toFixed(4))
        }
    };

    // Tasa Supervivencia (%) = (N°peces final/ N°peces inicial)*100
    const calcularTasaSupervivencia = () => {
        const cantidad_peces_inicial = form.value.cantidad_peces_inicial;
        const cantidad_peces_final = form.value.cantidad_peces_final;
        form.value.tasa_supervivencia_porcentaje = 0.0;

        if ( cantidad_peces_inicial && cantidad_peces_final && cantidad_peces_inicial > 0 && cantidad_peces_final > 0) {
            form.value.tasa_supervivencia_porcentaje = parseFloat(((cantidad_peces_final/cantidad_peces_inicial)*100).toFixed(4))
        }
    };

    const resetForm = () => {
        resetFormValues();
        errors.value = {};
    };

    const back = () => {
        router.visit(route('produccion.biometrias.index'));
    }

    const submit = () => {
        const routeName = props.dataForm ? "biometrias.update" : "biometrias.store";

        submitForm({
            url: props.dataForm
                ? route(routeName, props.dataForm.id)
                : route(routeName),
            data: form.value,
            method: props.dataForm ? "put" : "post",
            onSuccess: (response) => {
                const biometriaId = response.data.data.id;

                ElMessageBox.confirm(
                    response.data.message,
                    'Éxito',
                    {
                        confirmButtonText: 'Ver PDF',
                        cancelButtonText: 'Mostrar Biométrias',
                        type: 'success',
                        center: true,
                        closeOnPressEscape: false,
                        closeOnClickModal: false,
                        showClose: false
                    }
                )
                .then(() => {
                    window.open(route('biometrias.pdf', biometriaId), '_blank');
                })
                .catch(() => {
                    back();
                });

                resetForm();
            },
        });
    };

    onMounted(async () => {
        await piscigranjasOptions();

        if (props.dataForm) {
            setFormValues({
                ...props.dataForm,
                piscigranja_id: props.dataForm.campania_etapa?.campania_especie?.campania?.piscigranja_id ?? null,
                campania_id: props.dataForm.campania_etapa?.campania_especie?.campania_id ?? null,
                campania_especie_id: props.dataForm.campania_etapa?.campania_especie_id,
            });
            await campaniasOptions();
            await especiesOptions();
            await etapasOptions();
            await parametrosEtapa();
        } else {
            resetForm();
        }

        loadingForm.value = false;
    });

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <template v-if="loadingForm">
            <el-skeleton :rows="7" animated />
        </template>
        <template v-else>
            <FormSection @submitted="submit">
                <template #form>
                    <h6 class="text-muted">Identificación del Muestreo</h6>
                    <div class="separator separator-dotted mb-3"></div>
                    <div class="row mb-2">
                        <div class="col-lg-3">
                            <el-form-item
                                label="Piscigranja"
                                :error="errors.piscigranja_id?.[0]"
                                required
                            >
                                <el-select
                                    @change="campaniasOptions"
                                    v-model="form.piscigranja_id"
                                    placeholder="Seleccione una piscigranja"
                                    filterable
                                >
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
                            <el-form-item
                                label="Campañas"
                                :error="errors.campania_id?.[0]"
                                required
                            >
                                <el-select
                                    @change="especiesOptions"
                                    v-model="form.campania_id"
                                    placeholder="Seleccione una campaña"
                                    filterable
                                >
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
                            <el-form-item
                                label="Especies"
                                :error="errors.campania_especie_id?.[0]"
                                required
                            >
                                <el-select
                                    @change="etapasOptions"
                                    v-model="form.campania_especie_id"
                                    placeholder="Seleccione una especie"
                                    filterable
                                >
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
                            <el-form-item
                                label="Etapa"
                                :error="errors.campania_etapa_id?.[0]"
                                required
                            >
                                <el-select
                                    v-model="form.campania_etapa_id"
                                    placeholder="Seleccione una etapa"
                                    filterable
                                    @change="parametrosEtapa"
                                >
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

                    <div class="row mb-2">
                        <div class="col-lg-3">
                            <el-form-item
                                label="Fecha de Muestreo"
                                :error="errors.fecha_muestreo?.[0]"
                                required
                            >
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
                    </div>

                    <h6 class="text-muted">Resultados del Muestreo</h6>
                    <div class="separator separator-dotted mb-3"></div>
                    <div class="row mb-2">
                        <div class="col-lg-2">
                            <el-form-item
                                label="N° de Muestras"
                                :error="errors.cantidad_muestreo?.[0]"
                            >
                                <el-input
                                    type="number"
                                    class="w-100"
                                    v-model="form.cantidad_muestreo"
                                    :min="0"
                                    step="any"
                                    @change="calcularBiomasaInicial"
                                />
                            </el-form-item>
                        </div>

                        <div class="col-lg-2">
                            <el-form-item
                                label="N° Peces Iniciales"
                                :error="errors.cantidad_peces_inicial?.[0]"
                            >
                                <el-input
                                    type="number"
                                    class="w-100"
                                    v-model="form.cantidad_peces_inicial"
                                    :min="0"
                                    step="any"
                                    @change="calcularBiomasaInicial"
                                />
                            </el-form-item>
                        </div>

                        <div class="col-lg-2">
                            <el-form-item
                                label="N° Peces Finales"
                                :error="errors.cantidad_peces_final?.[0]"
                            >
                                <el-input
                                    type="number"
                                    class="w-100"
                                    v-model="form.cantidad_peces_final"
                                    :min="0"
                                    step="any"
                                    @change="calcularBiomasaFinal"
                                />
                            </el-form-item>
                        </div>

                        <div class="col-lg-2">
                            <el-form-item
                                label="Peso Inicial (g)"
                                :error="errors.peso_inicial_gr?.[0]"
                            >
                                <el-input
                                    type="number"
                                    class="w-100"
                                    v-model="form.peso_inicial_gr"
                                    :min="0"
                                    step="any"
                                    @change="calcularBiomasaInicial"
                                />
                            </el-form-item>
                        </div>

                        <div class="col-lg-2">
                            <el-form-item
                                label="Peso Final (g)"
                                :error="errors.peso_final_gr?.[0]"
                            >
                                <el-input
                                    type="number"
                                    class="w-100"
                                    v-model="form.peso_final_gr"
                                    :min="0"
                                    step="any"
                                    @change="calcularBiomasaFinal"
                                />
                            </el-form-item>
                        </div>

                        <div class="col-lg-2">
                            <el-form-item
                                label="Tamaño Inicial (cm)"
                                :error="errors.tamanio_inicial_cm?.[0]"
                            >
                                <el-input
                                    type="number"
                                    class="w-100"
                                    v-model="form.tamanio_inicial_cm"
                                    :min="0"
                                    step="any"
                                />
                            </el-form-item>
                        </div>
                    </div>

                    <div class="row mb-2">
                        <div class="col-lg-2">
                            <el-form-item
                                label="Tamaño Final (cm)"
                                :error="errors.tamanio_final_cm?.[0]"
                            >
                                <el-input
                                    type="number"
                                    class="w-100"
                                    v-model="form.tamanio_final_cm"
                                    :min="0"
                                    step="any"
                                />
                            </el-form-item>
                        </div>
                    </div>

                    <h6 class="text-muted">Resultados Calculados</h6>
                    <div class="separator separator-dotted mb-3"></div>
                    <div class="row mb-2">
                        <div class="col-lg-3">
                            <el-form-item
                                label="Biomasa Inicial (Kg)"
                                :error="errors.biomasa_inicial_kg?.[0]"
                            >
                                <el-input
                                    type="number"
                                    class="w-100"
                                    v-model="form.biomasa_inicial_kg"
                                    :min="0"
                                    step="any"
                                    disabled
                                />
                            </el-form-item>
                        </div>

                        <div class="col-lg-3">
                            <el-form-item
                                label="Biomasa Final (Kg)"
                                :error="errors.biomasa_final_kg?.[0]"
                            >
                                <el-input
                                    type="number"
                                    class="w-100"
                                    v-model="form.biomasa_final_kg"
                                    :min="0"
                                    step="any"
                                    disabled
                                />
                            </el-form-item>
                        </div>

                        <div class="col-lg-3">
                            <el-form-item
                                label="Tasa de supervivencia (%)"
                                :error="errors.tasa_supervivencia_porcentaje?.[0]"
                            >
                                <el-input
                                    type="number"
                                    class="w-100"
                                    v-model="form.tasa_supervivencia_porcentaje"
                                    :min="0"
                                    step="any"
                                    disabled
                                />
                            </el-form-item>
                        </div>

                        <div class="col-lg-3">
                            <el-form-item
                                label="Tasa específica de crecimiento (%)"
                                :error="
                                    errors.tasa_crecimiento_especifico_porcentaje?.[0]
                                "
                            >
                                <el-input
                                    type="number"
                                    class="w-100"
                                    v-model="
                                        form.tasa_crecimiento_especifico_porcentaje
                                    "
                                    :min="0"
                                    step="any"
                                    disabled
                                />
                            </el-form-item>
                        </div>
                    </div>

                    <h6 class="text-muted">Información Adicional</h6>
                    <div class="separator separator-dotted mb-3"></div>
                    <div class="row">
                        <div class="col-lg-12">
                            <el-form-item
                                label="Observaciones"
                                :error="errors.observaciones?.[0]"
                            >
                                <el-input
                                    type="textarea"
                                    v-model="form.observaciones"
                                />
                            </el-form-item>
                        </div>
                    </div>
                </template>
                <template #actions>
                    <div class="d-flex justify-content-center">
                        <el-button size="small" type="primary" native-type="submit" :loading="loading" >{{props.dataForm ? 'Actualizar' : 'Registrar'}}</el-button>
                        <el-button size="small" type="danger" @click.prevent="back()" >Cancelar</el-button>
                    </div>
                </template>
            </FormSection>
        </template>
    </App>
</template>
