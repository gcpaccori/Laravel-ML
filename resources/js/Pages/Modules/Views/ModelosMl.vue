<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import {
    ArrowLeft,
    Bell,
    Connection,
    DataAnalysis,
    Odometer,
    RefreshRight,
    SetUp,
    TrendCharts,
} from "@element-plus/icons-vue";
import ChartFisheye from "@/Components/ChartFisheye.vue";
import LatexFormula from "@/Components/LatexFormula.vue";
import ValidacionModelo from "@/Components/ValidacionModelo.vue";

defineProps({
    title: String,
    toolbar: {
        type: Array,
        required: false,
    },
});

const MODEL_CODES = {
    svm: "SVM_OD_FORECAST_1H",
    oxygen: "OXYGEN_STATUS_MODEL",
    growth: "TILAPIA_GROWTH_TEMPERATURE",
    water: "WATER_QUALITY_INDEX_ICA",
};

const catalog = [
    {
        code: MODEL_CODES.svm,
        name: "Proyección de oxígeno disuelto a una hora",
        description: "Anticipa el oxígeno disuelto de la próxima hora con la serie real del sensor, sin las rachas en que el sensor se quedó atascado.",
        horizon: "Horizonte: 1 hora",
        modelType: "Gradient boosting (regresión)",
        icon: DataAnalysis,
    },
    {
        code: MODEL_CODES.oxygen,
        name: "Estado de oxígeno disuelto",
        description: "Compara el oxígeno medido con el que admite el agua a su temperatura, y muestra si la temperatura explica el oxígeno del estanque.",
        horizon: "Horizonte: lectura actual",
        modelType: "Ecuación física de solubilidad",
        icon: Odometer,
    },
    {
        code: MODEL_CODES.growth,
        name: "Crecimiento de tilapia por temperatura",
        description: "Predice cuántos milímetros de longitud gana el pez al día según la temperatura, y lo contrasta con las biometrías del lote.",
        horizon: "Horizonte: diario, acumulado entre biometrías",
        modelType: "Regresión lineal simple",
        icon: TrendCharts,
    },
    {
        code: MODEL_CODES.water,
        name: "Calidad de agua",
        description: "Clasifica la calidad del agua a partir de temperatura, pH, oxígeno y nitrato, y muestra de qué variable depende.",
        horizon: "Horizonte: lectura actual",
        modelType: "Máquina de vectores de soporte (SVC, núcleo RBF)",
        icon: Connection,
    },
];

const loading = ref(false);
const errorMessage = ref("");
const response = ref(null);
const piscigranjas = ref([]);
const piscinasList = ref([]);
const currentView = ref("catalog");
const selectedCode = ref(null);
const requestController = ref(null);
const reloadTimer = ref(null);

const form = ref({
    piscigranja_id: "T",
    piscina_id: "T",
    ventana: "7d",
    proyeccion_dias: 7,
});

const ventanas = [
    { id: "6h", name: "Últimas 6 horas" },
    { id: "24h", name: "Últimas 24 horas" },
    { id: "7d", name: "Últimos 7 días" },
    { id: "30d", name: "Últimos 30 días" },
    { id: "90d", name: "Últimos 90 días" },
];

const latest = computed(() => response.value?.latest ?? {});
const summary = computed(() => response.value?.summary ?? {});
const models = computed(() => response.value?.models ?? []);
const warnings = computed(() => response.value?.warnings ?? []);
const lifecycle = computed(() => response.value?.lifecycle ?? {});
const activeMeta = computed(() => catalog.find((item) => item.code === selectedCode.value) ?? null);
const activeModel = computed(() => models.value.find((item) => item.code === selectedCode.value) ?? null);
const growthProjection = computed(() => response.value?.tilapia_growth?.length_projection ?? null);
const selectedBiometrics = computed(() => activeModel.value?.biometric_context ?? response.value?.biometrics ?? null);

const formatValue = (value, unit = "") => {
    if (value === null || value === undefined || value === "") return "-";
    const number = Number(value);
    if (!Number.isFinite(number)) return "-";
    return `${number.toLocaleString("es-PE", { maximumFractionDigits: 3 })}${unit ? ` ${unit}` : ""}`;
};

const metricValue = (value) => {
    if (value === null || value === undefined || value === "") return "N/D";
    const number = Number(value);
    return Number.isFinite(number) ? number.toLocaleString("es-PE", { maximumFractionDigits: 4 }) : "N/D";
};

const shortId = (value) => {
    if (!value) return "N/D";
    const text = String(value);
    return text.length > 18 ? `${text.slice(0, 18)}...` : text;
};

const statusLabel = (status) => ({
    asset_activo: "Modelo activo",
    entrenado: "Entrenado",
    calculado: "Calculado",
    candidato_bloqueado: "Entrenado en evaluacion",
    calculo_parcial: "Calculo parcial",
    fuera_de_dominio: "Fuera del dominio",
    sin_datos: "Sin datos",
}[status] ?? status ?? "Disponible");

const statusClass = (status) => {
    if (["asset_activo", "entrenado", "calculado"].includes(status)) return "badge-light-success";
    if (["candidato_bloqueado", "calculo_parcial"].includes(status)) return "badge-light-warning";
    if (["fuera_de_dominio", "sin_datos"].includes(status)) return "badge-light-danger";
    return "badge-light-primary";
};

const interpretationRange = (item) => {
    if (item.range) return item.range;
    const minimum = Number(item.minimum);
    const maximum = Number(item.maximum);
    if (!Number.isFinite(minimum) || !Number.isFinite(maximum)) return "-";
    return `${minimum.toLocaleString("es-PE", { maximumFractionDigits: 1 })} a ${maximum.toLocaleString("es-PE", { maximumFractionDigits: 1 })}`;
};

const hasMetrics = (model) => Object.keys(model?.metrics ?? {}).length > 0;

