<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { QuestionFilled, RefreshRight, Setting, SetUp } from "@element-plus/icons-vue";
import ChartFisheye from "@/Components/ChartFisheye.vue";

defineProps({
    title: String,
    toolbar: { type: Array, required: false },
});

/* ------------------------------------------------------------------ *
 * Traduccion a lenguaje llano. Nada de "maturity" ni "ready_for_policy"
 * en pantalla: cada modelo es un vigilante con nombre corto.
 * ------------------------------------------------------------------ */
const VIGILANTES = {
    WATER_QUALITY_INDEX_ICA: {
        emoji: "\u{1F4A7}",
        nombre: "El agua",
        vigila: "Si el agua esta buena para los peces.",
        ayuda: "Junta la temperatura, el pH, el oxigeno y el nitrato en una sola nota del 0 al 100. Mientras mas alta, mejor esta el agua.",
    },
    TILAPIA_GROWTH_TEMPERATURE: {
        emoji: "\u{1F41F}",
        nombre: "El crecimiento",
        vigila: "Si los peces crecen como deberian.",
        ayuda: "Compara cuanto deberian crecer con la temperatura que hay, contra lo que se midio la ultima vez que se pesaron.",
    },
    SVM_OD_FORECAST_1H: {
        emoji: "\u{1F4A8}",
        nombre: "El oxigeno",
        vigila: "Si va a faltar oxigeno dentro de una hora.",
        ayuda: "Mira como venia el agua en las ultimas horas para adivinar el oxigeno de la proxima hora.",
    },
    LIGHT_FEED_RESPONSE_CLASSIFIER_V1: {
        emoji: "☀️",
        nombre: "La luz",
        vigila: "Si la luz ayuda a que los peces coman.",
        ayuda: "Necesita un sensor de luz dentro del agua y que se anote cuanto comen. Todavia no esta instalado.",
    },
};

const ESTADOS = {
    vigilando: { texto: "Vigilando", tono: "ok", explica: "Esta encendido. Si algo se sale de lo normal, avisa solo." },
    apagado: { texto: "Falta encenderlo", tono: "aviso", explica: "Ya sabe hacer la cuenta, pero nadie le ha dicho todavia a partir de que punto debe avisar." },
    sin_datos: { texto: "Le faltan datos", tono: "off", explica: "No puede trabajar porque le faltan mediciones que hoy nadie registra." },
    pensando: { texto: "Pensando...", tono: "calc", explica: "Esta haciendo la cuenta en este momento." },
};

/* Bandas oficiales del backend (models_engine/deterministic/water_quality.py) */
const notaAgua = (valor) => {
    const n = Number(valor);
    if (!Number.isFinite(n)) return null;
    if (n >= 90) return "Excelente";
    if (n >= 70) return "Buena";
    if (n >= 50) return "Regular";
    if (n >= 25) return "Mala";
    return "Muy mala";
};

const GRAVEDAD = {
    emergencia: { texto: "Urgente", tono: "malo" },
    critico: { texto: "Grave", tono: "malo" },
    advertencia: { texto: "Ojo", tono: "aviso" },
};

const loading = ref(false);
const scenarioLoading = ref(false);
const errorMessage = ref("");
const response = ref(null);
const lightScenarioResult = ref(null);
const detalle = ref(null);
const verAjustes = ref([]);
const piscigranjas = ref([]);
const piscinas = ref([]);
const requestController = ref(null);
const reloadTimer = ref(null);
const warmupTimer = ref(null);

const filters = ref({
    piscigranja_id: "T",
    piscina_id: new URLSearchParams(window.location.search).get("piscina_id") || "T",
    ventana_horas: 24,
});

const lightScenario = ref({
    maximum_lux: 500,
    current_lux: null,
    photoperiod_hours: 12,
    dawn_hour: 6,
    horizon_hours: 24,
});

const windowOptions = [
    { value: 6, label: "Las ultimas 6 horas" },
    { value: 24, label: "El ultimo dia" },
    { value: 168, label: "La ultima semana" },
    { value: 720, label: "El ultimo mes" },
];

const models = computed(() => response.value?.models ?? []);
const summary = computed(() => response.value?.summary ?? {});
const light = computed(() => response.value?.light ?? {});
const avisos = computed(() => response.value?.events ?? []);
const observations = computed(() => response.value?.technical_observations ?? []);
const calculando = computed(() => Boolean(response.value?.meta?.warming));

