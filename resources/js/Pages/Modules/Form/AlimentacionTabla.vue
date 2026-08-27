<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'
import { ElMessage } from 'element-plus'
import { Plus, Delete, Download, EditPen } from '@element-plus/icons-vue'

const props = defineProps({
    campaniaEspecie: { type: Object, required: true },
    tabla: { type: Object, default: null }, // null = aún no hay tabla calculada
})

// Cada mes tiene siempre 4 semanas fijas. La tabla solo crece/decrece de a
// un mes completo (4 semanas) a la vez, nunca semana por semana suelta.
const SEMANAS_POR_MES = 4

// Si ya existe una tabla calculada, arrancamos en modo "vista"; si no, en modo "formulario".
const modo = ref(props.tabla ? 'vista' : 'formulario')

/* ---------------------------------------------------------------------- */
/* Formulario                                                              */
/* ---------------------------------------------------------------------- */

function semanasIniciales() {
    if (!props.tabla) return []
    return props.tabla.meses.flatMap((mes) =>
        mes.semanas.map((s) => ({
            numero_semana: s.numero_semana,
            ganancia_peso_g: s.ganancia_peso_g,
            tasa_alimentacion_porcentaje: s.tasa_alimentacion_porcentaje,
        }))
    )
}

function mesesIniciales() {
    if (!props.tabla) return []
    return props.tabla.meses.map((mes) => ({
        numero_mes: mes.numero_mes,
        tipo_alimento: mes.tipo_alimento ?? '',
    }))
}

const mesesInicialesForm = mesesIniciales().length
    ? mesesIniciales()
    : [{ numero_mes: 1, tipo_alimento: '' }]

const semanasInicialesForm = semanasIniciales().length
    ? semanasIniciales()
    : Array.from({ length: SEMANAS_POR_MES }, (_, i) => ({
          numero_semana: i + 1,
          ganancia_peso_g: 0,
          tasa_alimentacion_porcentaje: 0,
      }))

const form = useForm({
    titulo: props.tabla?.tabla?.titulo ?? 'TABLA DE ALIMENTACIÓN CALCULADO BFT',
    responsable: props.tabla?.tabla?.responsable ?? '',
    poblacion_inicial: props.tabla?.tabla?.poblacion_inicial ?? 2000,
    mortalidad_porcentaje: props.tabla?.tabla?.mortalidad_porcentaje ?? 10,
    numero_semanas: mesesInicialesForm.length * SEMANAS_POR_MES,
    semanas_por_mes: SEMANAS_POR_MES,
    observaciones: props.tabla?.tabla?.observaciones ?? '',
    horarios: props.tabla?.horarios?.map((h) => ({ hora: h.hora })) ?? [
        { hora: '08:00' },
        { hora: '11:00' },
        { hora: '14:00' },
        { hora: '17:00' },
    ],
    semanas: semanasInicialesForm,
    meses: mesesInicialesForm,
})

// numero_semanas siempre se deriva de la cantidad de meses (cada mes = 4
// semanas fijas), así que se mantiene sincronizado automáticamente.
watch(
    () => form.meses.length,
    (totalMeses) => {
        form.numero_semanas = totalMeses * SEMANAS_POR_MES
    }
)

// Agrega un mes completo (4 semanas nuevas en blanco) al final de la tabla.
function agregarMes() {
    const numeroMes = form.meses.length + 1
    form.meses.push({ numero_mes: numeroMes, tipo_alimento: '' })

    const inicioSemana = form.semanas.length + 1
    for (let i = 0; i < SEMANAS_POR_MES; i++) {
        form.semanas.push({
            numero_semana: inicioSemana + i,
            ganancia_peso_g: 0,
            tasa_alimentacion_porcentaje: 0,
        })
    }
}