const modelFormulaTone = (code) => ({
    [MODEL_CODES.svm]: "formula-svm",
    [MODEL_CODES.oxygen]: "formula-oxygen",
    [MODEL_CODES.growth]: "formula-growth",
    [MODEL_CODES.water]: "formula-water",
})[code] ?? "formula-default";

const piscigranjasOptions = async () => {
    const { data } = await axios.get(route("piscigranjas.options"));
    piscigranjas.value = data.data ?? [];
};

const piscinasOptions = async () => {
    if (form.value.piscigranja_id === "T") {
        piscinasList.value = [];
        return;
    }
    const { data } = await axios.get(route("piscigranjas.piscinas", form.value.piscigranja_id));
    piscinasList.value = data ?? [];
};

const loadModelos = async () => {
    requestController.value?.abort();
    const controller = new AbortController();
    requestController.value = controller;
    loading.value = true;
    errorMessage.value = "";
    try {
        const { data } = await axios.get(route("monitoreo.modelosmls.proyecciones"), {
            params: form.value,
            signal: controller.signal,
        });
        response.value = data;
    } catch (error) {
        if (error?.code !== "ERR_CANCELED") {
            errorMessage.value = error?.response?.data?.message ?? "Los datos aún se están preparando. Puedes actualizar en unos segundos.";
        }
    } finally {
        if (requestController.value === controller) loading.value = false;
    }
};

// Validacion de los modelos contra lo medido. Se pide una vez: el calculo
// recorre todo el historico y tarda, y no cambia de un minuto a otro.
const validacion = ref(null);
const cargarValidacion = async () => {
    try {
        const { data } = await axios.get(route("monitoreo.modelosmls.validacion"), {
            params: { piscina_id: form.value.piscina_id },
            timeout: 150000,
        });
        validacion.value = data;
    } catch (error) {
        validacion.value = {
            disponible: false,
            motivo: error?.response?.data?.motivo ?? "La validación todavía se está calculando. Vuelve a entrar en unos segundos.",
        };
    }
};
const validacionDe = (codigo) => validacion.value?.modelos?.[codigo] ?? null;

// ----------------------------------------------------------------------
// Ficha de cada modelo.
//
// La pagina de un modelo sigue siempre el mismo orden -que es, que dice
// ahora, como ha evolucionado y si acierta- y todo lo que se ensena sale de
// aqui. Antes cada bloque venia tal cual del backend y varios describian
// modelos que ya no se usan: la SVR vieja, dos formulas mezcladas en el
// crecimiento o el indice ICA en calidad de agua.
// ----------------------------------------------------------------------
const FICHA = {
    [MODEL_CODES.svm]: {
        nombre: "Proyección de oxígeno disuelto a una hora",
        pregunta: "Cuánto oxígeno disuelto tendrá el agua dentro de una hora",
        tipo: "Gradient boosting (regresión)",
        familia: "Aprendizaje automático supervisado, entrenado con datos de este estanque",
        calcula: "Oxígeno disuelto dentro de una hora",
        unidad: "mg/L",
        horizonte: "1 hora",
        entradas: ["Oxígeno disuelto de las últimas 2 horas", "Variación reciente del oxígeno", "Déficit de saturación", "Hora del día", "Temperatura del agua", "pH"],
        formula: "OD(t + 1 h) = OD(t) + cambio estimado por el gradient boosting",
        fuente: "Marco del balance de masas del oxígeno disuelto del Informe 16 (febrero de 2026). El modelo y sus variables se desarrollaron en este proyecto; la saturación se calcula con Benson y Krause (1984).",
        evolucion: "Oxígeno disuelto medido y previsto",
    },
    [MODEL_CODES.oxygen]: {
        nombre: "Estado de oxígeno disuelto",
        pregunta: "Qué parte del oxígeno que admite el agua tiene ahora el estanque",
        tipo: "Ecuación física de solubilidad",
        familia: "Modelo determinista: no aprende de datos",
        calcula: "Porcentaje de saturación de oxígeno",
        unidad: "%",
        horizonte: "Lectura actual",
        entradas: ["Temperatura del agua", "Oxígeno disuelto"],
        formula: "Saturación (%) = 100 × OD medido ÷ OD de saturación a la temperatura del agua",
        fuente: "OD de saturación según Benson y Krause (1984), la ecuación estándar de APHA para agua dulce. El Informe 15 usa esa concentración de saturación en el balance de aireación.",
        evolucion: "Saturación de oxígeno medida",
    },
    [MODEL_CODES.growth]: {
        nombre: "Crecimiento de tilapia por temperatura",
        pregunta: "Cuántos milímetros de longitud debería ganar un pez al día con esta temperatura",
        tipo: "Regresión lineal simple",
        familia: "Ecuación publicada; no se ajustó con datos de este estanque",
        calcula: "Crecimiento diario en longitud",
        unidad: "mm/día",
        horizonte: "Diario, acumulado entre biometrías",
        entradas: ["Temperatura media del día"],
        formula: "Crecimiento diario en longitud (mm/día) = −1,6707 + 0,09682 × temperatura (°C)",
        fuente: "Informe 17 (marzo de 2026), ecuación 1. Recta de Söderberg para tilapia del Nilo macho, con R² de 0,95 en su publicación y válida entre 21 y 30 °C.",
        evolucion: "Longitud medida frente a la que predice la recta",
    },
    [MODEL_CODES.water]: {
        nombre: "Calidad de agua",
        pregunta: "En qué clase de calidad está el agua ahora",
        tipo: "Máquina de vectores de soporte (SVC, núcleo RBF)",
        familia: "Aprendizaje automático supervisado, clasificación",
        calcula: "Clase de calidad del agua",
        unidad: "Excelente, Buena, Regular, Mala o Muy mala",
        horizonte: "Lectura actual",
        entradas: ["Temperatura del agua", "pH", "Oxígeno disuelto", "Ion nitrato"],
        formula: "Clase = SVC con núcleo RBF (temperatura, pH, oxígeno disuelto, ion nitrato)",
        fuente: "Desarrollado en este proyecto: las clases de referencia salen de la ponderación del equipo sobre las cuatro variables y el SVM aprende a reconocerlas desde las lecturas. En esta página se entrena solo con lecturas de nitrato válido. Ninguno de los cuatro informes trata un índice de calidad de agua.",
        evolucion: "Clase de calidad que asigna el SVM",
    },
};
const fichaActiva = computed(() => FICHA[selectedCode.value] ?? null);

