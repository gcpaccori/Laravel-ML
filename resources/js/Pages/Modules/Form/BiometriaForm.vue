<script setup>
    import { router } from "@inertiajs/vue3";
    import { computed, onMounted, ref, watch } from "vue";
    import { InfoFilled, View } from "@element-plus/icons-vue";
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

    // ================= Alimento consumido (referencial) =================
    const referencia          = ref(null);
    const loadingReferencia   = ref(false);
    const dialogReferencia    = ref(false);
    // En edición no se sobrescribe el valor guardado
    const totalEditadoManual  = ref(!!props.dataForm);
    let referenciaReqId = 0;

    const origenFechaInicial = {
        biometria_anterior: "biometría anterior",
        inicio_etapa      : "inicio de la etapa",
        fecha_siembra     : "fecha de siembra",
    };

    const fmtFecha = (f) => (f ? f.split("-").reverse().join("/") : "—");
    const fmtKg = (v, d = 3) =>
        v === null || v === undefined ? "—" : Number(v).toLocaleString("es-PE", { minimumFractionDigits: d, maximumFractionDigits: d });

    const cargarReferencia = async () => {
        if (!form.value.campania_etapa_id || !form.value.fecha_muestreo) {
            referencia.value = null;
            return;
        }
        const reqId = ++referenciaReqId;
        loadingReferencia.value = true;
        try {
            const { data } = await axios.get(route("biometrias.alimento-referencial"), {
                params: {
                    campania_etapa_id: form.value.campania_etapa_id,
                    fecha_muestreo   : form.value.fecha_muestreo,
                    biometria_id     : props.dataForm?.id ?? null,
                },
            });
            if (reqId !== referenciaReqId) return; // respuesta obsoleta
            referencia.value = data;
            if (!totalEditadoManual.value && data.total_kg !== null) {
                form.value.total_alimento_consumido_kg = data.total_kg;
            }
        } catch (e) {
            if (reqId === referenciaReqId) referencia.value = null;
        } finally {
            if (reqId === referenciaReqId) loadingReferencia.value = false;
        }
    };

    watch(
        () => [form.value.campania_etapa_id, form.value.fecha_muestreo],
        cargarReferencia
    );

    const difiereDeReferencia = computed(() =>
        referencia.value?.total_kg !== null &&
        referencia.value?.total_kg !== undefined &&
        Math.abs(Number(form.value.total_alimento_consumido_kg || 0) - Number(referencia.value.total_kg)) > 0.0005
    );

    const usarReferencia = () => {
        if (referencia.value?.total_kg === null || referencia.value?.total_kg === undefined) return;
        form.value.total_alimento_consumido_kg = referencia.value.total_kg;
        totalEditadoManual.value = false;
    };

    const claseFilaReferencia = ({ row }) =>
        row.estado === "completa" ? "fila-completa" : row.estado === "parcial" ? "fila-parcial" : "";

    const resumenReferencia = ({ columns }) =>
        columns.map((col, i) => {
            if (i === 0) return "Total";
            if (col.property === "dias_considerados") return referencia.value?.dias_cubiertos ?? "";
            if (col.property === "aporte_kg") return fmtKg(referencia.value?.total_kg);
            return "";
        });

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
                        confirmButtonText: 'Vizualizar Resultados',
                        cancelButtonText: 'Mostrar Biométrias',
                        type: 'success',
                        center: true,
                        closeOnPressEscape: false,
                        closeOnClickModal: false,
                        showClose: false
                    }
                )
                .then(() => {
                    window.location.href = route('biometrias.show', biometriaId);
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
                                        :error="errors.total_alimento_consumido_kg?.[0]"
                                        required
                                    >
                                        <template #label>
                                            <span>Total Alimento Consumido (Kg)</span>
                                            <el-tooltip placement="top" :disabled="!referencia">
                                                <template #content>
                                                    <div v-if="referencia" style="max-width: 280px">
                                                        <div><b>Referencial:</b> {{ fmtKg(referencia.total_kg) }} kg</div>
                                                        <div>
                                                            Periodo: {{ fmtFecha(referencia.fecha_inicial) }} →
                                                            {{ fmtFecha(referencia.fecha_muestreo) }} ({{ referencia.dias }} días)
                                                        </div>
                                                        <div v-if="referencia.semana_desde">
                                                            Semanas {{ referencia.semana_desde }} a {{ referencia.semana_hasta }} de la tabla BFT
                                                        </div>
                                                        <div v-if="referencia.origen_fecha_inicial" class="opacity-75">
                                                            Fecha inicial tomada de: {{ origenFechaInicial[referencia.origen_fecha_inicial] }}
                                                        </div>
                                                        <div v-for="(a, i) in referencia.advertencias" :key="i" class="mt-1">⚠ {{ a }}</div>
                                                    </div>
                                                </template>
                                                <el-icon class="ms-1 text-primary" style="cursor: help; vertical-align: middle">
                                                    <InfoFilled />
                                                </el-icon>
                                            </el-tooltip>
                                        </template>

                                        <el-input
                                            type="number"
                                            class="w-100"
                                            v-model="form.total_alimento_consumido_kg"
                                            :min="0"
                                            step="any"
                                            @input="totalEditadoManual = true"
                                        >
                                            <template #append>
                                                <el-tooltip content="Ver tabla de alimentación" placement="top">
                                                    <el-button
                                                        :icon="View"
                                                        :loading="loadingReferencia"
                                                        :disabled="!referencia?.semanas?.length"
                                                        @click="dialogReferencia = true"
                                                    />
                                                </el-tooltip>
                                            </template>
                                        </el-input>

                                        <div v-if="referencia && referencia.total_kg !== null" class="w-100 small text-muted lh-sm mt-1">
                                            Ref.: <b>{{ fmtKg(referencia.total_kg) }} kg</b> · {{ referencia.dias }} días
                                            <el-link
                                                v-if="difiereDeReferencia"
                                                type="primary"
                                                class="ms-1 small"
                                                @click="usarReferencia"
                                            >Usar</el-link>
                                        </div>
                                        <div v-else-if="referencia?.advertencias?.length" class="w-100 small text-warning lh-sm mt-1">
                                            {{ referencia.advertencias[0] }}
                                        </div>
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

        <el-dialog
            v-model="dialogReferencia"
            title="Tabla de alimentación BFT (referencial)"
            width="960px"
            append-to-body
        >
            <template v-if="referencia">
                <div class="small mb-3">
                    <div v-if="referencia.tabla">
                        <b>Tabla:</b> {{ referencia.tabla.titulo || `#${referencia.tabla.id}` }} ·
                        <b>Población inicial:</b> {{ referencia.tabla.poblacion_inicial }} ·
                        <b>Mortalidad:</b> {{ referencia.tabla.mortalidad_porcentaje }}%
                    </div>
                    <div>
                        <b>Siembra:</b> {{ fmtFecha(referencia.fecha_siembra) }} ·
                        <b>Periodo:</b> {{ fmtFecha(referencia.fecha_inicial) }} → {{ fmtFecha(referencia.fecha_muestreo) }}
                        ({{ referencia.dias }} días)
                        <template v-if="referencia.semana_desde">
                            · <b>Semanas:</b> {{ referencia.semana_desde }} a {{ referencia.semana_hasta }}
                        </template>
                    </div>
                    <div class="text-muted mt-1">
                        <span class="leyenda leyenda-completa"></span> Semana completa (consumo semanal)
                        <span class="leyenda leyenda-parcial ms-3"></span> Semana parcial (consumo diario × días)
                    </div>
                </div>

                <el-alert
                    v-for="(a, i) in referencia.advertencias"
                    :key="i"
                    :title="a"
                    type="warning"
                    :closable="false"
                    show-icon
                    class="mb-2"
                />

                <el-table
                    :data="referencia.semanas"
                    border
                    size="small"
                    max-height="420"
                    :row-class-name="claseFilaReferencia"
                    show-summary
                    :summary-method="resumenReferencia"
                >
                    <el-table-column label="Sem." prop="numero_semana" width="60" align="center" />
                    <el-table-column label="Mes" prop="numero_mes" width="55" align="center" />
                    <el-table-column label="Tipo alimento" prop="tipo_alimento" min-width="110" />
                    <el-table-column label="Fechas" min-width="150" align="center">
                        <template #default="{ row }">
                            {{ fmtFecha(row.fecha_desde) }} – {{ fmtFecha(row.fecha_hasta) }}
                        </template>
                    </el-table-column>
                    <el-table-column label="Peso (g)" prop="ganancia_peso_g" width="85" align="right" />
                    <el-table-column label="C. diario (kg)" prop="consumo_diario_kg" width="105" align="right">
                        <template #default="{ row }">{{ fmtKg(row.consumo_diario_kg, 4) }}</template>
                    </el-table-column>
                    <el-table-column label="C. semanal (kg)" prop="consumo_semanal_kg" width="115" align="right">
                        <template #default="{ row }">{{ fmtKg(row.consumo_semanal_kg) }}</template>
                    </el-table-column>
                    <el-table-column label="Días" prop="dias_considerados" width="60" align="center">
                        <template #default="{ row }">{{ row.dias_considerados || "" }}</template>
                    </el-table-column>
                    <el-table-column label="Aporte (kg)" prop="aporte_kg" width="100" align="right">
                        <template #default="{ row }">
                            <b v-if="row.dias_considerados">{{ fmtKg(row.aporte_kg) }}</b>
                        </template>
                    </el-table-column>
                </el-table>
            </template>

            <template #footer>
                <el-button size="small" @click="dialogReferencia = false">Cerrar</el-button>
                <el-button
                    size="small"
                    type="primary"
                    :disabled="referencia?.total_kg === null || referencia?.total_kg === undefined"
                    @click="usarReferencia(); dialogReferencia = false"
                >
                    Usar {{ fmtKg(referencia?.total_kg) }} kg
                </el-button>
            </template>
        </el-dialog>
    </App>
</template>

<style scoped>
    :deep(.el-table .fila-completa) {
        --el-table-tr-bg-color: var(--el-color-success-light-9);
    }
    :deep(.el-table .fila-parcial) {
        --el-table-tr-bg-color: var(--el-color-warning-light-9);
    }
    .leyenda {
        display: inline-block;
        width: 12px;
        height: 12px;
        border-radius: 2px;
        vertical-align: middle;
        margin-right: 4px;
    }
    .leyenda-completa { background: var(--el-color-success-light-7); }
    .leyenda-parcial  { background: var(--el-color-warning-light-7); }
</style>