/* ---------------- La unica respuesta que importa ---------------- */
const estadoGeneral = computed(() => {
    if (loading.value && !response.value) {
        return { emoji: "⏳", titulo: "Un momento", frase: "Estamos mirando la piscina.", tono: "calc" };
    }
    if (errorMessage.value) {
        return { emoji: "\u{1F50C}", titulo: "No pudimos mirar", frase: "Vuelve a intentar en un momento.", tono: "off" };
    }
    const n = avisos.value.length;
    if (n > 0) {
        return {
            emoji: "\u{1F6A8}",
            titulo: "Revisa la piscina",
            frase: n === 1 ? "Hay 1 aviso sin atender." : "Hay " + n + " avisos sin atender.",
            tono: "malo",
        };
    }
    if (Number(summary.value.can_emit ?? 0) > 0) {
        return { emoji: "✅", titulo: "Todo esta bien", frase: "Nada fuera de lo normal ahora mismo.", tono: "ok" };
    }
    return { emoji: "\u{1F319}", titulo: "Nadie esta vigilando", frase: "Falta encender al menos un vigilante.", tono: "off" };
});

/* ---------------- Los vigilantes, en simple ---------------- */
const vigilantes = computed(() => models.value.map((m) => {
    const base = VIGILANTES[m.code] ?? { emoji: "\u{1F514}", nombre: m.name, vigila: m.purpose, ayuda: m.purpose };
    let estado = ESTADOS.sin_datos;
    if (m.alarm_state === "warming" || calculando.value) estado = ESTADOS.pensando;
    else if (m.can_emit) estado = ESTADOS.vigilando;
    else if (m.maturity === "ready_for_policy") estado = ESTADOS.apagado;

    /* Si no esta trabajando, no mostramos un numero: confunde. */
    let dato = null;
    if (estado !== ESTADOS.sin_datos) {
        if (m.code === "WATER_QUALITY_INDEX_ICA") {
            const nota = notaAgua(m.current_value);
            if (nota) dato = { grande: nota, chico: "nota " + Math.round(Number(m.current_value)) + " de 100" };
        } else if (m.current_value !== null && m.current_value !== undefined) {
            dato = {
                grande: Number(m.current_value).toLocaleString("es-PE", { maximumFractionDigits: 1 }),
                chico: m.unit ?? "",
            };
        }
    }

    return { ...base, estado, dato, raw: m };
}));

/* El backend redacta para un tecnico. Aqui se dice en simple. */
const mensajeSimple = (a) => {
    const code = a?.model?.code;
    /* el backend manda predicted_value; el controlador de Laravel lo reexpone como value */
    const v = Number(a?.predicted_value ?? a?.value);
    if (code === "WATER_QUALITY_INDEX_ICA" && Number.isFinite(v)) {
        return "La nota del agua esta en " + Math.round(v) + " de 100 (" + notaAgua(v) + ").";
    }
    if (code === "SVM_OD_FORECAST_1H" && Number.isFinite(v)) {
        return "El oxigeno puede llegar a " + v.toLocaleString("es-PE", { maximumFractionDigits: 1 }) + " mg/L dentro de una hora.";
    }
    if (code === "TILAPIA_GROWTH_TEMPERATURE") {
        return "El crecimiento se esta saliendo de lo esperado.";
    }
    if (code === "LIGHT_FEED_RESPONSE_CLASSIFIER_V1") {
        return "La luz no esta acompanando la alimentacion.";
    }
    return a?.message ?? "";
};

const reglaEnPalabras = (m) => {
    const p = m?.policy;
    if (!p || p.status !== "approved" || p.threshold === null || p.threshold === undefined) return null;
    const dir = { lt: "baje de", lte: "baje de o llegue a", gt: "pase de", gte: "llegue o pase de" }[p.operator] ?? "llegue a";
    const cosa = m.code === "WATER_QUALITY_INDEX_ICA" ? "la nota del agua" : "el valor";
    return "Avisa cuando " + cosa + " " + dir + " " + Number(p.threshold).toLocaleString("es-PE") + ".";
};

const faltantes = (m) => (m?.missing_inputs ?? []);
const esLuz = (m) => m?.code === "LIGHT_FEED_RESPONSE_CLASSIFIER_V1";

const graficoDe = (m) => {
    if (esLuz(m) && lightScenarioResult.value?.chart) return lightScenarioResult.value.chart;
    return m?.projection?.chart ?? null;
};