const serieDe = (modelo, patron, excluir = null) => (modelo?.chart?.series ?? []).find(
    (s) => patron.test(s.name ?? "") && !(excluir && excluir.test(s.name ?? "")),
);
const ultimoDe = (datos) => {
    const d = (datos ?? []).filter((p) => Array.isArray(p) && p[1] !== null && p[1] !== undefined);
    return d.length ? d[d.length - 1] : null;
};
// Coma decimal y espacio de miles, igual que en la validacion.
const num = (v, dec = 2) => (v === null || v === undefined || v === "" || Number.isNaN(Number(v))
    ? "-"
    : Number(v).toLocaleString("fr-FR", { maximumFractionDigits: dec }).replace(/\u202f/g, "\u00a0"));
const fechaCorta = (iso) => (iso ? new Date(iso).toLocaleString("es-PE", { day: "numeric", month: "short", hour: "2-digit", minute: "2-digit" }) : "");
const diaCorto = (iso) => (iso ? new Date(`${String(iso).slice(0, 10)}T00:00:00`).toLocaleDateString("es-PE", { day: "numeric", month: "short" }) : "");

// En el eje de tiempo: la fecha en los cambios de dia, la hora en el resto.
const etiquetaTiempo = (valor) => {
    const d = new Date(valor);
    if (d.getHours() === 0 && d.getMinutes() === 0) return d.toLocaleDateString("es-PE", { day: "numeric", month: "short" });
    return d.toLocaleTimeString("es-PE", { hour: "2-digit", minute: "2-digit" });
};

const ejeTiempo = (nombreY) => ({
    grid: { top: 24, left: 40, right: 24, bottom: 48, containLabel: true },
    tooltip: { trigger: "axis" },
    legend: { bottom: 0, textStyle: { fontSize: 11 } },
    xAxis: { type: "time", splitLine: { show: false }, axisLabel: { color: "#6b7280", hideOverlap: true, formatter: etiquetaTiempo } },
    yAxis: {
        type: "value",
        name: nombreY,
        nameLocation: "middle",
        nameGap: 44,
        nameTextStyle: { color: "#4b5563", fontSize: 12, fontWeight: 600 },
        axisLabel: { color: "#6b7280", formatter: (v) => num(v, 1) },
        scale: true,
        splitLine: { lineStyle: { color: "#f1f5f9" } },
    },
});

// ---------- lectura actual de cada modelo ----------
// Todas las paginas leen la misma ultima lectura limpia del sensor, la que
// calcula la validacion, para que el oxigeno de ahora no cambie de una pagina
// a otra.
const lecturaSensor = computed(() => validacion.value?.lectura_actual ?? null);

// Un pH acido puede quedar en una clase alta: se explica con su puntaje y su
// peso dentro de la ponderacion de referencia.
const pieDelPh = (c) => {
    if (c.ph >= 6.5) return "";
    const comp = (c.componentes ?? []).find((x) => /^ph$/i.test(x.variable));
    if (!comp) return "ácido, por debajo del óptimo de 6,5";
    return `ácido: puntúa ${num(comp.puntaje, 0)} de 100 y pesa ${num(comp.peso * 100, 0)} % en la clase`;
};

const lecturaActual = computed(() => {
    const v = validacionDe(selectedCode.value);
    const s = lecturaSensor.value;
    switch (selectedCode.value) {
    case MODEL_CODES.svm: {
        const p = v?.prevision_actual;
        if (!p) return [];
        return [
            { etiqueta: "Oxígeno medido ahora", valor: num(p.current_mg_l), unidad: "mg/L", pie: `lectura de ${fechaCorta(p.from_at)}` },
            { etiqueta: "Previsto dentro de una hora", valor: num(p.predicted_mg_l), unidad: "mg/L", pie: `para las ${new Date(p.for_at).toLocaleTimeString("es-PE", { hour: "2-digit", minute: "2-digit" })}`, destacado: true },
            { etiqueta: "Cambio esperado", valor: `${p.change_mg_l > 0 ? "+" : ""}${num(p.change_mg_l)}`, unidad: "mg/L", pie: p.predicted_mg_l < 3 ? "quedaría por debajo de 3 mg/L" : "por encima del umbral de 3 mg/L" },
        ];
    }
    case MODEL_CODES.oxygen: {
        if (!s) return [];
        return [
            { etiqueta: "Oxígeno medido", valor: num(s.oxigeno_mg_l), unidad: "mg/L", pie: `lectura de ${fechaCorta(s.cuando)}` },
            { etiqueta: "Temperatura del agua", valor: num(s.temperatura_c, 1), unidad: "°C" },
            { etiqueta: "Oxígeno de saturación", valor: num(s.od_saturacion_mg_l), unidad: "mg/L", pie: "lo máximo que admite el agua a esa temperatura" },
            { etiqueta: "Saturación", valor: num(s.saturacion_pct, 1), unidad: "%", destacado: true, pie: `faltan ${num(s.deficit_mg_l)} mg/L para saturar` },
        ];
    }
    case MODEL_CODES.growth: {
        const hoy = v?.ultimo_dia;
        const bio = (v?.longitudes ?? []).at(-1);
        if (!hoy && !bio) return [];
        const diferencia = bio?.previsto_mm ? bio.medido_mm - bio.previsto_mm : null;
        return [
            { etiqueta: "Temperatura media del día", valor: num(hoy?.temperatura_media, 1), unidad: "°C", pie: hoy ? diaCorto(hoy.fecha) : "" },
            { etiqueta: "Crecimiento que predice la recta", valor: num(hoy?.soderberg_mm_dia), unidad: "mm/día", destacado: true, pie: hoy && !hoy.en_rango_validado ? "temperatura fuera del rango validado (21 a 30 °C)" : "dentro del rango validado" },
            { etiqueta: "Última biometría", valor: num(bio?.medido_mm, 1), unidad: "mm", pie: bio ? `longitud media de ${bio.peces} peces, ${diaCorto(bio.fecha)}` : "" },
            { etiqueta: "La recta predecía para esa fecha", valor: num(bio?.previsto_mm, 1), unidad: "mm", pie: diferencia === null ? "" : `el lote midió ${num(Math.abs(diferencia), 1)} mm ${diferencia >= 0 ? "más" : "menos"}` },
        ];
    }
    case MODEL_CODES.water: {
        const c = v?.clase_actual;
        if (!c) return [];
        const nitratoFuera = s && !s.nitrato_valido;
        return [
            { etiqueta: "Clase según el SVM", valor: c.clase, unidad: "", destacado: true, pie: `referencia: ${c.clase_referencia}; lectura de ${fechaCorta(c.cuando)}` },
            { etiqueta: "Oxígeno disuelto", valor: num(c.oxigeno_mg_l), unidad: "mg/L" },
            { etiqueta: "Ion nitrato", valor: num(c.nitrato_mg_l), unidad: "mg/L", pie: nitratoFuera ? "la lectura más reciente trae nitrato fuera de rango; se usa la última válida" : "" },
            { etiqueta: "Temperatura", valor: num(c.temperatura_c, 1), unidad: "°C" },
            { etiqueta: "pH", valor: num(c.ph), unidad: "", pie: pieDelPh(c) },
        ];
    }
    default:
        return [];
    }
});