// Quita el último mes completo (y sus 4 semanas). No se pueden quitar
// meses intermedios ni semanas sueltas, solo el último mes agregado.
function quitarUltimoMes() {
    if (form.meses.length <= 1) {
        ElMessage.warning('Debe quedar al menos un mes.')
        return
    }
    form.meses.pop()
    form.semanas.splice(form.semanas.length - SEMANAS_POR_MES, SEMANAS_POR_MES)
}

// Agrupa las semanas del formulario por mes, para pintar una sola sección
// de "Tipo de alimento" por bloque de 4 semanas (igual que en el Excel).
const semanasAgrupadasPorMes = computed(() => {
    const grupos = {}
    form.semanas.forEach((semana) => {
        const numeroMes = Math.ceil(semana.numero_semana / SEMANAS_POR_MES) || 1
        if (!grupos[numeroMes]) grupos[numeroMes] = []
        grupos[numeroMes].push(semana)
    })
    return grupos
})

function mesForm(numeroMes) {
    return form.meses.find((m) => m.numero_mes === numeroMes)
}

function agregarHorario() {
    form.horarios.push({ hora: '12:00' })
}

function quitarHorario(index) {
    if (form.horarios.length <= 1) {
        ElMessage.warning('Debe quedar al menos un horario de alimentación.')
        return
    }
    form.horarios.splice(index, 1)
}

function guardar() {
    form.post(route('campana-especie.alimentacion-bft.store', props.campaniaEspecie.id), {
        preserveScroll: true,
        onSuccess: () => {
            modo.value = 'vista'
            ElMessage.success('Tabla de alimentación generada correctamente.')
        },
        onError: () => {
            ElMessage.error('Revisa los campos marcados en rojo.')
        },
    })
}

/* ---------------------------------------------------------------------- */
/* Vista de la tabla ya calculada                                          */
/* ---------------------------------------------------------------------- */

const filasTabla = computed(() => {
    if (!props.tabla) return []
    const filas = []
    props.tabla.meses.forEach((mes) => {
        mes.semanas.forEach((semana, idx) => {
            filas.push({
                ...semana,
                numero_mes: mes.numero_mes,
                tipo_alimento: mes.tipo_alimento,
                consumo_mensual_kg: mes.consumo_mensual_kg,
                esPrimeraSemanaDelMes: idx === 0,
                totalSemanasDelMes: mes.semanas.length,
            })
        })
    })
    return filas
})

// Combina celdas (rowspan) para Mes / Tipo de alimento / Consumo mensual,
// igual que las celdas fusionadas del Excel original.
function combinarCeldas({ row, column }) {
    const columnasAgrupadas = ['numero_mes', 'tipo_alimento', 'consumo_mensual_kg']
    if (columnasAgrupadas.includes(column.property)) {
        return row.esPrimeraSemanaDelMes ? [row.totalSemanasDelMes, 1] : [0, 0]
    }
}
</script>

