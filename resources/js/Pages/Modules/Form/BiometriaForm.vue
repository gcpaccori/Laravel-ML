<script setup>
    import { router } from "@inertiajs/vue3";
    import { onMounted, ref, watch } from "vue";
    import useSubmitForm from "@/Composables/useSubmitForm";
    import { ElMessage, ElMessageBox } from "element-plus";
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
    const loadingForm = ref(true);

    const formInitial = ref({
        piscigranja_id             : null,
        campania_id                : null,
        campania_especie_id        : null,
        campania_etapa_id          : null,
        cantidad_peces_actuales    : 0,
        fecha_muestreo             : null,
        observaciones              : null,
        cantidad_muestreo          : 0,
        total_alimento_consumido_kg: 0,
        detalles                   : []
    });

    const { loading, progress, errors, submitForm } = useSubmitForm();
    const {
        form,
        resetForm: resetFormValues,
        setFormValues,
    } = useFormReset(formInitial.value);

    // Opciobes
    const piscigranjasOptions = async () => {
        const response = await axios.get(route("piscigranjas.options"));
        optionsPiscigranjas.value = response.data.data;
    };

    const campaniasOptions = async () => {
        optionsEspecies.value = null;
        optionsEtapas.value = null;
        const response = await axios.get(
            route("campania.active.show", form.value.piscigranja_id)
        );
        optionsCampanias.value = response.data;
    };

    const especiesOptions = async () => {
        optionsEtapas.value = null;
        const response = await axios.get(
            route("especie.active.show", form.value.campania_id)
        );
        optionsEspecies.value = response.data;
    };

    const etapasOptions = async () => {
        const response = await axios.get(
            route("etapa.active.show", form.value.campania_especie_id)
        );
        optionsEtapas.value = response.data;
    };

    // Agregar manualmente una fila
    const addMuestra = () => {
        const cantidad = parseInt(form.value.cantidad_muestreo);
        if (!cantidad || cantidad <= 0) {
            ElMessage.warning('Ingrese una cantidad válida de muestras.');
            return;
        }

        const cantidadActual = form.value.detalles.length;
        const cantidadNueva = parseInt(cantidad);

        // Si aumenta la cantidad, agrega nuevas filas vacías
        if (cantidadNueva > cantidadActual) {
            for (let i = cantidadActual; i < cantidadNueva; i++) {
                form.value.detalles.push({
                    numero     : i + 1,
                    longitud_cm: null,
                    peso_g     : null,
                });
            }
        }

        // Si disminuye, elimina las últimas filas
        if (cantidadNueva < cantidadActual) {
            form.value.detalles.splice(cantidadNueva);
        }
    };

    // Eliminar una muestra
    const removeMuestra = (index) => {
        form.value.detalles.splice(index, 1);
        // Reordenar numeración
        form.value.detalles.forEach((d, i) => d.numero = i + 1);
        form.value.cantidad_muestreo = form.value.detalles.length;
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
        } else {
            resetForm();
        }

        loadingForm.value = false;
    });
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <template v-if="loadingForm">
            <el-skeleton :rows="15" animated />
        </template>
        <template v-else>
            <FormSection @submitted="submit">
                <template #form>
                    <div class="row">
                        <div class="col-lg-8">
                            <h6 class="text-muted">Identificación del Muestreo</h6>
                            <div class="separator separator-dotted mb-3"></div>
                            <div class="row mb-5">
                                <div class="col-lg-4">
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

                                <div class="col-lg-4">
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

                                <div class="col-lg-4">
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

                                <div class="col-lg-4">
                                    <el-form-item
                                        label="Etapa"
                                        :error="errors.campania_etapa_id?.[0]"
                                        required
                                    >
                                        <el-select
                                            v-model="form.campania_etapa_id"
                                            placeholder="Seleccione una etapa"
                                            filterable
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

                                <div class="col-lg-4">
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

                                <div class="col-lg-4">
                                    <el-form-item
                                        label="N° de Muestras"
                                        :error="errors.cantidad_muestreo?.[0]"
                                        required
                                    >
                                        <el-input
                                            type="number"
                                            class="w-100"
                                            v-model="form.cantidad_muestreo"
                                            :min="0"
                                            @change="addMuestra"
                                        />
                                    </el-form-item>
                                </div>

                                <div class="col-lg-4">
                                    <el-form-item
                                        label="N° Peces Actuales"
                                        :error="errors.cantidad_peces_actuales?.[0]"
                                        required
                                    >
                                        <el-input
                                            type="number"
                                            class="w-100"
                                            v-model="form.cantidad_peces_actuales"
                                            :min="0"
                                        />
                                    </el-form-item>
                                </div>

                                <div class="col-lg-4">
                                    <el-form-item
                                        label="Total Alimento Consumido (Kg)"
                                        :error="errors.total_alimento_consumido_kg?.[0]"
                                        required
                                    >
                                        <el-input
                                            type="number"
                                            class="w-100"
                                            v-model="form.total_alimento_consumido_kg"
                                            :min="0"
                                            step="any"
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
                        </div>
                        <div class="col-lg-4">
                            <h6 class="text-muted">Registro de las Muestras</h6>
                            <div class="separator separator-dotted mb-3"></div>
                            <el-table
                                :data="form.detalles"
                                border
                                style="width: 100%"
                                v-if="form.detalles.length > 0"
                            >
                                <el-table-column label="N°" prop="numero" width="50" align="center">
                                    <template #default="{ row }">
                                        <span>{{ row.numero }}</span>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Tamaño (cm)" align="center">
                                    <template #default="{ $index }">
                                        <el-input
                                            v-model="form.detalles[$index].longitud_cm"
                                            type="text"
                                            step="any"
                                            :min="0"
                                            class="w-100"
                                        />

                                        <small
                                            v-if="errors[`detalles.${$index}.longitud_cm`]"
                                            class="text-danger"
                                        >
                                            {{ errors[`detalles.${$index}.longitud_cm`] }}
                                        </small>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Peso (g)" align="center">
                                    <template #default="{ $index }">
                                        <el-input
                                            v-model="form.detalles[$index].peso_g"
                                            type="text"
                                            step="any"
                                            :min="0"
                                            class="w-100"
                                        />

                                        <small
                                            v-if="errors[`detalles.${$index}.peso_g`]"
                                            class="text-danger"
                                        >
                                            {{ errors[`detalles.${$index}.peso_g`] }}
                                        </small>
                                    </template>
                                </el-table-column>

                                <el-table-column label="Acciones" width="100" align="center">
                                    <template #default="{ $index }">
                                        <el-button
                                            type="danger"
                                            size="small"
                                            icon="Delete"
                                            @click="removeMuestra($index)"
                                        />
                                    </template>
                                </el-table-column>
                            </el-table>
                            <template v-else>
                                <p class="text-muted fst-italic">No se han agregado muestras aún.</p>
                                <small v-if="errors.detalles?.[0]" class="text-danger">{{ errors.detalles?.[0] }}</small>
                            </template>
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