// ---------- evolucion en el tiempo, solo con la salida de cada modelo ----------
const graficoEvolucion = computed(() => {
    const m = activeModel.value;
    const v = validacionDe(selectedCode.value);
    switch (selectedCode.value) {
    case MODEL_CODES.svm: {
        const medido = serieDe(m, /observad|medid/i, /estimaci/i)?.data ?? [];
        if (!medido.length) return null;
        const p = v?.prevision_actual;
        const tramo = p ? [[p.from_at, p.current_mg_l], [p.for_at, p.predicted_mg_l]] : [];
        const fin = p?.for_at ?? ultimoDe(medido)?.[0];
        return {
            ...ejeTiempo("Oxígeno disuelto (mg/L)"),
            series: [
                { name: "Oxígeno medido", type: "line", showSymbol: false, smooth: true, color: "#2563eb", lineStyle: { width: 2 }, data: medido },
                { name: "Previsto a una hora", type: "line", symbolSize: 8, color: "#ea580c", lineStyle: { width: 2, type: "dashed" }, data: tramo },
                { name: "Umbral de alarma (3 mg/L)", type: "line", showSymbol: false, silent: true, color: "#dc2626", lineStyle: { width: 1, type: "dotted" }, data: [[medido[0][0], 3], [fin, 3]] },
            ],
        };
    }
    case MODEL_CODES.oxygen: {
        const sat = serieDe(m, /saturaci/i, /estimaci/i)?.data ?? [];
        if (!sat.length) return null;
        return {
            ...ejeTiempo("Saturación de oxígeno (%)"),
            series: [
                { name: "Saturación medida", type: "line", showSymbol: false, smooth: true, color: "#7c3aed", lineStyle: { width: 2 }, areaStyle: { color: "rgba(124,58,237,0.08)" }, data: sat },
                { name: "Agua saturada (100 %)", type: "line", showSymbol: false, silent: true, color: "#94a3b8", lineStyle: { width: 1, type: "dashed" }, data: [[sat[0][0], 100], [sat[sat.length - 1][0], 100]] },
            ],
        };
    }
    case MODEL_CODES.growth: {
        const prevista = v?.serie_longitud_prevista ?? [];
        const medidas = (v?.longitudes ?? []).map((l) => [l.fecha, l.medido_mm]);
        if (!prevista.length || !medidas.length) return null;
        const opciones = ejeTiempo("Longitud media del pez (mm)");
        return {
            ...opciones,
            xAxis: { ...opciones.xAxis, axisLabel: { color: "#6b7280", hideOverlap: true, formatter: (val) => new Date(val).toLocaleDateString("es-PE", { day: "numeric", month: "short" }) } },
            yAxis: { ...opciones.yAxis, min: 0, scale: false },
            tooltip: { trigger: "axis", valueFormatter: (val) => `${num(val, 1)} mm` },
            series: [
                { name: "Longitud que predice la recta", type: "line", showSymbol: false, color: "#7c3aed", lineStyle: { width: 2.5 }, data: prevista },
                { name: "Longitud medida en la biometría", type: "line", symbol: "circle", symbolSize: 11, color: "#ea580c", lineStyle: { width: 1.5, type: "dashed" }, itemStyle: { borderColor: "#fff", borderWidth: 2 }, data: medidas },
            ],
        };
    }
    case MODEL_CODES.water: {
        const serie = v?.serie_svm ?? [];
        const orden = v?.orden_clases ?? ["Excelente", "Buena", "Regular", "Mala", "Muy mala"];
        if (!serie.length) return null;
        const opciones = ejeTiempo("");
        return {
            ...opciones,
            grid: { top: 16, left: 12, right: 24, bottom: 30, containLabel: true },
            legend: { show: false },
            yAxis: { type: "category", data: [...orden].reverse(), axisLabel: { color: "#4b5563" }, splitLine: { show: true, lineStyle: { color: "#f1f5f9" } } },
            series: [
                { name: "Clase del SVM", type: "line", step: "end", showSymbol: false, color: "#0d9488", lineStyle: { width: 2 }, data: serie.map((d) => [d[0], d[1]]) },
            ],
        };
    }
    default:
        return null;
    }
});