const cuando = (value) => {
    if (!value) return "";
    const d = new Date(value);
    return Number.isNaN(d.getTime())
        ? ""
        : d.toLocaleString("es-PE", { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" });
};

const nombreDe = (code) => VIGILANTES[code]?.nombre ?? "Un vigilante";
const gravedadDe = (sev) => GRAVEDAD[sev] ?? GRAVEDAD.advertencia;
const textoNota = (o) => (typeof o === "string" ? o : o?.message ?? "");

/* ---------------- Carga ---------------- */
const loadPiscigranjas = async () => {
    try {
        const { data } = await axios.get(route("piscigranjas.options"));
        piscigranjas.value = data.data ?? [];
    } catch {
        piscigranjas.value = [];
    }
};

const loadPiscinas = async () => {
    if (filters.value.piscigranja_id === "T") {
        piscinas.value = [];
        return;
    }
    try {
        const { data } = await axios.get(route("piscigranjas.piscinas", filters.value.piscigranja_id));
        piscinas.value = data.data ?? [];
    } catch {
        piscinas.value = [];
    }
};

const loadDashboard = async (refresh = false) => {
    requestController.value?.abort();
    const controller = new AbortController();
    requestController.value = controller;
    loading.value = true;
    errorMessage.value = "";
    try {
        const { data } = await axios.get(route("monitoreo.alarmasmodelos.datos"), {
            params: {
                piscina_id: filters.value.piscina_id,
                ventana_horas: filters.value.ventana_horas,
                refresh: refresh ? 1 : 0,
            },
            signal: controller.signal,
        });
        response.value = data;
        if (light.value.latest_value !== null && light.value.latest_value !== undefined) {
            lightScenario.value.current_lux = Number(light.value.latest_value);
            lightScenario.value.maximum_lux = Math.max(1, Number(light.value.latest_value));
        }
        clearTimeout(warmupTimer.value);
        if (data?.meta?.warming) warmupTimer.value = setTimeout(() => loadDashboard(false), 3000);
    } catch (error) {
        if (error?.code !== "ERR_CANCELED") {
            errorMessage.value = error?.response?.data?.message ?? "No se pudo leer el estado de la piscina.";
        }
    } finally {
        if (requestController.value === controller) loading.value = false;
    }
};

const scheduleReload = () => {
    clearTimeout(reloadTimer.value);
    reloadTimer.value = setTimeout(() => loadDashboard(false), 250);
};

const changeFarm = async () => {
    filters.value.piscina_id = "T";
    await loadPiscinas();
    scheduleReload();
};

const runLightScenario = async () => {
    scenarioLoading.value = true;
    try {
        const { data } = await axios.post(route("monitoreo.alarmasmodelos.luz.escenario"), lightScenario.value);
        lightScenarioResult.value = data;
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? "No se pudo calcular el escenario de luz.";
    } finally {
        scenarioLoading.value = false;
    }
};

const abrirDetalle = (v) => {
    detalle.value = v;
    if (esLuz(v.raw) && !lightScenarioResult.value) runLightScenario();
};

const openTwin = () => {
    const query = new URLSearchParams({ modelo: detalle.value?.raw?.code ?? "WATER_QUALITY_INDEX_ICA" });
    if (filters.value.piscina_id !== "T") query.set("piscina_id", filters.value.piscina_id);
    window.location.assign(route("monitoreo.gemelodigitals.index") + "?" + query.toString());
};

onMounted(async () => {
    await Promise.all([loadPiscigranjas(), loadPiscinas()]);
    await loadDashboard(false);
    window.Echo?.private("alarmas.modelos").listen(".alarma.generada", () => loadDashboard(false));
});

onBeforeUnmount(() => {
    requestController.value?.abort();
    clearTimeout(reloadTimer.value);
    clearTimeout(warmupTimer.value);
    window.Echo?.leave("alarmas.modelos");
});
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="al">
            <header class="al__top">
                <el-button type="primary" round :icon="RefreshRight" :loading="loading" @click="loadDashboard(true)">
                    Actualizar
                </el-button>
            </header>

            <!-- 1. LA RESPUESTA -->
            <section class="estado" :class="'estado--' + estadoGeneral.tono">
                <span class="estado__emoji">{{ estadoGeneral.emoji }}</span>
                <div>
                    <h2 class="estado__titulo">{{ estadoGeneral.titulo }}</h2>
                    <p class="estado__frase">{{ estadoGeneral.frase }}</p>
                </div>
            </section>

            <!-- 2. LOS AVISOS -->
            <section v-if="avisos.length" class="avisos">
                <h3 class="al__seccion">Que paso</h3>
                <article v-for="a in avisos" :key="a.source_event_id ?? a.id" class="aviso">
                    <span class="aviso__punto" :class="'punto--' + gravedadDe(a.suggested_severity).tono" />
                    <div class="aviso__cuerpo">
                        <strong>{{ nombreDe(a.model?.code) }}</strong>
                        <span>{{ mensajeSimple(a) }}</span>
                        <el-popover placement="bottom-start" :width="320" trigger="click">
                            <template #reference>
                                <button class="aviso__detalle" type="button">Ver el detalle tecnico</button>
                            </template>
                            <p class="pop__d">{{ a.message }}</p>
                        </el-popover>
                    </div>
                    <div class="aviso__lado">
                        <span class="chip" :class="'chip--' + gravedadDe(a.suggested_severity).tono">
                            {{ gravedadDe(a.suggested_severity).texto }}
                        </span>
                        <time>{{ cuando(a.occurred_at) }}</time>
                    </div>
                </article>
            </section>

            <!-- 3. LOS VIGILANTES -->
            <section class="al__vig">
                <h3 class="al__seccion">Quien esta cuidando la piscina</h3>
                <div class="tarjetas">
                    <article
                        v-for="v in vigilantes"
                        :key="v.raw.code"
                        class="tarjeta"
                        :class="'tarjeta--' + v.estado.tono"
                        role="button"
                        tabindex="0"
                        @click="abrirDetalle(v)"
                        @keyup.enter="abrirDetalle(v)"
                    >
                        <el-popover placement="top" :width="260" trigger="click">
                            <template #reference>
                                <button class="tarjeta__info" type="button" aria-label="Que hace" @click.stop>
                                    <el-icon><QuestionFilled /></el-icon>
                                </button>
                            </template>
                            <p class="pop__t">{{ v.nombre }}</p>
                            <p class="pop__d">{{ v.ayuda }}</p>
                        </el-popover>

                        <span class="tarjeta__emoji">{{ v.emoji }}</span>
                        <h4 class="tarjeta__nombre">{{ v.nombre }}</h4>

                        <p v-if="v.dato" class="tarjeta__dato">
                            <span class="tarjeta__grande">{{ v.dato.grande }}</span>
                            <span class="tarjeta__chico">{{ v.dato.chico }}</span>
                        </p>
                        <p v-else class="tarjeta__dato tarjeta__dato--vacio">&mdash;</p>

                        <span class="chip" :class="'chip--' + v.estado.tono">{{ v.estado.texto }}</span>
                        <span class="tarjeta__mas">Ver mas</span>
                    </article>
                </div>
            </section>

            <!-- 4. AJUSTES, plegados -->
            <el-collapse v-model="verAjustes" class="ajustes">
                <el-collapse-item name="ajustes">
                    <template #title><span class="ajustes__t">Ajustes</span></template>
                    <div class="ajustes__grid">
                        <div>
                            <label>Piscigranja</label>
                            <el-select v-model="filters.piscigranja_id" filterable @change="changeFarm">
                                <el-option label="Todas" value="T" />
                                <el-option v-for="i in piscigranjas" :key="i.id" :label="i.nombre" :value="String(i.id)" />
                            </el-select>
                        </div>
                        <div>
                            <label>Piscina</label>
                            <el-select v-model="filters.piscina_id" filterable @change="scheduleReload">
                                <el-option label="Principal" value="T" />
                                <el-option v-for="i in piscinas" :key="i.id" :label="i.nombre" :value="String(i.id)" />
                            </el-select>
                        </div>
                        <div>
                            <label>Que periodo mirar</label>
                            <el-select v-model="filters.ventana_horas" @change="scheduleReload">
                                <el-option v-for="i in windowOptions" :key="i.value" :label="i.label" :value="i.value" />
                            </el-select>
                        </div>
                    </div>
                </el-collapse-item>
            </el-collapse>

            <!-- 5. DETALLE: aqui vive todo lo tecnico -->
            <el-drawer :model-value="Boolean(detalle)" :with-header="false" size="520px" @close="detalle = null">
                <div v-if="detalle" class="det">
                    <header class="det__top">
                        <span class="det__emoji">{{ detalle.emoji }}</span>
                        <div>
                            <h3>{{ detalle.nombre }}</h3>
                            <span class="chip" :class="'chip--' + detalle.estado.tono">{{ detalle.estado.texto }}</span>
                        </div>
                    </header>

                    <p class="det__vigila">{{ detalle.vigila }}</p>
                    <p class="det__explica">{{ detalle.estado.explica }}</p>

                    <div v-if="detalle.dato" class="det__valor">
                        <span class="det__grande">{{ detalle.dato.grande }}</span>
                        <span class="det__chico">{{ detalle.dato.chico }}</span>
                    </div>

                    <div v-if="reglaEnPalabras(detalle.raw)" class="det__regla">
                        <strong>La regla</strong>
                        <p>{{ reglaEnPalabras(detalle.raw) }}</p>
                    </div>

                    <div v-if="faltantes(detalle.raw).length" class="det__falta">
                        <strong>Le falta</strong>
                        <ul>
                            <li v-for="f in faltantes(detalle.raw)" :key="f">{{ f }}</li>
                        </ul>
                    </div>

                    <el-collapse class="det__mas">
                        <el-collapse-item v-if="graficoDe(detalle.raw)" title="Ver el grafico" name="g">
                            <ChartFisheye :option="graficoDe(detalle.raw)" style="height: 260px" />
                        </el-collapse-item>

                        <el-collapse-item v-if="esLuz(detalle.raw)" title="Probar un escenario de luz" name="l">
                            <div class="luz">
                                <div>
                                    <label>Luz maxima (lux)</label>
                                    <el-input-number v-model="lightScenario.maximum_lux" :min="0" :max="200000" :step="50" controls-position="right" />
                                </div>
                                <div>
                                    <label>Horas de luz</label>
                                    <el-input-number v-model="lightScenario.photoperiod_hours" :min="0" :max="24" :step="0.5" controls-position="right" />
                                </div>
                                <div>
                                    <label>Hora de amanecer</label>
                                    <el-input-number v-model="lightScenario.dawn_hour" :min="0" :max="23.5" :step="0.5" controls-position="right" />
                                </div>
                                <el-button type="primary" :loading="scenarioLoading" @click="runLightScenario">Calcular</el-button>
                            </div>
                        </el-collapse-item>

                        <el-collapse-item v-if="detalle.raw.formula" title="Como lo calcula" name="f">
                            <p class="det__mono">{{ detalle.raw.formula.expression }}</p>
                            <p class="det__nota">{{ detalle.raw.formula.detail }}</p>
                            <ul v-if="detalle.raw.formula.conditions">
                                <li v-for="c in detalle.raw.formula.conditions" :key="c">{{ c }}</li>
                            </ul>
                        </el-collapse-item>

                        <el-collapse-item v-if="detalle.raw.policy?.rationale" title="Por que ese limite" name="p">
                            <p class="det__nota">{{ detalle.raw.policy.rationale }}</p>
                        </el-collapse-item>

                        <el-collapse-item v-if="observations.length" title="Notas tecnicas" name="o">
                            <ul>
                                <li v-for="(o, i) in observations" :key="i">{{ textoNota(o) }}</li>
                            </ul>
                        </el-collapse-item>
                    </el-collapse>

                    <el-button class="det__twin" :icon="SetUp" @click="openTwin">Abrir en el gemelo digital</el-button>
                </div>
            </el-drawer>
        </div>
    </App>
</template>

<style scoped>
.al { max-width: 980px; margin: 0 auto; padding: 8px 0 48px; }
.al__top { display: flex; align-items: center; justify-content: flex-end; margin-bottom: 16px; }
.al__seccion { font-size: 15px; font-weight: 700; color: #6b7280; margin: 32px 0 12px; }

.estado { display: flex; align-items: center; gap: 20px; padding: 28px 32px; border-radius: 20px; border: 2px solid; }
.estado__emoji { font-size: 56px; line-height: 1; }
.estado__titulo { font-size: 28px; font-weight: 800; margin: 0 0 4px; }
.estado__frase { font-size: 16px; margin: 0; opacity: .8; }
.estado--ok { background: #f0fdf4; border-color: #86efac; color: #166534; }
.estado--malo { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
.estado--aviso { background: #fffbeb; border-color: #fcd34d; color: #92400e; }
.estado--off { background: #f9fafb; border-color: #e5e7eb; color: #4b5563; }
.estado--calc { background: #eff6ff; border-color: #93c5fd; color: #1e40af; }

.aviso { display: flex; align-items: flex-start; gap: 14px; padding: 16px 18px; background: #fff; border: 1px solid #e5e7eb; border-radius: 14px; margin-bottom: 10px; }
.aviso__punto { width: 12px; height: 12px; border-radius: 50%; margin-top: 6px; flex: none; }
.punto--malo { background: #ef4444; }
.punto--aviso { background: #f59e0b; }
.aviso__cuerpo { flex: 1; display: flex; flex-direction: column; gap: 3px; }
.aviso__cuerpo strong { font-size: 16px; color: #1f2937; }
.aviso__cuerpo span { font-size: 14px; color: #6b7280; }
.aviso__detalle { align-self: flex-start; margin-top: 4px; border: 0; background: transparent; padding: 0; font-size: 12px; color: #9ca3af; cursor: pointer; text-decoration: underline; }
.aviso__detalle:hover { color: #4b5563; }
.aviso__lado { display: flex; flex-direction: column; align-items: flex-end; gap: 6px; }
.aviso__lado time { font-size: 12px; color: #9ca3af; white-space: nowrap; }

.tarjetas { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 14px; }
.tarjeta { position: relative; background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 22px 18px 16px; text-align: center; cursor: pointer; transition: transform .15s, box-shadow .15s, border-color .15s; }
.tarjeta:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0, 0, 0, .07); }
.tarjeta--ok { border-color: #bbf7d0; }
.tarjeta--aviso { border-color: #fde68a; }
.tarjeta--calc { border-color: #bfdbfe; }
.tarjeta__info { position: absolute; top: 10px; right: 10px; border: 0; background: transparent; color: #d1d5db; cursor: pointer; padding: 4px; }
.tarjeta__info:hover { color: #6b7280; }
.tarjeta__emoji { font-size: 40px; display: block; line-height: 1; }
.tarjeta__nombre { font-size: 17px; font-weight: 700; color: #1f2937; margin: 10px 0 8px; }
.tarjeta__dato { margin: 0 0 12px; display: flex; flex-direction: column; gap: 2px; }
.tarjeta__grande { font-size: 24px; font-weight: 800; color: #111827; }
.tarjeta__chico { font-size: 12px; color: #9ca3af; }
.tarjeta__dato--vacio { font-size: 24px; color: #d1d5db; }
.tarjeta__mas { display: block; margin-top: 10px; font-size: 12px; color: #9ca3af; }

.chip { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; }
.chip--ok { background: #dcfce7; color: #166534; }
.chip--aviso { background: #fef3c7; color: #92400e; }
.chip--malo { background: #fee2e2; color: #991b1b; }
.chip--off { background: #f3f4f6; color: #6b7280; }
.chip--calc { background: #dbeafe; color: #1e40af; }

.pop__t { font-weight: 700; margin: 0 0 4px; }
.pop__d { margin: 0; font-size: 13px; color: #4b5563; line-height: 1.5; }

.ajustes { margin-top: 36px; border-top: 1px solid #e5e7eb; }
.ajustes__t { font-weight: 700; color: #6b7280; }
.ajustes__grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; }
.ajustes__grid label { display: block; font-size: 13px; color: #6b7280; margin-bottom: 6px; }
.ajustes__grid :deep(.el-select) { width: 100%; }

.det { padding: 8px 4px 24px; }
.det__top { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
.det__emoji { font-size: 40px; }
.det__top h3 { font-size: 22px; font-weight: 800; margin: 0 0 6px; color: #1f2937; }
.det__vigila { font-size: 16px; color: #1f2937; margin: 0 0 6px; }
.det__explica { font-size: 14px; color: #6b7280; margin: 0 0 20px; }
.det__valor { display: flex; align-items: baseline; gap: 8px; padding: 16px; background: #f9fafb; border-radius: 14px; margin-bottom: 18px; }
.det__grande { font-size: 30px; font-weight: 800; color: #111827; }
.det__chico { font-size: 13px; color: #9ca3af; }
.det__regla, .det__falta { padding: 14px 16px; border-radius: 14px; margin-bottom: 12px; }
.det__regla { background: #eff6ff; }
.det__falta { background: #fffbeb; }
.det__regla strong, .det__falta strong { display: block; font-size: 13px; margin-bottom: 4px; }
.det__regla p { margin: 0; font-size: 15px; }
.det__falta ul { margin: 0; padding-left: 18px; font-size: 14px; }
.det__mas { margin-top: 18px; }
.det__mono { font-family: ui-monospace, Menlo, monospace; font-size: 13px; background: #f3f4f6; padding: 10px; border-radius: 8px; overflow-x: auto; }
.det__nota { font-size: 13px; color: #4b5563; line-height: 1.6; }
.det__twin { width: 100%; margin-top: 20px; }
.luz { display: grid; gap: 12px; }
.luz label { display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px; }
</style>
