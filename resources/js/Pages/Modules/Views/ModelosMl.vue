<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import {
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
        name: "Proyeccion de oxigeno disuelto a una hora",
        description: "Anticipa el oxigeno disuelto de la proxima hora a partir de la serie real de sensores, ya limpia de rachas de sensor atascado.",
        importance: "Da margen para airear antes de que el oxigeno caiga por debajo de lo que toleran los peces.",
        inputs: ["Oxigeno disuelto", "Temperatura", "pH", "Hora del dia"],
        horizon: "1 hora",
        modelType: "Gradient boosting (regresion)",
        icon: DataAnalysis,
    },
    {
        code: MODEL_CODES.oxygen,
        name: "Estado de oxigeno disuelto",
        description: "Compara el oxigeno medido con la saturacion que admite el agua a su temperatura.",
        importance: "Separa lo que depende de la fisica del agua de lo que depende de los peces, la aireacion y el biofloc.",
        inputs: ["Temperatura", "Oxigeno disuelto"],
        horizon: "Lectura actual",
        modelType: "Ecuacion fisica de solubilidad",
        icon: Odometer,
    },
    {
        code: MODEL_CODES.growth,
        name: "Crecimiento de tilapia por temperatura",
        description: "Estima el crecimiento diario en longitud a partir de la temperatura del agua, y lo contrasta con las biometrias reales.",
        importance: "Permite ver si el lote crece lo que su temperatura permite o si algo mas lo esta frenando.",
        inputs: ["Temperatura", "Biometria real"],
        horizon: "1 a 365 dias",
        modelType: "Regresion lineal simple",
        icon: TrendCharts,
    },
    {
        code: MODEL_CODES.water,
        name: "Calidad de agua",
        description: "Clasifica la calidad del agua con una maquina de vectores de soporte a partir de temperatura, pH, oxigeno y nitrato.",
        importance: "Muestra de que variable depende realmente la calidad del agua en este estanque.",
        inputs: ["Temperatura", "pH", "OD", "Ion nitrato"],
        horizon: "Lectura actual",
        modelType: "Maquina de vectores de soporte (SVC, nucleo RBF)",
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
    { id: "6h", name: "Ultimas 6 horas" },
    { id: "24h", name: "Ultimas 24 horas" },
    { id: "7d", name: "Ultimos 7 dias" },
    { id: "30d", name: "Ultimos 30 dias" },
    { id: "90d", name: "Ultimos 90 dias" },
];

const proyeccionesCrecimiento = [
    { id: 1, name: "1 dia" },
    { id: 7, name: "7 dias" },
    { id: 30, name: "30 dias" },
    { id: 90, name: "90 dias" },
    { id: 180, name: "180 dias" },
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
            errorMessage.value = error?.response?.data?.message ?? "Los datos aun se estan preparando. Puedes actualizar en unos segundos.";
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
            motivo: error?.response?.data?.motivo ?? "La validacion todavia se esta calculando.",
        };
    }
};
const validacionDe = (codigo) => validacion.value?.modelos?.[codigo] ?? null;