const notaEvolucion = computed(() => ({
    [MODEL_CODES.svm]: "En azul, el oxígeno medido. En naranja, lo que el modelo prevé para la próxima hora. La línea roja punteada marca los 3 mg/L del umbral de alarma.",
    [MODEL_CODES.oxygen]: "Cada punto de la línea es la saturación de una lectura: el oxígeno medido como porcentaje del máximo que admite el agua a su temperatura. En 100 % el agua estaría saturada.",
    [MODEL_CODES.growth]: "La línea morada parte de la longitud medida en la primera biometría y suma cada día lo que la recta predice con la temperatura media real de ese día. Los puntos naranjas son las biometrías: donde se separan, el lote creció distinto de lo que su temperatura permitía.",
    [MODEL_CODES.water]: "Clase que asigna el SVM a las lecturas limpias más recientes, con nitrato válido.",
}[selectedCode.value] ?? ""));

// La insignia de cada card resume si el modelo acierta frente a lo medido.
// La calidad de agua es un clasificador: no tiene R2, se da su acierto.
const r2Catalogo = (codigo) => {
    const v = validacionDe(codigo);
    if (!v?.disponible) return null;
    const tono = (x, fuerte, medio, debil) => (x >= fuerte ? "badge-light-success" : x >= medio ? "badge-light-primary" : x >= debil ? "badge-light-warning" : "badge-light-danger");
    if (codigo === MODEL_CODES.water) {
        if (v.acierto_svm === null || v.acierto_svm === undefined) return null;
        return { texto: `Acierto ${num(v.acierto_svm * 100, 0)} %`, clase: tono(v.acierto_svm, 0.8, 0.6, 0.6), titulo: "Clase correcta en las lecturas de prueba" };
    }
    if (v.r2 === null || v.r2 === undefined) return null;
    const titulo = codigo === MODEL_CODES.oxygen ? "R² entre el oxígeno medido y la temperatura" : "R² frente a lo medido";
    return { texto: `R² ${Number(v.r2).toLocaleString("fr-FR", { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`, clase: tono(v.r2, 0.8, 0.5, 0.1), titulo };
};

const usaVentana = computed(() => [MODEL_CODES.svm, MODEL_CODES.oxygen].includes(selectedCode.value));

const scheduleReload = () => {
    clearTimeout(reloadTimer.value);
    reloadTimer.value = setTimeout(loadModelos, 250);
};

const cambiarPiscina = () => {
    validacion.value = null;
    scheduleReload();
    cargarValidacion();
};

const changePiscigranja = async () => {
    form.value.piscina_id = "T";
    await piscinasOptions();
    cambiarPiscina();
};

const openModelAlerts = (modelCode = null) => {
    const query = new URLSearchParams();
    if (form.value.piscina_id !== "T") query.set("piscina_id", form.value.piscina_id);
    if (modelCode) query.set("modelo", modelCode);
    const suffix = query.toString();
    window.location.assign(`${route("monitoreo.alarmasmodelos.index")}${suffix ? `?${suffix}` : ""}`);
};

const selectModel = (code) => {
    selectedCode.value = code;
    currentView.value = "detail";
};

const showCatalog = () => {
    currentView.value = "catalog";
    selectedCode.value = null;
};

const openDigitalTwin = (modelCode = null) => {
    const query = new URLSearchParams();
    if (form.value.piscina_id !== "T") query.set("piscina_id", form.value.piscina_id);
    if (modelCode) query.set("modelo", modelCode);
    const suffix = query.toString();
    window.location.assign(`${route("monitoreo.gemelodigitals.index")}${suffix ? `?${suffix}` : ""}`);
};

const relationshipRows = (model) => {
    const chart = model?.relationship?.chart;
    const series = chart?.series?.[0] ?? {};
    const categories = chart?.xAxis?.data ?? chart?.yAxis?.data ?? [];
    return (series.data ?? []).slice(0, 10).map((entry, index) => {
        const value = entry && typeof entry === "object" && !Array.isArray(entry) ? (entry.value ?? entry) : entry;
        const name = entry && typeof entry === "object" && entry.name
            ? entry.name
            : categories[index] ?? `Punto ${index + 1}`;
        if (Array.isArray(value)) {
            return { name, primary: value[0], secondary: value[1] ?? "-" };
        }
        return { name, primary: value, secondary: "-" };
    });
};

onMounted(async () => {
    try {
        await piscigranjasOptions();
        await piscinasOptions();
    } finally {
        loadModelos();
        cargarValidacion();
    }
});