<template>
    <div class="alimentacion-bft">
        <div class="cabecera">
            <h2>Tabla de Alimentación BFT</h2>

            <div class="acciones-cabecera" v-if="tabla">
                <el-button
                    v-if="modo === 'vista'"
                    :icon="EditPen"
                    @click="modo = 'formulario'"
                >
                    Editar
                </el-button>
                <el-button
                    v-else
                    @click="modo = 'vista'"
                >
                    Cancelar
                </el-button>

                <el-button
                    type="primary"
                    :icon="Download"
                    tag="a"
                    :href="route('campana-especie.alimentacion-bft.pdf', campaniaEspecie.id)"
                    target="_blank"
                >
                    Descargar PDF
                </el-button>
            </div>
        </div>

        <!-- ================= FORMULARIO ================= -->
        <el-card v-if="modo === 'formulario'" shadow="never" class="tarjeta-formulario">
            <el-form label-position="top" :model="form">
                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="Título" :error="form.errors.titulo">
                            <el-input v-model="form.titulo" placeholder="Tabla de Alimentación BFT - Tilapia" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="Responsable" :error="form.errors.responsable">
                            <el-input v-model="form.responsable" placeholder="Ing. responsable" />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-row :gutter="16">
                    <el-col :span="12">
                        <el-form-item label="Población inicial" :error="form.errors.poblacion_inicial">
                            <el-input-number v-model="form.poblacion_inicial" :min="1" style="width: 100%" />
                        </el-form-item>
                    </el-col>
                    <el-col :span="12">
                        <el-form-item label="Mortalidad (%)" :error="form.errors.mortalidad_porcentaje">
                            <el-input-number
                                v-model="form.mortalidad_porcentaje"
                                :min="0"
                                :max="100"
                                :step="0.5"
                                style="width: 100%"
                            />
                        </el-form-item>
                    </el-col>
                </el-row>

                <el-form-item label="Observaciones" :error="form.errors.observaciones">
                    <el-input v-model="form.observaciones" type="textarea" :rows="2" />
                </el-form-item>

                <el-divider content-position="left">Horarios de alimentación</el-divider>
                <el-form-item :error="form.errors.horarios">
                    <div class="lista-horarios">
                        <div v-for="(horario, index) in form.horarios" :key="index" class="fila-horario">
                            <el-time-picker
                                v-model="horario.hora"
                                format="HH:mm"
                                value-format="HH:mm"
                                placeholder="Hora"
                            />
                            <el-button
                                :icon="Delete"
                                circle
                                type="danger"
                                plain
                                @click="quitarHorario(index)"
                            />
                        </div>
                        <el-button :icon="Plus" @click="agregarHorario">Agregar horario</el-button>
                    </div>
                </el-form-item>

                <el-divider content-position="left">Meses (4 semanas fijas por mes)</el-divider>
                <div v-if="form.errors.semanas || form.errors.meses" class="mensaje-error-general">
                    <el-alert type="error" :closable="false" :title="form.errors.semanas || form.errors.meses" />
                </div>

                <div class="controles-meses">
                    <span class="resumen-meses">
                        {{ form.meses.length }} {{ form.meses.length === 1 ? 'mes' : 'meses' }}
                        &middot; {{ form.numero_semanas }} semanas en total
                    </span>
                    <div class="botones-meses">
                        <el-button
                            :icon="Delete"
                            plain
                            :disabled="form.meses.length <= 1"
                            @click="quitarUltimoMes"
                        >
                            Quitar último mes
                        </el-button>
                        <el-button type="primary" :icon="Plus" @click="agregarMes">
                            Agregar mes
                        </el-button>
                    </div>
                </div>

                <el-collapse accordion>
                    <el-collapse-item
                        v-for="(semanasDelMes, numeroMes) in semanasAgrupadasPorMes"
                        :key="numeroMes"
                        :name="numeroMes"
                    >
                        <template #title>
                            <strong>Mes {{ String(numeroMes).padStart(2, '0') }}</strong>
                            <span class="resumen-mes">({{ semanasDelMes.length }} semanas)</span>
                        </template>

                        <el-form-item
                            :label="`Tipo de alimento - Mes ${numeroMes}`"
                            v-if="mesForm(Number(numeroMes))"
                        >
                            <el-input
                                v-model="mesForm(Number(numeroMes)).tipo_alimento"
                                placeholder="Ej. 0.45mm, extruido 2mm..."
                            />
                        </el-form-item>

                        <el-table :data="semanasDelMes" size="small" border>
                            <el-table-column prop="numero_semana" label="Semana" width="90" />
                            <el-table-column label="Ganancia de peso (g)">
                                <template #default="{ row }">
                                    <el-input-number
                                        v-model="row.ganancia_peso_g"
                                        :min="0"
                                        :step="0.1"
                                        :precision="3"
                                        size="small"
                                        style="width: 100%"
                                    />
                                </template>
                            </el-table-column>
                            <el-table-column label="Tasa de alimentación T.A (%)">
                                <template #default="{ row }">
                                    <el-input-number
                                        v-model="row.tasa_alimentacion_porcentaje"
                                        :min="0"
                                        :max="100"
                                        :step="0.1"
                                        :precision="2"
                                        size="small"
                                        style="width: 100%"
                                    />
                                </template>
                            </el-table-column>
                        </el-table>
                    </el-collapse-item>
                </el-collapse>

                <div class="acciones-formulario">
                    <el-button v-if="tabla" @click="modo = 'vista'">Cancelar</el-button>
                    <el-button type="primary" :loading="form.processing" @click="guardar">
                        Calcular y guardar tabla
                    </el-button>
                </div>
            </el-form>
        </el-card>

        <!-- ================= VISTA CALCULADA ================= -->
        <el-card v-else-if="tabla" shadow="never" class="tarjeta-vista">
            <div class="info-tabla">
                <div><strong>Población inicial:</strong> {{ tabla.tabla.poblacion_inicial }}</div>
                <div><strong>Mortalidad:</strong> {{ tabla.tabla.mortalidad_porcentaje }}%</div>
                <div><strong>N° semanas:</strong> {{ tabla.tabla.numero_semanas }}</div>
                <div><strong>Semanas por mes:</strong> {{ tabla.tabla.semanas_por_mes }}</div>
            </div>

            <el-table
                :data="filasTabla"
                border
                size="small"
                :span-method="combinarCeldas"
                style="width: 100%"
            >
                <el-table-column prop="numero_mes" label="Mes" width="70">
                    <template #default="{ row }">Mes {{ String(row.numero_mes).padStart(2, '0') }}</template>
                </el-table-column>
                <el-table-column prop="numero_semana" label="Semana" width="80" />
                <el-table-column prop="ganancia_peso_g" label="Ganancia peso (g)" width="110" />
                <el-table-column label="Población" width="100">
                    <template #default="{ row }">{{ Number(row.poblacion).toLocaleString() }}</template>
                </el-table-column>
                <el-table-column prop="biomasa_kg" label="Biomasa (Kg)" width="100" />
                <el-table-column prop="tasa_alimentacion_porcentaje" label="T.A (%)" width="80" />
                <el-table-column prop="consumo_diario_kg" label="Consumo diario (Kg)" width="130" />
                <el-table-column prop="consumo_semanal_kg" label="Consumo semanal (Kg)" width="140" />
                <el-table-column prop="tipo_alimento" label="Tipo de alimento" width="120" />
                <el-table-column prop="consumo_mensual_kg" label="Consumo mensual (Kg)" width="140" />
                <el-table-column
                    v-for="(horario, index) in tabla.horarios"
                    :key="horario.hora"
                    :label="horario.hora"
                    width="80"
                >
                    <template #default="{ row }">{{ row.frecuencias[index]?.gramos }}</template>
                </el-table-column>
            </el-table>
        </el-card>

        <el-empty v-else description="Aún no hay una tabla de alimentación para esta campaña/especie." />
    </div>
</template>

<style scoped>
.alimentacion-bft {
    display: flex;
    flex-direction: column;
    gap: 16px;
}
.cabecera {
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.acciones-cabecera {
    display: flex;
    gap: 8px;
}
.lista-horarios {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    align-items: center;
}
.fila-horario {
    display: flex;
    align-items: center;
    gap: 6px;
}
.resumen-mes {
    margin-left: 8px;
    color: var(--el-text-color-secondary);
    font-weight: normal;
}
.controles-meses {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 12px;
}
.resumen-meses {
    color: var(--el-text-color-secondary);
    font-size: 13px;
}
.botones-meses {
    display: flex;
    gap: 8px;
}
.info-tabla {
    display: flex;
    gap: 24px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}
.acciones-formulario {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 16px;
}
.mensaje-error-general {
    margin-bottom: 12px;
}
</style>