// Cada modelo ensena una sola formula. El backend del crecimiento devolvia la
// recta de Soderberg y la ley peso-longitud juntas, que son dos modelos
// distintos; y el de oxigeno seguia describiendo la SVR que ya no se usa.
const formulaDe = (modelo) => {
    if (modelo?.code === MODEL_CODES.growth) {
        return "Crecimiento diario en longitud (mm/dia) = -1,6707 + 0,09682 x temperatura (C)";
    }
    if (modelo?.code === MODEL_CODES.svm) {
        return "OD(t + 1 h) = OD(t) + GB( OD reciente, dOD/dt, deficit de saturacion, hora del dia, T, pH )";
    }
    if (modelo?.code === MODEL_CODES.water) {
        return "Clase = SVC-RBF( T, pH, OD, NO3 )";
    }
    return modelo?.formula?.expression ?? "";
};

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
        nombre: "Proyeccion de oxigeno disuelto a una hora",
        pregunta: "Cuanto oxigeno disuelto tendra el agua dentro de una hora",
        tipo: "Gradient boosting (regresion)",
        familia: "Aprendizaje automatico supervisado, entrenado con datos de este estanque",
        calcula: "Oxigeno disuelto dentro de una hora",
        unidad: "mg/L",
        horizonte: "1 hora",
        entradas: ["Oxigeno disuelto de las ultimas 2 horas", "Variacion reciente del oxigeno", "Deficit de saturacion", "Hora del dia", "Temperatura del agua", "pH"],
        formula: "OD(t + 1 h) = OD(t) + variacion estimada por el gradient boosting",
        fuente: "Marco del balance de masas del oxigeno disuelto del Informe 16 (febrero 2026). El modelo y sus variables se desarrollaron en este proyecto; la saturacion se calcula con Benson y Krause (1984).",
        evolucion: "Oxigeno disuelto medido y previsto",
    },
    [MODEL_CODES.oxygen]: {
        nombre: "Estado de oxigeno disuelto",
        pregunta: "Que parte del oxigeno que admite el agua tiene ahora el estanque",
        tipo: "Ecuacion fisica de solubilidad",
        familia: "Modelo determinista: no aprende de datos",
        calcula: "Porcentaje de saturacion de oxigeno",
        unidad: "%",
        horizonte: "Lectura actual",
        entradas: ["Temperatura del agua", "Oxigeno disuelto"],
        formula: "Saturacion (%) = 100 x OD medido / OD de saturacion a la temperatura del agua",
        fuente: "OD de saturacion segun Benson y Krause (1984), ecuacion estandar de APHA para agua dulce. El Informe 15 usa esa concentracion de saturacion en el balance de aireacion.",
        evolucion: "Saturacion de oxigeno medida",
    },
    [MODEL_CODES.growth]: {
        nombre: "Crecimiento de tilapia por temperatura",
        pregunta: "Cuantos milimetros de longitud deberia ganar un pez al dia con esta temperatura",
        tipo: "Regresion lineal simple",
        familia: "Ecuacion publicada, no ajustada con datos de este estanque",
        calcula: "Crecimiento diario en longitud",
        unidad: "mm/dia",
        horizonte: "Diario",
        entradas: ["Temperatura del agua"],
        formula: "Crecimiento diario en longitud (mm/dia) = -1,6707 + 0,09682 x Temperatura (C)",
        fuente: "Informe 17 (marzo 2026), ecuacion 1. Recta de Soderberg para tilapia del Nilo macho, con R2 de 0,95 y valida entre 21 y 30 C.",
        evolucion: "Crecimiento diario segun la temperatura medida",
    },
    [MODEL_CODES.water]: {
        nombre: "Calidad de agua",
        pregunta: "En que clase de calidad esta el agua ahora",
        tipo: "Maquina de vectores de soporte (SVC, nucleo RBF)",
        familia: "Aprendizaje automatico supervisado, clasificacion",
        calcula: "Clase de calidad del agua",
        unidad: "Excelente, Buena, Regular, Mala o Muy mala",
        horizonte: "Lectura actual",
        entradas: ["Temperatura del agua", "pH", "Oxigeno disuelto", "Ion nitrato"],
        formula: "Clase = SVC con nucleo RBF (Temperatura, pH, Oxigeno disuelto, Ion nitrato)",
        fuente: "Entrenado en este proyecto en julio de 2026 con las clases de la ponderacion del equipo. Ninguno de los cuatro informes trata un indice de calidad de agua.",
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
const num = (v, dec = 2) => (v === null || v === undefined || Number.isNaN(Number(v))
    ? "-"
    : Number(v).toLocaleString("es-PE", { maximumFractionDigits: dec }));
const fechaCorta = (iso) => (iso ? new Date(iso).toLocaleString("es-PE", { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" }) : "");

const ejeTiempo = (nombreY) => ({
    grid: { top: 28, left: 12, right: 24, bottom: 48, containLabel: true },
    tooltip: { trigger: "axis" },
    legend: { bottom: 0, textStyle: { fontSize: 11 } },
    dataZoom: [{ type: "inside" }],
    xAxis: { type: "time", splitLine: { show: false } },
    yAxis: { type: "value", name: nombreY, nameLocation: "middle", nameGap: 44, scale: true, splitLine: { lineStyle: { color: "#f1f5f9" } } },
});

// ---------- lectura actual de cada modelo ----------
const lecturaActual = computed(() => {
    const m = activeModel.value;
    const v = validacionDe(selectedCode.value);
    if (!m && !v) return [];
    switch (selectedCode.value) {
    case MODEL_CODES.svm: {
        const p = v?.prevision_actual;
        return [
            { etiqueta: "Oxigeno medido ahora", valor: num(p?.current_mg_l ?? m?.current_value), unidad: "mg/L", pie: p ? `dato de ${fechaCorta(p.from_at)}` : "" },
            { etiqueta: "Previsto dentro de una hora", valor: num(p?.predicted_mg_l), unidad: "mg/L", pie: p ? `para ${fechaCorta(p.for_at)}` : "calculando", destacado: true },
            { etiqueta: "Cambio esperado", valor: p ? (p.change_mg_l > 0 ? "+" : "") + num(p.change_mg_l) : "-", unidad: "mg/L", pie: p && p.predicted_mg_l < 3 ? "por debajo de 3 mg/L" : "" },
        ];
    }
    case MODEL_CODES.oxygen: {
        const fila = (re) => (m?.forecast ?? []).find((f) => re.test(f.label ?? ""));
        const sat = fila(/saturaci/i);
        const def = fila(/d.ficit/i);
        return [
            { etiqueta: "Saturacion", valor: num(sat?.value ?? m?.current_value, 1), unidad: "%", destacado: true },
            { etiqueta: "Deficit de oxigeno", valor: num(def?.value), unidad: "mg/L", pie: "lo que falta para saturar" },
        ];
    }
    case MODEL_CODES.growth: {
        const serie = v?.serie_diaria ?? [];
        const hoy = serie.length ? serie[serie.length - 1] : null;
        const bio = selectedBiometrics.value;
        return [
            { etiqueta: "Temperatura media de hoy", valor: num(hoy?.[1]), unidad: "C" },
            { etiqueta: "Crecimiento diario en longitud", valor: num(hoy?.[2] ?? m?.potential_daily_length_gain_mm_day, 3), unidad: "mm/dia", destacado: true, pie: hoy && (hoy[1] < 21 || hoy[1] > 30) ? "fuera del rango validado" : "" },
            { etiqueta: "Ultima biometria", valor: num(bio?.longitud_promedio_cm, 2), unidad: "cm", pie: "longitud media medida" },
        ];
    }
    case MODEL_CODES.water: {
        const serie = v?.serie_svm ?? [];
        const clase = serie.length ? serie[serie.length - 1][1] : m?.machine_learning?.classification;
        const comp = (re) => (m?.components ?? []).find((c) => re.test(c.variable ?? ""));
        return [
            { etiqueta: "Clase segun el SVM", valor: clase ?? "-", unidad: "", destacado: true },
            { etiqueta: "Oxigeno disuelto", valor: num(comp(/ox.geno/i)?.raw_value), unidad: "mg/L" },
            { etiqueta: "Ion nitrato", valor: num(comp(/nitrato/i)?.raw_value), unidad: "mg/L" },
            { etiqueta: "Temperatura y pH", valor: `${num(comp(/temperatura/i)?.raw_value, 1)} C, ${num(comp(/^ph$/i)?.raw_value, 2)}`, unidad: "" },
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
        const p = v?.prevision_actual;
        const ultimo = ultimoDe(medido);
        const tramo = p && ultimo ? [ultimo, [p.for_at, p.predicted_mg_l]] : [];
        if (!medido.length) return null;
        return {
            ...ejeTiempo("Oxigeno disuelto (mg/L)"),
            series: [
                { name: "Medido", type: "line", showSymbol: false, smooth: true, lineStyle: { color: "#2563eb", width: 2 }, data: medido },
                { name: "Previsto a una hora", type: "line", symbolSize: 9, lineStyle: { color: "#ea580c", width: 2, type: "dashed" }, itemStyle: { color: "#ea580c" }, data: tramo },
                { name: "Umbral de 3 mg/L", type: "line", showSymbol: false, silent: true, lineStyle: { color: "#dc2626", width: 1, type: "dotted" }, data: medido.length ? [[medido[0][0], 3], [p?.for_at ?? ultimo[0], 3]] : [] },
            ],
        };
    }
    case MODEL_CODES.oxygen: {
        const sat = serieDe(m, /saturaci/i, /estimaci/i)?.data ?? [];
        if (!sat.length) return null;
        return {
            ...ejeTiempo("Saturacion de oxigeno (%)"),
            series: [
                { name: "Saturacion medida", type: "line", showSymbol: false, smooth: true, lineStyle: { color: "#7c3aed", width: 2 }, areaStyle: { color: "rgba(124,58,237,0.08)" }, data: sat },
                { name: "Agua saturada (100%)", type: "line", showSymbol: false, silent: true, lineStyle: { color: "#94a3b8", width: 1, type: "dashed" }, data: [[sat[0][0], 100], [sat[sat.length - 1][0], 100]] },
            ],
        };
    }
    case MODEL_CODES.growth: {
        const serie = v?.serie_diaria ?? [];
        if (!serie.length) return null;
        return {
            grid: { top: 28, left: 12, right: 12, bottom: 48, containLabel: true },
            tooltip: { trigger: "axis" },
            legend: { bottom: 0, textStyle: { fontSize: 11 } },
            xAxis: { type: "category", data: serie.map((d) => d[0]), axisLabel: { fontSize: 10 } },
            yAxis: [
                { type: "value", name: "Crecimiento diario en longitud (mm/dia)", nameLocation: "middle", nameGap: 46, splitLine: { lineStyle: { color: "#f1f5f9" } } },
                { type: "value", name: "Temperatura (C)", nameLocation: "middle", nameGap: 36, scale: true, splitLine: { show: false } },
            ],
            series: [
                { name: "Crecimiento diario segun la recta", type: "line", showSymbol: false, smooth: true, lineStyle: { color: "#0d9488", width: 2.5 }, areaStyle: { color: "rgba(13,148,136,0.08)" }, data: serie.map((d) => d[2]) },
                { name: "Temperatura media del dia", type: "line", yAxisIndex: 1, showSymbol: false, smooth: true, lineStyle: { color: "#94a3b8", width: 1.5, type: "dashed" }, data: serie.map((d) => d[1]) },
            ],
        };
    }
    case MODEL_CODES.water: {
        const serie = v?.serie_svm ?? [];
        const orden = v?.orden_clases ?? ["Excelente", "Buena", "Regular", "Mala", "Muy mala"];
        if (!serie.length) return null;
        const escala = [...orden].reverse();
        return {
            grid: { top: 20, left: 12, right: 24, bottom: 30, containLabel: true },
            tooltip: { trigger: "axis" },
            dataZoom: [{ type: "inside" }],
            xAxis: { type: "time", splitLine: { show: false } },
            yAxis: { type: "category", data: escala, splitLine: { show: true, lineStyle: { color: "#f1f5f9" } } },
            series: [
                { name: "Clase del SVM", type: "line", step: "end", showSymbol: false, lineStyle: { color: "#0d9488", width: 2 }, data: serie.map((d) => [d[0], d[1]]) },
            ],
        };
    }
    default:
        return null;
    }
});

const notaEvolucion = computed(() => ({
    [MODEL_CODES.svm]: "Azul, el oxigeno medido. En naranja, lo que el modelo preve para la proxima hora. La linea roja punteada marca los 3 mg/L del umbral de alarma.",
    [MODEL_CODES.oxygen]: "Porcentaje de la capacidad de oxigeno que tiene el agua en cada lectura. Por encima del 100% el agua esta sobresaturada.",
    [MODEL_CODES.growth]: "Cada dia se lleva la temperatura media a la recta, sin factores limitantes. Por eso es un crecimiento posible segun la temperatura, no el crecimiento que se midio.",
    [MODEL_CODES.water]: "Clase que asigna el SVM a cada lectura limpia reciente del estanque.",
}[selectedCode.value] ?? ""));

// La insignia de cada card resume si el modelo acierta frente a lo medido.
// Sustituye al estado que venia del backend, que para la proyeccion de oxigeno
// seguia diciendo "candidato bloqueado" por la SVR que ya no se usa.
const r2Catalogo = (codigo) => {
    const v = validacionDe(codigo);
    if (!v?.disponible) return null;
    const r2 = codigo === MODEL_CODES.water ? v.regresion_multiple?.r2_prueba : v.r2;
    if (r2 === null || r2 === undefined) return null;
    const texto = `R2 ${Number(r2).toLocaleString("es-PE", { maximumFractionDigits: 3 })}`;
    if (r2 >= 0.8) return { texto, clase: "badge-light-success" };
    if (r2 >= 0.5) return { texto, clase: "badge-light-primary" };
    if (r2 >= 0.1) return { texto, clase: "badge-light-warning" };
    return { texto, clase: "badge-light-danger" };
};

const usaVentana = computed(() => [MODEL_CODES.svm, MODEL_CODES.oxygen].includes(selectedCode.value));

const scheduleReload = () => {
    clearTimeout(reloadTimer.value);
    reloadTimer.value = setTimeout(loadModelos, 250);
};

const changePiscigranja = async () => {
    form.value.piscina_id = "T";
    await piscinasOptions();
    scheduleReload();
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
        <section class="module-intro mb-6">
            <div>
                <div class="text-gray-500 fs-7 mb-1">Piscina, datos reales y proyecciones</div>
                <h2 class="fs-2 fw-bold text-dark mb-2">Modelos de aprendizaje automatico</h2>
                <p class="text-gray-600 fs-6 mb-0">Elige un modelo para revisar su proyeccion. El gemelo digital tiene su propio modulo de simulacion de piscina.</p>
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
                            <el-select filterable v-model="form.piscina_id" @change="scheduleReload">
                                <el-option label="Piscina principal" value="T" />
                                <el-option v-for="item in piscinasList" :key="item.id" :label="item.nombre" :value="item.id" />
                            </el-select>
                        </el-form-item>
                    </div>
                    <div class="col-md-4 col-xl-3">
                        <el-form-item label="Ventana visible" class="mb-0">
                            <el-select v-model="form.ventana" @change="scheduleReload">
                                <el-option v-for="item in ventanas" :key="item.id" :label="item.name" :value="item.id" />
                            </el-select>
                        </el-form-item>
                    </div>
                    <div class="col-md-4 col-xl-3">
                        <el-form-item label="Proyeccion de biometria" class="mb-0">
                            <el-select v-model="form.proyeccion_dias" @change="scheduleReload">
                                <el-option v-for="item in proyeccionesCrecimiento" :key="item.id" :label="item.name" :value="item.id" />
                            </el-select>
                        </el-form-item>
                    </div>
                </div>
            </el-form>
        </section>

        <el-alert v-if="errorMessage" class="mb-6" type="warning" :title="errorMessage" show-icon :closable="false">
            <template #default>
                <el-button link type="primary" :loading="loading" @click="loadModelos">Reintentar ahora</el-button>
            </template>
        </el-alert>

        <template v-if="currentView === 'catalog'">
            <section class="catalog-heading mb-5">
                <div>
                    <h3 class="fs-4 fw-bold text-dark mb-1">Selecciona una vista</h3>
                    <p class="text-gray-600 fs-7 mb-0">Cada modelo abre sus variables, horizonte, explicacion y resultados.</p>
                </div>
                <span v-if="loading" class="text-gray-500 fs-8 d-flex align-items-center gap-2"><el-icon class="is-loading"><RefreshRight /></el-icon>Actualizando datos reales</span>
            </section>

            <section class="model-catalog-grid">
                <div v-for="item in catalog" :key="item.code">
                    <button type="button" class="model-choice h-100 text-start" @click="selectModel(item.code)">
                        <span class="model-choice__icon"><el-icon><component :is="item.icon" /></el-icon></span>
                        <span class="model-choice__body">
                            <span class="d-flex justify-content-between align-items-start gap-2">
                                <span class="fw-bold text-dark fs-6">{{ item.name }}</span>
                                
                                <span v-if="r2Catalogo(item.code)" :class="['badge', r2Catalogo(item.code).clase]">{{ r2Catalogo(item.code).texto }}</span>
                            </span>
                            <span class="text-gray-600 fs-7 mt-3">{{ item.description }}</span>
                            <span v-if="item.modelType" class="model-choice__type">{{ item.modelType }}</span>
                            <span class="model-choice__footer">{{ item.horizon }}</span>
                        </span>
                    </button>
                </div>
            </section>

            <section v-if="response" class="snapshot-strip mt-6">
                <div><span>OD actual</span><strong>{{ formatValue(latest.oxigeno_disuelto, latest.oxigeno_disuelto_unit) }}</strong></div>
                <div><span>Ion nitrato</span><strong>{{ formatValue(latest.ion_nitrato, latest.ion_nitrato_unit) }}</strong></div>
                <div><span>Lecturas limpias</span><strong>{{ formatValue(summary.samples) }}</strong></div>
                <div><span>Ventana</span><strong>{{ response.filters?.window_label ?? "-" }}</strong></div>
            </section>
        </template>

        <template v-else-if="currentView === 'detail'">
            <div class="detail-nav mb-5">
                <el-button text :icon="RefreshRight" @click="showCatalog">Volver a modelos</el-button>
                <span class="text-gray-400">/</span>
                <span class="text-gray-600 fs-7">{{ fichaActiva?.nombre ?? activeMeta?.name }}</span>
            </div>

            <template v-if="fichaActiva">
                <!-- CABECERA -->
                <section class="mp-cab mb-6">
                    <div class="mp-cab__icono"><el-icon><component :is="activeMeta?.icon" /></el-icon></div>
                    <div class="mp-cab__texto">
                        <h3 class="mp-cab__nombre">{{ fichaActiva.nombre }}</h3>
                        <p class="mp-cab__pregunta">{{ fichaActiva.pregunta }}.</p>
                    </div>
                    <span class="mp-tipo">{{ fichaActiva.tipo }}</span>
                    <el-button :icon="SetUp" @click="openDigitalTwin(selectedCode)">Ver en gemelo digital</el-button>
                </section>

                <!-- 1. FICHA DEL MODELO -->
                <section class="card card-flush mb-6">
                    <div class="card-header"><h4 class="card-title fw-bold text-dark fs-5"><span class="mp-num">1</span>Ficha del modelo</h4></div>
                    <div class="card-body pt-0">
                        <div class="mp-ficha">
                            <div><small>Tipo de modelo</small><strong>{{ fichaActiva.tipo }}</strong><span>{{ fichaActiva.familia }}</span></div>
                            <div><small>Que calcula</small><strong>{{ fichaActiva.calcula }}</strong><span>{{ fichaActiva.unidad }}</span></div>
                            <div><small>Horizonte</small><strong>{{ fichaActiva.horizonte }}</strong></div>
                        </div>
                        <div class="mp-fila">
                            <small>Variables de entrada</small>
                            <div class="input-tags"><span v-for="e in fichaActiva.entradas" :key="e">{{ e }}</span></div>
                        </div>
                        <div class="mp-fila">
                            <small>Formula</small>
                            <code class="mp-formula">{{ fichaActiva.formula }}</code>
                        </div>
                        <div class="mp-fila">
                            <small>De donde sale</small>
                            <p class="mp-fuente">{{ fichaActiva.fuente }}</p>
                        </div>
                    </div>
                </section>

                <!-- 2. LECTURA ACTUAL -->
                <section class="card card-flush mb-6">
                    <div class="card-header"><h4 class="card-title fw-bold text-dark fs-5"><span class="mp-num">2</span>Lectura actual</h4></div>
                    <div class="card-body pt-0">
                        <el-skeleton v-if="loading && !lecturaActual.length" :rows="2" animated />
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
                        <p v-else-if="!graficoEvolucion" class="text-gray-500 fs-7">No hay datos suficientes para dibujar la evolucion.</p>
                        <template v-else>
                            <ChartFisheye :options="graficoEvolucion" height="360px" />
                            <p class="mp-nota">{{ notaEvolucion }}</p>
                        </template>
                    </div>
                </section>

                <!-- 4. VALIDACION CON DATOS REALES -->
                <ValidacionModelo
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