onBeforeUnmount(() => {
    requestController.value?.abort();
    clearTimeout(reloadTimer.value);
});
</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <template v-if="currentView === 'catalog'">
            <section class="module-intro mb-6">
                <div>
                    <p class="text-gray-600 fs-6 mb-0">Cuatro modelos, cada uno contrastado con lo que midieron los sensores del estanque.</p>
                </div>
                <div class="module-intro__actions">
                    <el-button :icon="Bell" @click="openModelAlerts()">Alarmas de modelos</el-button>
                    <el-button :icon="SetUp" @click="openDigitalTwin()">Gemelo digital</el-button>
                    <el-button :icon="RefreshRight" :loading="loading" @click="loadModelos">Actualizar datos</el-button>
                </div>
            </section>

            <section class="filter-strip mb-6">
                <el-form :model="form" label-position="top" class="w-100">
                    <div class="row g-4">
                        <div class="col-md-4 col-xl-3">
                            <el-form-item label="Piscigranja" class="mb-0">
                                <el-select filterable v-model="form.piscigranja_id" @change="changePiscigranja">
                                    <el-option label="Todas" value="T" />
                                    <el-option v-for="item in piscigranjas" :key="item.id" :label="item.nombre" :value="item.id" />
                                </el-select>
                            </el-form-item>
                        </div>
                        <div class="col-md-4 col-xl-3">
                            <el-form-item label="Piscina" class="mb-0">
                                <el-select filterable v-model="form.piscina_id" @change="cambiarPiscina">
                                    <el-option label="Piscina principal" value="T" />
                                    <el-option v-for="item in piscinasList" :key="item.id" :label="item.nombre" :value="item.id" />
                                </el-select>
                            </el-form-item>
                        </div>
                    </div>
                </el-form>
            </section>
        </template>

        <el-alert v-if="errorMessage" class="mb-6" type="warning" :title="errorMessage" show-icon :closable="false">
            <template #default>
                <el-button link type="primary" :loading="loading" @click="loadModelos">Reintentar ahora</el-button>
            </template>
        </el-alert>

        <template v-if="currentView === 'catalog'">
            <section class="model-catalog-grid">
                <div v-for="item in catalog" :key="item.code">
                    <button type="button" class="model-choice h-100 text-start" @click="selectModel(item.code)">
                        <span class="model-choice__icon"><el-icon><component :is="item.icon" /></el-icon></span>
                        <span class="model-choice__body">
                            <span class="d-flex justify-content-between align-items-start gap-2">
                                <span class="fw-bold text-dark fs-6">{{ item.name }}</span>
                                <span v-if="r2Catalogo(item.code)" :class="['badge', r2Catalogo(item.code).clase]" :title="r2Catalogo(item.code).titulo">{{ r2Catalogo(item.code).texto }}</span>
                                <span v-else-if="!validacion" class="badge badge-light text-gray-500">calculando</span>
                            </span>
                            <span class="text-gray-600 fs-7 mt-3">{{ item.description }}</span>
                            <span v-if="item.modelType" class="model-choice__type">{{ item.modelType }}</span>
                            <span class="model-choice__footer">{{ item.horizon }}</span>
                        </span>
                    </button>
                </div>
            </section>

            <section v-if="lecturaSensor" class="snapshot-strip mt-6">
                <div><span>Última lectura del sensor</span><strong>{{ fechaCorta(lecturaSensor.cuando) }}</strong></div>
                <div><span>Oxígeno disuelto</span><strong>{{ num(lecturaSensor.oxigeno_mg_l) }} mg/L</strong></div>
                <div><span>Temperatura</span><strong>{{ num(lecturaSensor.temperatura_c, 1) }} °C</strong></div>
                <div><span>pH</span><strong>{{ num(lecturaSensor.ph) }}</strong></div>
                <div>
                    <span>Ion nitrato</span>
                    <strong v-if="lecturaSensor.nitrato_valido">{{ num(lecturaSensor.nitrato_mg_l) }} mg/L</strong>
                    <strong v-else class="text-gray-500 fs-6">fuera de rango del sensor</strong>
                </div>
                <div><span>Lecturas limpias</span><strong>{{ num(validacion.limpieza?.lecturas_limpias, 0) }} <small class="text-gray-500 fs-8">de {{ num(validacion.limpieza?.lecturas_crudas, 0) }}</small></strong></div>
            </section>
        </template>

        <template v-else-if="currentView === 'detail'">
            <div class="detail-nav mb-5">
                <el-button text :icon="ArrowLeft" @click="showCatalog">Volver a modelos</el-button>
            </div>

            <template v-if="fichaActiva">
                <!-- CABECERA -->
                <section class="mp-cab mb-6">
                    <div class="mp-cab__icono"><el-icon><component :is="activeMeta?.icon" /></el-icon></div>
                    <div class="mp-cab__texto">
                        <h3 class="mp-cab__nombre">{{ fichaActiva.nombre }}</h3>
                        <p class="mp-cab__pregunta">¿{{ fichaActiva.pregunta }}?</p>
                    </div>
                    <el-button :icon="SetUp" @click="openDigitalTwin(selectedCode)">Ver en gemelo digital</el-button>
                </section>

                <!-- 1. FICHA DEL MODELO -->
                <section class="card card-flush mb-6">
                    <div class="card-header"><h4 class="card-title fw-bold text-dark fs-5"><span class="mp-num">1</span>Ficha del modelo</h4></div>
                    <div class="card-body pt-0">
                        <div class="mp-ficha">
                            <div><small>Tipo de modelo</small><strong>{{ fichaActiva.tipo }}</strong><span>{{ fichaActiva.familia }}</span></div>
                            <div><small>Qué calcula</small><strong>{{ fichaActiva.calcula }}</strong><span>{{ fichaActiva.unidad }}</span></div>
                            <div><small>Horizonte</small><strong>{{ fichaActiva.horizonte }}</strong></div>
                        </div>
                        <div class="mp-fila">
                            <small>Variables de entrada</small>
                            <div class="input-tags"><span v-for="e in fichaActiva.entradas" :key="e">{{ e }}</span></div>
                        </div>
                        <div class="mp-fila">
                            <small>Fórmula</small>
                            <code class="mp-formula">{{ fichaActiva.formula }}</code>
                        </div>
                        <div class="mp-fila">
                            <small>De dónde sale</small>
                            <p class="mp-fuente">{{ fichaActiva.fuente }}</p>
                        </div>
                    </div>
                </section>

                <!-- 2. LECTURA ACTUAL -->
                <section class="card card-flush mb-6">
                    <div class="card-header"><h4 class="card-title fw-bold text-dark fs-5"><span class="mp-num">2</span>Lectura actual</h4></div>
                    <div class="card-body pt-0">
                        <el-skeleton v-if="!lecturaActual.length && !validacion" :rows="2" animated />
                        <p v-else-if="!lecturaActual.length" class="text-gray-500 fs-7 mb-0">No hay una lectura válida reciente para este modelo.</p>
                        <div v-else class="mp-lectura">
                            <div v-for="l in lecturaActual" :key="l.etiqueta" :class="['mp-lectura__item', { 'mp-lectura__item--destacado': l.destacado }]">
                                <small>{{ l.etiqueta }}</small>
                                <strong>{{ l.valor }} <em v-if="l.unidad">{{ l.unidad }}</em></strong>
                                <span v-if="l.pie">{{ l.pie }}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- 3. EVOLUCION EN EL TIEMPO -->
                <section class="card card-flush mb-6">
                    <div class="card-header">
                        <h4 class="card-title fw-bold text-dark fs-5"><span class="mp-num">3</span>{{ fichaActiva.evolucion }}</h4>
                        <div v-if="usaVentana" class="card-toolbar">
                            <el-select v-model="form.ventana" class="model-controls__select" @change="scheduleReload">
                                <el-option v-for="item in ventanas" :key="item.id" :label="item.name" :value="item.id" />
                            </el-select>
                        </div>
                    </div>
                    <div class="card-body pt-0">
                        <el-skeleton v-if="!graficoEvolucion && (loading || !validacion)" :rows="6" animated />
                        <p v-else-if="!graficoEvolucion" class="text-gray-500 fs-7">No hay datos suficientes para dibujar la evolución.</p>
                        <template v-else>
                            <ChartFisheye :options="graficoEvolucion" height="360px" />
                            <p class="mp-nota">{{ notaEvolucion }}</p>
                        </template>
                    </div>
                </section>

                <!-- 4. VALIDACION CON DATOS REALES -->
                <ValidacionModelo
                    :numero="4"
                    :codigo="selectedCode"
                    :datos="validacionDe(selectedCode)"
                    :limpieza="validacion?.limpieza"
                />
            </template>
        </template>

    </App>
</template>

<style scoped>
.module-intro,
.catalog-heading,
.detail-nav,
.model-overview,
.model-controls,
.pond-twin__header,
.snapshot-strip,
.lifecycle-strip,
.twin-layout {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.module-intro {
    align-items: flex-end;
}

.module-intro__actions {
    flex: 0 0 auto;
    display: flex;
    gap: 8px;
}

.filter-strip,
.model-controls,
.twin-layout,
.pond-twin,
.snapshot-strip,
.lifecycle-strip,
.warning-strip {
    border: 1px solid #e5e7eb;
    background: #ffffff;
}

.filter-strip {
    padding: 18px 20px;
}

.model-catalog-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
}

.model-choice {
    width: 100%;
    display: flex;
    gap: 14px;
    border: 1px solid #dbe3ee;
    background: #ffffff;
    padding: 20px;
    transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
}

.model-choice:hover,
.model-choice:focus-visible {
    border-color: #4d8dff;
    box-shadow: 0 8px 20px rgba(18, 74, 155, 0.12);
    transform: translateY(-2px);
    outline: none;
}

.model-choice__icon,
.model-overview__icon {
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #0d6efd;
    background: #edf5ff;
    font-size: 21px;
}

.model-overview__icon {
    color: #087f5b;
    background: #eafaf3;
}

.model-choice__body {
    min-width: 0;
    display: flex;
    flex-direction: column;
}

.model-choice__footer {
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
    margin-top: auto;
    padding-top: 16px;
}

.model-choice__type {
    color: #0d6efd;
    font-size: 12px;
    font-weight: 600;
    margin-top: 12px;
}

.snapshot-strip,
.lifecycle-strip {
    align-items: stretch;
    overflow-x: auto;
}

.snapshot-strip > div,
.lifecycle-strip > div {
    min-width: 170px;
    flex: 1;
    padding: 16px 18px;
    border-right: 1px solid #eef1f5;
}

.snapshot-strip > div:last-child,
.lifecycle-strip > div:last-child {
    border-right: 0;
}

.snapshot-strip span,
.lifecycle-strip span {
    display: block;
    color: #64748b;
    font-size: 12px;
}

.snapshot-strip strong {
    display: block;
    color: #172033;
    font-size: 18px;
    margin-top: 4px;
}

.detail-nav {
    justify-content: flex-start;
    gap: 10px;
}

.model-overview {
    align-items: flex-start;
    justify-content: flex-start;
    padding: 24px 0;
    border-bottom: 1px solid #e5e7eb;
}

.model-overview__copy {
    max-width: 850px;
}

.model-overview__action {
    margin-left: auto;
    flex: 0 0 auto;
}

.relationship-table {
    border-top: 1px solid #e5e7eb;
    padding-top: 16px;
}

.model-controls {
    align-items: stretch;
    justify-content: flex-start;
    padding: 16px;
    flex-wrap: wrap;
}

.model-controls__group {
    min-width: 180px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.model-controls__group--wide {
    flex: 1 1 360px;
}

.model-controls__select {
    width: 220px;
    max-width: 100%;
}

.input-tags,
.twin-checkboxes {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.input-tags span {
    border: 1px solid #dbe3ee;
    padding: 5px 8px;
    color: #475569;
    font-size: 12px;
    background: #f8fafc;
}

.result-keyline,
.info-block {
    border: 1px solid #e1e7ef;
    padding: 20px;
    background: #ffffff;
}

.result-keyline span,
.info-block > span {
    display: block;
    color: #64748b;
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
}

.result-keyline strong {
    display: block;
    color: #172033;
    font-size: 28px;
    line-height: 1.2;
    margin: 9px 0 7px;
}

.result-keyline small {
    display: block;
    color: #64748b;
    font-size: 12px;
    line-height: 1.45;
}

.formula-panel {
    border: 1px solid #dbe3ee;
    padding: 22px;
    background: #ffffff;
}

.formula-panel :deep(.katex) {
    color: #172033;
    font-size: 1.12em;
}

.formula-panel__origin {
    border-top: 1px solid #e5e7eb;
    padding-top: 12px;
    color: #64748b;
    font-size: 12px;
    line-height: 1.5;
}

.formula-svm { border-left: 3px solid #0d6efd; }
.formula-oxygen { border-left: 3px solid #16803c; }
.formula-growth { border-left: 3px solid #7048c8; }
.formula-water { border-left: 3px solid #0f766e; }
.formula-default { border-left: 3px solid #64748b; }

.pond-twin {
    padding: 20px;
    background: #f7fbff;
}

.pond-twin__timeline {
    display: flex;
    align-items: center;
    margin-top: 24px;
}

.pond-twin__step {
    width: 31%;
    min-width: 0;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
}

.pond-twin__step small,
.pond-twin__step span {
    color: #64748b;
    font-size: 12px;
}

.pond-twin__step strong {
    color: #172033;
    font-size: 20px;
    margin: 5px 0;
}

.pond-twin__dot {
    width: 12px;
    height: 12px;
    background: #0d6efd;
    display: block;
    margin-bottom: 10px;
}

.pond-twin__dot--current { background: #16803c; }
.pond-twin__dot--future { background: #7048c8; }

.pond-twin__line {
    height: 2px;
    flex: 1;
    background: #b8c7d8;
    margin: 0 12px 30px;
}

.twin-layout {
    align-items: stretch;
}

.twin-inputs,
.twin-reference {
    padding: 22px;
}

.twin-inputs {
    flex: 1 1 66%;
}

.twin-reference {
    flex: 1 1 34%;
    border-left: 1px solid #e5e7eb;
    background: #f8fafc;
}

.twin-inputs label {
    display: block;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 6px;
}

.twin-inputs :deep(.el-input-number),
.twin-inputs :deep(.el-select) {
    width: 100%;
}

.twin-checkboxes {
    margin-top: 9px;
}

.twin-reference__value {
    color: #172033;
    font-weight: 700;
    font-size: 16px;
    margin-top: 10px;
}

.twin-reference p {
    color: #64748b;
    font-size: 13px;
    line-height: 1.55;
    margin-top: 14px;
}

.twin-reference__biometrics {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-top: 14px;
    border-top: 1px solid #dbe3ee;
}

.twin-reference__biometrics span,
.twin-reference__biometrics small { color: #64748b; font-size: 12px; }
.twin-reference__biometrics strong { color: #172033; }

.warning-strip {
    padding: 16px 20px;
}

.warning-strip strong,
.warning-strip span {
    display: block;
    color: #7c5d00;
    font-size: 13px;
}

.warning-strip span { margin-top: 5px; }

@media (max-width: 991px) {
    .module-intro,
    .twin-layout {
        flex-direction: column;
        align-items: stretch;
    }

    .twin-reference { border-left: 0; border-top: 1px solid #e5e7eb; }
    .model-overview__action { margin-left: 0; }
}

@media (max-width: 650px) {
    .model-controls,
    .catalog-heading,
    .pond-twin__header {
        align-items: flex-start;
        flex-direction: column;
    }

    .snapshot-strip > div,
    .lifecycle-strip > div { min-width: 155px; }
    .pond-twin__timeline { align-items: flex-start; }
    .pond-twin__line { margin-top: 5px; }
    .pond-twin__step strong { font-size: 16px; }
    .model-catalog-grid { grid-template-columns: 1fr; }
}

.mp-cab { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; background: #fff; border: 1px solid #eef2f7; border-radius: 12px; padding: 18px 20px; }
.mp-cab__icono { width: 48px; height: 48px; border-radius: 12px; background: #eef2ff; color: #4338ca; display: flex; align-items: center; justify-content: center; font-size: 24px; flex: none; }
.mp-cab__texto { flex: 1 1 320px; min-width: 0; }
.mp-cab__nombre { font-size: 20px; font-weight: 700; color: #111827; margin: 0; }
.mp-cab__pregunta { font-size: 14px; color: #4b5563; margin: 4px 0 0; }
.mp-tipo { font-size: 12px; font-weight: 600; color: #4338ca; background: #eef2ff; border-radius: 999px; padding: 5px 12px; }
.mp-num { display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; background: #111827; color: #fff; font-size: 12px; margin-right: 10px; }
.mp-ficha { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; margin-bottom: 16px; }
.mp-ficha > div { background: #f8fafc; border: 1px solid #eef2f7; border-radius: 8px; padding: 10px 12px; }
.mp-ficha small, .mp-fila small, .mp-lectura__item small { display: block; font-size: 10px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #6b7280; margin-bottom: 4px; }
.mp-ficha strong { display: block; font-size: 14px; color: #111827; line-height: 1.35; }
.mp-ficha span { display: block; font-size: 12px; color: #6b7280; margin-top: 2px; }
.mp-fila { margin-top: 14px; }
.mp-formula { display: inline-block; font-size: 13px; color: #111827; background: #f1f5f9; border-radius: 6px; padding: 6px 10px; white-space: normal; }
.mp-fuente { font-size: 13px; line-height: 1.6; color: #374151; margin: 0; }
.mp-lectura { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
.mp-lectura__item { border: 1px solid #eef2f7; border-radius: 10px; padding: 12px 14px; }
.mp-lectura__item strong { display: block; font-size: 22px; font-weight: 700; color: #111827; }
.mp-lectura__item strong em { font-style: normal; font-size: 13px; font-weight: 600; color: #6b7280; }
.mp-lectura__item span { display: block; font-size: 12px; color: #6b7280; margin-top: 2px; }
.mp-lectura__item--destacado { border-color: #c7d2fe; background: #f5f7ff; }
.mp-lectura__item--destacado strong { color: #3730a3; }
.mp-nota { font-size: 12px; line-height: 1.6; color: #6b7280; margin: 8px 0 0; }
@media (max-width: 900px) { .mp-ficha { grid-template-columns: 1fr; } }
</style>
