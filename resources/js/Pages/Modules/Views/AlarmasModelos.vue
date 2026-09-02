<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { Delete, InfoFilled, QuestionFilled, RefreshRight, Setting, SetUp } from "@element-plus/icons-vue";
import ChartFisheye from "@/Components/ChartFisheye.vue";

defineProps({
    title: String,
    toolbar: { type: Array, required: false },
});

/* Nombre corto y explicacion por modelo. El nombre tecnico real se
   sigue mostrando al lado, no se reemplaza. */
const MODELOS = {
    WATER_QUALITY_INDEX_ICA: {
        img: "/images/modelos/agua.svg",
        corto: "El agua",
        ayuda: "Junta cuatro medidas del agua (temperatura, pH, oxigeno y nitrato) en una sola nota del 0 al 100. Es una formula fija: no aprende, siempre calcula igual.",
    },
    TILAPIA_GROWTH_TEMPERATURE: {
        img: "/images/modelos/crecimiento.svg",
        corto: "El crecimiento",
        ayuda: "Con la temperatura del agua calcula cuantos milimetros deberian crecer los peces por dia, y lo compara con lo que se midio al pesarlos.",
    },
    SVM_OD_FORECAST_1H: {
        img: "/images/modelos/oxigeno.svg",
        corto: "El oxigeno",
        ayuda: "Es el unico que de verdad aprende de datos pasados. Mira como venia el agua e intenta adivinar el oxigeno de la proxima hora.",
    },
    PHOTOPERIOD_GREENHOUSE_V1: {
        img: "/images/modelos/fotoperiodo.svg",
        corto: "El fotoperiodo",
        ayuda: "Compara la luz que mide el sensor dentro del vivero con la luz natural que deberia haber afuera ese dia, segun la ubicacion de la piscigranja. De ahi sale cuantas horas hay luz suficiente para que la tilapia coma, y cuanta luz esta frenando la cubierta.",
    },
    LIGHT_FEED_RESPONSE_CLASSIFIER_V1: {
        img: "/images/modelos/luz.svg",
        corto: "La luz",
        ayuda: "Buscara relacionar la luz dentro del agua con las ganas de comer de los peces. Todavia no existe: falta instalar el sensor y anotar cuanto comen.",
    },
};

/* El estado sale de usage.status, que es especifico. Antes usaba una sola
   etiqueta para todo y decia "le faltan datos" incluso cuando si habia. */
const ESTADOS = {
    en_uso: {
        texto: "Vigilando", tono: "ok",
        explica: "Esta encendido y con una alarma configurada. Si el valor cruza el limite, avisa solo.",
    },
    listo_sin_alarma: {
        texto: "Sin alarma", tono: "aviso",
        explica: "El modelo calcula bien, pero nadie ha definido todavia a partir de que numero debe avisar. Se configura abajo, en Configuracion de alarmas.",
    },
    sombra: {
        texto: "En pruebas", tono: "eval",
        explica: "Ya calcula y su proyeccion se puede revisar, pero todavia esta en modo de prueba: el sistema no le permite disparar alarmas hasta validar su margen de error con biometria real.",
    },
    candidato_bloqueado: {
        texto: "En evaluacion", tono: "eval",
        explica: "El modelo esta entrenado y da resultados, pero todavia no se gano la confianza: en las pruebas no supera al metodo simple de suponer que todo sigue igual. Por eso no se le deja disparar alarmas.",
    },
    fuera_de_dominio: {
        texto: "Fuera de rango", tono: "eval",
        explica: "La formula solo esta validada dentro de un rango. Las condiciones de ahora estan fuera de ese rango, asi que prefiere no responder antes que responder mal.",
    },
    collecting_data: {
        texto: "Falta el sensor", tono: "off",
        explica: "No hay equipo instalado que tome esta medida, asi que no hay nada que calcular todavia.",
    },
    sin_datos: {
        texto: "Sin datos", tono: "off",
        explica: "El equipo existe pero no estan llegando las mediciones que necesita.",
    },
    pensando: {
        texto: "Calculando", tono: "calc",
        explica: "Esta haciendo la cuenta en este momento. En unos segundos se actualiza.",
    },
};

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
    advertencia: { texto: "Atencion", tono: "aviso" },
};

const CRITERIOS = {
    positive_test_r2: "Acierta mejor que tirar al azar",
    minimum_500_windows: "Tiene suficientes datos para entrenar",
    beats_persistence_mae: "Le gana a suponer que todo sigue igual",
    nitrate_unit_verified: "Las unidades del nitrato estan verificadas",
    artifact_and_metrics_stored: "El modelo entrenado quedo guardado",
};

const loading = ref(false);
const scenarioLoading = ref(false);
const errorMessage = ref("");
const response = ref(null);
const lightScenarioResult = ref(null);
const detalle = ref(null);
const abiertos = ref([]);
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
    { value: 6, label: "Ultimas 6 horas" },
    { value: 24, label: "Ultimo dia" },
    { value: 168, label: "Ultima semana" },
    { value: 720, label: "Ultimo mes" },
];

const models = computed(() => response.value?.models ?? []);
const summary = computed(() => response.value?.summary ?? {});
const light = computed(() => response.value?.light ?? {});
const avisos = computed(() => response.value?.events ?? []);
const observations = computed(() => response.value?.technical_observations ?? []);
const calculando = computed(() => Boolean(response.value?.meta?.warming));
const meta = computed(() => response.value?.meta ?? {});

const estadoDe = (m) => {
    if (m.alarm_state === "warming" || calculando.value) return ESTADOS.pensando;
    if (m.can_emit) return ESTADOS.en_uso;
    if (m.maturity === "shadow") return ESTADOS.sombra;
    const u = m.usage?.status;
    if (u === "en_uso") return ESTADOS.listo_sin_alarma;
    if (m.maturity === "ready_for_policy") return ESTADOS.listo_sin_alarma;
    if (u === "candidato_bloqueado") return ESTADOS.candidato_bloqueado;
    if (u === "fuera_de_dominio") return ESTADOS.fuera_de_dominio;
    if (u === "collecting_data" || m.model_status === "sin_sensor") return ESTADOS.collecting_data;
    return ESTADOS.sin_datos;
};

const RANGO_GRAVEDAD = { emergencia: 0, critico: 1, advertencia: 2 };

/* Los avisos se ordenan por gravedad y luego por fecha, y la lista se
   mantiene acotada: crece hacia dentro con scroll, nunca empuja la pagina. */
const avisosOrdenados = computed(() => [...avisos.value].sort((a, b) => {
    const ga = RANGO_GRAVEDAD[a.suggested_severity] ?? 9;
    const gb = RANGO_GRAVEDAD[b.suggested_severity] ?? 9;
    if (ga !== gb) return ga - gb;
    return String(b.occurred_at ?? "").localeCompare(String(a.occurred_at ?? ""));
}));

const verTodosLosAvisos = ref(false);
const TOPE_AVISOS = 4;
const avisosVisibles = computed(() => (verTodosLosAvisos.value
    ? avisosOrdenados.value
    : avisosOrdenados.value.slice(0, TOPE_AVISOS)));

const conteoGravedad = computed(() => {
    const total = { emergencia: 0, critico: 0, advertencia: 0 };
    for (const a of avisos.value) {
        const key = a.suggested_severity in total ? a.suggested_severity : "advertencia";
        total[key] += 1;
    }
    return total;
});

const estadoGeneral = computed(() => {
    if (loading.value && !response.value) {
        return { emoji: "⏳", titulo: "Un momento", frase: "Leyendo el estado de la piscina.", tono: "calc" };
    }
    if (errorMessage.value) {
        return { emoji: "\u{1F50C}", titulo: "No se pudo leer", frase: errorMessage.value, tono: "off" };
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
    const vigilando = Number(summary.value.can_emit ?? 0);
    if (vigilando > 0) {
        return {
            emoji: "✅",
            titulo: "Todo esta bien",
            frase: vigilando === 1
                ? "1 modelo esta vigilando y no encontro nada raro."
                : vigilando + " modelos estan vigilando y no encontraron nada raro.",
            tono: "ok",
        };
    }
    return {
        emoji: "\u{1F319}",
        titulo: "Nadie esta vigilando",
        frase: "Ningun modelo tiene una alarma configurada todavia.",
        tono: "off",
    };
});

const tarjetas = computed(() => models.value.map((m) => {
    const base = MODELOS[m.code] ?? { img: null, corto: m.name, ayuda: m.purpose };
    const estado = estadoDe(m);

    let dato = null;
    if (m.code === "WATER_QUALITY_INDEX_ICA" && m.current_value !== null && m.current_value !== undefined) {
        dato = { grande: notaAgua(m.current_value), chico: "nota " + Math.round(Number(m.current_value)) + " de 100" };
    } else if (m.current_value !== null && m.current_value !== undefined) {
        dato = {
            grande: Number(m.current_value).toLocaleString("es-PE", { maximumFractionDigits: 2 }),
            chico: (m.unit ?? "").replace(/^\//, "de "),
        };
    }
    return { ...base, estado, dato, raw: m };
}));

/* Definicion e interpretacion de la alarma de cada modelo: que vigila, como
   se lee su valor, y que hacer cuando suena. Es lo que convierte un numero
   suelto en una decision. */
const INTERPRETACION = {
    WATER_QUALITY_INDEX_ICA: {
        vigila: "La calidad general del agua, resumida en una nota del 0 al 100 que pondera temperatura, pH, oxigeno y nitrato.",
        unidad: "de 100",
        peor: "abajo",
        escala: [
            { min: 90, max: 100, etiqueta: "Excelente", tono: "ok" },
            { min: 70, max: 90, etiqueta: "Buena", tono: "ok" },
            { min: 50, max: 70, etiqueta: "Regular", tono: "aviso" },
            { min: 25, max: 50, etiqueta: "Mala", tono: "malo" },
            { min: 0, max: 25, etiqueta: "Muy mala", tono: "malo" },
        ],
        siSuena: [
            "Revisar primero oxigeno y pH: son los que mas pesan en la nota.",
            "Comprobar recambio de agua y si se esta sobrealimentando.",
            "Por debajo de 50, suspender alimentacion hasta recuperar.",
        ],
    },
    PHOTOPERIOD_GREENHOUSE_V1: {
        vigila: "Cuantas horas al dia hay luz suficiente dentro del vivero para que la tilapia detecte el alimento y coma.",
        unidad: "horas de luz util",
        peor: "abajo",
        escala: [
            { min: 0, max: 8, etiqueta: "Muy corto", tono: "malo" },
            { min: 8, max: 10, etiqueta: "Corto", tono: "aviso" },
            { min: 10, max: 18, etiqueta: "Adecuado", tono: "ok" },
            { min: 18, max: 24, etiqueta: "Excesivo", tono: "aviso" },
        ],
        siSuena: [
            "Concentrar las raciones en las horas con mas luz.",
            "Revisar suciedad, sombra o deterioro de la cubierta del vivero.",
            "Evaluar iluminacion de apoyo hasta alcanzar 12 h utiles.",
        ],
    },
    TILAPIA_GROWTH_TEMPERATURE: {
        vigila: "Cuanto deberian crecer los peces cada dia con la temperatura del agua que hay, segun la ecuacion de Soderberg.",
        unidad: "mm/dia",
        peor: "abajo",
        escala: [
            { min: 0, max: 0.5, etiqueta: "Muy lento", tono: "malo" },
            { min: 0.5, max: 0.9, etiqueta: "Lento", tono: "aviso" },
            { min: 0.9, max: 1.3, etiqueta: "Esperado", tono: "ok" },
        ],
        siSuena: [
            "Contrastar con la ultima biometria: si tambien cayo, el problema es real.",
            "Revisar temperatura del agua; por debajo de 22 C la ganancia se frena.",
            "Revisar conversion alimenticia: un salto brusco delata alimento sin consumir.",
        ],
    },
    SVM_OD_FORECAST_1H: {
        vigila: "El oxigeno disuelto que habra dentro de una hora, estimado con un modelo entrenado.",
        unidad: "mg/L",
        peor: "abajo",
        escala: [
            { min: 0, max: 4, etiqueta: "Critico", tono: "malo" },
            { min: 4, max: 6.5, etiqueta: "Bajo", tono: "aviso" },
            { min: 6.5, max: 8, etiqueta: "Optimo", tono: "ok" },
            { min: 8, max: 20, etiqueta: "Alto", tono: "aviso" },
        ],
        siSuena: [
            "Encender aireacion antes de que el valor llegue al umbral.",
            "Suspender alimentacion si se proyecta por debajo de 4 mg/L.",
        ],
    },
    LIGHT_FEED_RESPONSE_CLASSIFIER_V1: {
        vigila: "Si la luz dentro del agua acompana la respuesta alimentaria del pez.",
        unidad: "lux",
        peor: "abajo",
        escala: [],
        siSuena: ["Todavia no puede sonar: falta instalar el sensor sumergido."],
    },
};

/* Cuando un modelo no tiene alarma, se propone una con su justificacion,
   para que configurarla no sea adivinar un numero. */
const SUGERIDO = {
    SVM_OD_FORECAST_1H: {
        operador: "lt", umbral: 4, unidad: "mg/L", gravedad: "critico",
        porque: "4 mg/L es el limite critico de oxigeno en la tabla parametro_bandas del propio sistema. Aun asi, este modelo no puede emitir hasta superar al metodo simple en validacion.",
    },
    LIGHT_FEED_RESPONSE_CLASSIFIER_V1: {
        operador: "lt", umbral: 30, unidad: "lux", gravedad: "advertencia",
        porque: "30 lux es el limite por debajo del cual la tilapia come de forma pobre. Requiere sensor sumergido y etiquetas de consumo antes de activarse.",
    },
};

const OPERADOR_TEXTO = { lt: "baje de", lte: "baje de o llegue a", gt: "pase de", gte: "llegue o pase de" };

const bandaDe = (code, valor) => {
    const escala = INTERPRETACION[code]?.escala ?? [];
    const n = Number(valor);
    if (!Number.isFinite(n)) return null;
    return escala.find((b) => n >= b.min && n < b.max) ?? escala[escala.length - 1] ?? null;
};

const configuraciones = computed(() => models.value.map((m) => {
    const meta = MODELOS[m.code] ?? {};
    const info = INTERPRETACION[m.code] ?? {};
    const policy = m.policy ?? {};
    const activa = policy.status === "approved";
    return {
        code: m.code,
        img: meta.img ?? null,
        corto: meta.corto ?? m.name,
        nombre: m.name,
        alarmCode: m.alarm_code,
        policy,
        activa,
        puedeEmitir: Boolean(m.can_emit),
        elegible: m.maturity !== "blocked_inputs",
        bloqueo: m.status_detail,
        frescura: m.data_freshness ?? {},
        valor: m.current_value,
        banda: bandaDe(m.code, m.current_value),
        info,
        sugerido: SUGERIDO[m.code] ?? null,
        raw: m,
    };
}));

const comandoDe = (c) => {
    const p = c.activa ? c.policy : c.sugerido;
    if (!p) return null;
    const op = c.activa ? p.operator : p.operador;
    const th = c.activa ? p.threshold : p.umbral;
    const un = c.activa ? p.unit : p.unidad;
    const sv = c.activa ? p.severity : p.gravedad;
    const code = c.activa ? p.code : (c.corto.replace(/^El |^La /, "").toUpperCase() + "-NUEVA");
    return `php artisan model-alerts:approve-policy ${code} \\\n  --model=${c.code} --operator=${op} --threshold=${th} \\\n  --unit=${un} --severity=${sv} --reason="..." --approved-by=1`;
};

const reglaEnPalabras = (m) => {
    const p = m?.policy;
    if (!p || p.status !== "approved" || p.threshold === null || p.threshold === undefined) return null;
    const dir = { lt: "baje de", lte: "baje de o llegue a", gt: "pase de", gte: "llegue o pase de" }[p.operator] ?? "llegue a";
    const cosa = {
        WATER_QUALITY_INDEX_ICA: "la nota del agua",
        PHOTOPERIOD_GREENHOUSE_V1: "las horas de luz util",
    }[m.code] ?? "el valor";
    return "Avisa cuando " + cosa + " " + dir + " " + Number(p.threshold).toLocaleString("es-PE") + ".";
};

const mensajeSimple = (a) => {
    const code = a?.model?.code;
    const v = Number(a?.predicted_value ?? a?.value);
    if (code === "WATER_QUALITY_INDEX_ICA" && Number.isFinite(v)) {
        return "La nota del agua esta en " + Math.round(v) + " de 100 (" + notaAgua(v) + ").";
    }
    if (code === "SVM_OD_FORECAST_1H" && Number.isFinite(v)) {
        return "El oxigeno puede llegar a " + v.toLocaleString("es-PE", { maximumFractionDigits: 1 }) + " mg/L dentro de una hora.";
    }
    if (code === "PHOTOPERIOD_GREENHOUSE_V1" && Number.isFinite(v)) {
        return "Solo hay " + v.toLocaleString("es-PE", { maximumFractionDigits: 0 })
            + " horas de luz util al dia; lo recomendado son 10 o mas.";
    }
    if (code === "TILAPIA_GROWTH_TEMPERATURE") return "El crecimiento se esta saliendo de lo esperado.";
    if (code === "LIGHT_FEED_RESPONSE_CLASSIFIER_V1") return "La luz no esta acompanando la alimentacion.";
    return a?.message ?? "";
};

const esLuz = (m) => m?.code === "LIGHT_FEED_RESPONSE_CLASSIFIER_V1";

/* ------------------------------------------------------------------ *
 * Gráficos. El backend manda opciones de ECharts crudas: titulo dentro
 * del lienzo, toolbox con iconos tecnicos, ejes por defecto. Aqui se
 * reestilizan para que se lean de lejos, en un proyector.
 * ------------------------------------------------------------------ */
const PALETA = ["#2563eb", "#0d9488", "#7c3aed", "#ea580c", "#0891b2"];

const BANDAS_ICA = [
    { min: 90, max: 100, label: "Excelente", color: "rgba(22,163,74,0.10)" },
    { min: 70, max: 90, label: "Buena", color: "rgba(132,204,22,0.10)" },
    { min: 50, max: 70, label: "Regular", color: "rgba(245,158,11,0.10)" },
    { min: 25, max: 50, label: "Mala", color: "rgba(239,68,68,0.10)" },
    { min: 0, max: 25, label: "Muy mala", color: "rgba(153,27,27,0.12)" },
];

const tieneDatos = (chart) => (chart?.series ?? []).some((s) => Array.isArray(s.data) && s.data.length > 0);

const ejeLimpio = (eje, esValor) => ({
    ...(eje ?? {}),
    /* el nombre del eje se solapa con la esquina; la unidad ya se dice
       en el titulo de la seccion y en el tooltip */
    name: "",
    nameTextStyle: { color: "#94a3b8", fontSize: 11 },
    axisLine: { show: false },
    axisTick: { show: false },
    axisLabel: { color: "#64748b", fontSize: 12, hideOverlap: true },
    splitLine: esValor ? { lineStyle: { color: "#f1f5f9", width: 1 } } : { show: false },
});

const mejorarGrafico = (raw, modelo) => {
    if (!raw) return null;
    const o = JSON.parse(JSON.stringify(raw));

    /* el titulo ya esta en la interfaz; el toolbox son iconos que nadie
       usa mientras se expone */
    delete o.title;
    delete o.toolbox;

    o.color = PALETA;
    o.grid = { top: 22, left: 8, right: 18, bottom: 34, containLabel: true };
    o.legend = {
        bottom: 0,
        icon: "roundRect",
        itemWidth: 14,
        itemHeight: 4,
        itemGap: 18,
        textStyle: { fontSize: 12, color: "#475569" },
    };
    o.tooltip = {
        ...(o.tooltip ?? {}),
        trigger: "axis",
        backgroundColor: "rgba(15,23,42,0.94)",
        borderWidth: 0,
        padding: [8, 12],
        textStyle: { color: "#f8fafc", fontSize: 12 },
        axisPointer: { type: "line", lineStyle: { color: "#cbd5e1", width: 1 } },
    };

    o.xAxis = Array.isArray(o.xAxis) ? o.xAxis.map((e) => ejeLimpio(e, false)) : ejeLimpio(o.xAxis, false);
    o.yAxis = Array.isArray(o.yAxis) ? o.yAxis.map((e) => ejeLimpio(e, true)) : ejeLimpio(o.yAxis, true);

    /* zoom con la rueda, sin la barra inferior que ensucia */
    o.dataZoom = [{ type: "inside", xAxisIndex: [0], filterMode: "none" }];

    o.series = (o.series ?? []).map((s, i) => {
        if (s.type === "line") {
            return {
                ...s,
                smooth: 0.25,
                showSymbol: false,
                lineStyle: { width: i === 0 ? 3 : 2.5, ...(i > 0 ? { type: "dashed" } : {}) },
                emphasis: { focus: "series" },
                areaStyle: i === 0
                    ? { color: { type: "linear", x: 0, y: 0, x2: 0, y2: 1, colorStops: [
                        { offset: 0, color: "rgba(37,99,235,0.16)" },
                        { offset: 1, color: "rgba(37,99,235,0.01)" },
                    ] } }
                    : undefined,
            };
        }
        if (s.type === "bar") {
            return {
                ...s,
                barMaxWidth: 26,
                itemStyle: { ...(s.itemStyle ?? {}), borderRadius: [6, 6, 0, 0] },
                label: { show: true, position: "top", fontSize: 11, color: "#475569" },
            };
        }
        return s;
    });

    /* El ICA se entiende solo si se ven las bandas de calidad detras */
    if (modelo?.code === "WATER_QUALITY_INDEX_ICA" && o.series[0]) {
        o.yAxis = { ...(Array.isArray(o.yAxis) ? o.yAxis[0] : o.yAxis), min: 0, max: 100, scale: false };
        o.series[0].markArea = {
            silent: true,
            data: BANDAS_ICA.map((b) => ([
                {
                    yAxis: b.min,
                    itemStyle: { color: b.color },
                    label: {
                        show: true,
                        position: "insideTopRight",
                        offset: [-6, 2],
                        formatter: b.label,
                        color: "#94a3b8",
                        fontSize: 11,
                    },
                },
                { yAxis: b.max },
            ])),
        };
        const umbral = modelo?.policy?.status === "approved" ? Number(modelo.policy.threshold) : null;
        o.series[0].markLine = Number.isFinite(umbral)
            ? {
                silent: true,
                symbol: "none",
                data: [{
                    yAxis: umbral,
                    label: {
                        formatter: "Avisa por debajo de " + umbral,
                        position: "insideStartBottom",
                        color: "#dc2626",
                        fontSize: 11,
                        fontWeight: "bold",
                        backgroundColor: "rgba(255,255,255,0.85)",
                        padding: [3, 6],
                        borderRadius: 4,
                    },
                    lineStyle: { color: "#dc2626", type: "dashed", width: 2 },
                }],
            }
            : undefined;
    }

    return o;
};


/* Version reducida del grafico de proyeccion para meterla dentro de la
   tarjeta: sin ejes, sin leyenda, sin rejilla. Solo la forma. */
const miniGrafico = (m) => {
    const raw = esLuz(m) && lightScenarioResult.value?.chart
        ? lightScenarioResult.value.chart
        : m?.projection?.chart ?? null;
    if (!tieneDatos(raw)) return null;
    const o = JSON.parse(JSON.stringify(raw));
    const color = { ok: "#16a34a", aviso: "#d97706", eval: "#7c3aed", off: "#9ca3af", calc: "#2563eb" };
    const tono = estadoDe(m).tono;
    const linea = color[tono] ?? "#2563eb";
    return {
        grid: { top: 4, left: 2, right: 2, bottom: 2, containLabel: false },
        xAxis: { type: o.xAxis?.type === "time" ? "time" : "category", show: false,
                 data: Array.isArray(o.xAxis) ? undefined : o.xAxis?.data },
        yAxis: { type: "value", show: false, scale: true },
        tooltip: { trigger: "axis", backgroundColor: "rgba(15,23,42,.94)", borderWidth: 0,
                   textStyle: { color: "#f8fafc", fontSize: 11 } },
        series: (o.series ?? []).slice(0, 2).map((serie, i) => ({
            name: serie.name,
            type: serie.type === "bar" ? "bar" : "line",
            data: serie.data,
            smooth: 0.3,
            showSymbol: false,
            lineStyle: { width: i === 0 ? 2.5 : 1.8, type: i === 0 ? "solid" : "dashed", color: linea },
            itemStyle: { color: linea, borderRadius: [3, 3, 0, 0] },
            areaStyle: i === 0 && serie.type !== "bar"
                ? { color: { type: "linear", x: 0, y: 0, x2: 0, y2: 1, colorStops: [
                    { offset: 0, color: linea + "33" }, { offset: 1, color: linea + "05" }] } }
                : undefined,
        })),
    };
};

const graficoDe = (m) => {
    const raw = esLuz(m) && lightScenarioResult.value?.chart ? lightScenarioResult.value.chart : m?.projection?.chart ?? null;
    if (!tieneDatos(raw)) return null;
    return mejorarGrafico(raw, m);
};

const graficoRelacion = (m) => {
    const raw = m?.relationship?.chart ?? null;
    if (!tieneDatos(raw)) return null;
    return mejorarGrafico(raw, null);
};

/* ------------------------------------------------------------------ *
 * Metricas. En vez de volcar r2/mae/rmse, se compara el modelo contra
 * el metodo simple y se dice quien gana.
 * ------------------------------------------------------------------ */
const METRICAS = {
    r2: "Qué tan bien explica los datos (1 es perfecto)",
    mae: "Error promedio",
    rmse: "Error promedio, castigando los fallos grandes",
    cv_best_mae: "Error promedio durante el entrenamiento",
    validation_r2: "Explicación sobre datos que nunca vio",
    validation_mae: "Error sobre datos que nunca vio",
    validation_rmse: "Error grande sobre datos que nunca vio",
    persistence_r2: "Explicación del método simple",
    persistence_mae: "Error del método simple",
    persistence_rmse: "Error grande del método simple",
};

const comparativa = (m) => {
    const x = m?.metrics ?? {};
    const modelo = Number(x.validation_mae ?? x.mae);
    const simple = Number(x.persistence_mae);
    if (!Number.isFinite(modelo) || !Number.isFinite(simple)) return null;
    const ganaModelo = modelo < simple;
    const veces = simple > 0 ? modelo / simple : null;
    return {
        modelo,
        simple,
        ganaModelo,
        veces,
        frase: ganaModelo
            ? "El modelo acierta mejor que el método simple. Por eso se le puede confiar una alarma."
            : "El método simple —suponer que el valor no cambia— acierta mejor que el modelo."
                + (veces ? " Se equivoca " + veces.toLocaleString("es-PE", { maximumFractionDigits: 0 }) + " veces más." : "")
                + " Por eso el sistema no le deja disparar alarmas todavía.",
    };
};

const metricaTexto = (k) => METRICAS[k] ?? k.replace(/_/g, " ");

/* El grafico del modelo que si esta vigilando va a la vista principal:
   es el que se proyecta al exponer. */
const modeloDestacado = computed(() => models.value.find((m) => m.can_emit && graficoDe(m))
    ?? models.value.find((m) => graficoDe(m))
    ?? null);
const graficoDestacado = computed(() => (modeloDestacado.value ? graficoDe(modeloDestacado.value) : null));
const tituloDestacado = computed(() => {
    const m = modeloDestacado.value;
    if (!m) return "";
    const corto = MODELOS[m.code]?.corto ?? m.name;
    return "Como viene " + corto.replace(/^El |^La /, (x) => x.toLowerCase());
});
const nombreDe = (code) => MODELOS[code]?.corto ?? "Un modelo";
const imagenDe = (code) => MODELOS[code]?.img ?? null;
const gravedadDe = (sev) => GRAVEDAD[sev] ?? GRAVEDAD.advertencia;
const textoNota = (o) => (typeof o === "string" ? o : o?.message ?? "");
const criterioTexto = (k) => CRITERIOS[k] ?? k.replace(/_/g, " ");

const num = (v, dec = 3) => (v === null || v === undefined || !Number.isFinite(Number(v))
    ? "N/D"
    : Number(v).toLocaleString("es-PE", { maximumFractionDigits: dec }));

const cuando = (value) => {
    if (!value) return "";
    const d = new Date(value);
    return Number.isNaN(d.getTime())
        ? ""
        : d.toLocaleString("es-PE", { day: "2-digit", month: "short", hour: "2-digit", minute: "2-digit" });
};

const entradas = (o) => Object.entries(o ?? {}).filter(([, v]) => v !== null && v !== undefined && v !== "");

/* ---------------- Carga ---------------- */
const loadPiscigranjas = async () => {
    try {
        const { data } = await axios.get(route("piscigranjas.options"));
        piscigranjas.value = data.data ?? [];
    } catch { piscigranjas.value = []; }
};

const loadPiscinas = async () => {
    if (filters.value.piscigranja_id === "T") { piscinas.value = []; return; }
    try {
        const { data } = await axios.get(route("piscigranjas.piscinas", filters.value.piscigranja_id));
        piscinas.value = data.data ?? [];
    } catch { piscinas.value = []; }
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

const abrirDetalle = (t) => {
    detalle.value = t;
    if (esLuz(t.raw) && !lightScenarioResult.value) runLightScenario();
};

const openTwin = () => {
    const query = new URLSearchParams({ modelo: detalle.value?.raw?.code ?? "WATER_QUALITY_INDEX_ICA" });
    if (filters.value.piscina_id !== "T") query.set("piscina_id", filters.value.piscina_id);
    window.location.assign(route("monitoreo.gemelodigitals.index") + "?" + query.toString());
};

const openModels = () => window.location.assign(route("monitoreo.modelosmls.index"));


/* ---- Navegacion y filtros del historial de alarmas ---- */
const pestana = ref("panorama");
const configAbierta = ref(false);

const filtroAlarmas = ref({ rango: null, gravedad: "T", modelo: "T" });
const pagina = ref(1);
const porPagina = ref(10);

const modelosConAviso = computed(() => {
    const vistos = new Map();
    for (const a of avisos.value) {
        const code = a.model?.code;
        if (code && !vistos.has(code)) vistos.set(code, nombreDe(code));
    }
    return [...vistos].map(([value, label]) => ({ value, label }));
});

const avisosFiltrados = computed(() => avisosOrdenados.value.filter((a) => {
    if (filtroAlarmas.value.gravedad !== "T" && a.suggested_severity !== filtroAlarmas.value.gravedad) return false;
    if (filtroAlarmas.value.modelo !== "T" && a.model?.code !== filtroAlarmas.value.modelo) return false;
    const rango = filtroAlarmas.value.rango;
    if (Array.isArray(rango) && rango[0] && rango[1]) {
        const t = new Date(a.occurred_at).getTime();
        if (Number.isNaN(t)) return false;
        const desde = new Date(rango[0]).setHours(0, 0, 0, 0);
        const hasta = new Date(rango[1]).setHours(23, 59, 59, 999);
        if (t < desde || t > hasta) return false;
    }
    return true;
}));

const avisosPagina = computed(() => {
    const ini = (pagina.value - 1) * porPagina.value;
    return avisosFiltrados.value.slice(ini, ini + porPagina.value);
});

const limpiarFiltros = () => {
    filtroAlarmas.value = { rango: null, gravedad: "T", modelo: "T" };
    pagina.value = 1;
};

watch(avisosFiltrados, () => { pagina.value = 1; });

const cuandoLargo = (v) => {
    if (!v) return "";
    const d = new Date(v);
    return Number.isNaN(d.getTime()) ? "" : d.toLocaleString("es-PE",
        { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit" });
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
            <!-- CABECERA: contexto y acciones -->
            <header class="cab">
                <div class="cab__filtros">
                    <div class="campo">
                        <label>Piscigranja</label>
                        <el-select v-model="filters.piscigranja_id" filterable @change="changeFarm">
                            <el-option label="Todas" value="T" />
                            <el-option v-for="i in piscigranjas" :key="i.id" :label="i.nombre" :value="String(i.id)" />
                        </el-select>
                    </div>
                    <div class="campo">
                        <label>Piscina</label>
                        <el-select v-model="filters.piscina_id" filterable @change="scheduleReload">
                            <el-option label="Principal" value="T" />
                            <el-option v-for="i in piscinas" :key="i.id" :label="i.nombre" :value="String(i.id)" />
                        </el-select>
                    </div>
                    <div class="campo">
                        <label>Periodo</label>
                        <el-select v-model="filters.ventana_horas" @change="scheduleReload">
                            <el-option v-for="i in windowOptions" :key="i.value" :label="i.label" :value="i.value" />
                        </el-select>
                    </div>
                </div>
                <div class="cab__acciones">
                    <el-button :icon="Setting" @click="configAbierta = true">
                        Configurar alarmas
                        <span class="cab__badge">{{ configuraciones.filter((c) => c.activa).length }}/{{ configuraciones.length }}</span>
                    </el-button>
                    <el-button type="primary" :icon="RefreshRight" :loading="loading" @click="loadDashboard(true)">
                        Actualizar
                    </el-button>
                </div>
            </header>

            <!-- ESTADO -->
            <section class="estado" :class="'estado--' + estadoGeneral.tono">
                <span class="estado__emoji">{{ estadoGeneral.emoji }}</span>
                <div class="estado__txt">
                    <h2>{{ estadoGeneral.titulo }}</h2>
                    <p>{{ estadoGeneral.frase }}</p>
                </div>
                <el-button v-if="avisos.length" text class="estado__ir" @click="pestana = 'alarmas'">
                    Ver las alarmas
                </el-button>
            </section>

            <!-- PESTANAS -->
            <el-tabs v-model="pestana" class="tabs">
                <el-tab-pane name="panorama">
                    <template #label><span class="tabs__l">Panorama</span></template>

                    <section class="al__mod">
                        <h3 class="al__seccion">Los modelos que vigilan esta piscina</h3>
                        <div class="tarjetas">
                            <article
                                v-for="t in tarjetas"
                                :key="t.raw.code"
                                class="tarjeta"
                                :class="'tarjeta--' + t.estado.tono"
                                role="button"
                                tabindex="0"
                                @click="abrirDetalle(t)"
                                @keyup.enter="abrirDetalle(t)"
                            >
                                <el-popover placement="top" :width="290" trigger="click">
                                    <template #reference>
                                        <button class="tarjeta__info" type="button" aria-label="Que hace" @click.stop>
                                            <el-icon><QuestionFilled /></el-icon>
                                        </button>
                                    </template>
                                    <p class="pop__t">{{ t.corto }}</p>
                                    <p class="pop__d">{{ t.ayuda }}</p>
                                </el-popover>

                                <img v-if="t.img" class="tarjeta__img" :src="t.img" :alt="t.corto" width="60" height="60" loading="lazy" />
                                <span v-else class="tarjeta__img tarjeta__img--vacia" aria-hidden="true"></span>
                                <h4 class="tarjeta__nombre">{{ t.corto }}</h4>
                                <p class="tarjeta__real">{{ t.raw.name }}</p>

                                <p v-if="t.dato" class="tarjeta__dato">
                                    <span class="tarjeta__grande">{{ t.dato.grande }}</span>
                                    <span class="tarjeta__chico">{{ t.dato.chico }}</span>
                                </p>
                                <p v-else class="tarjeta__dato tarjeta__dato--vacio">Sin valor</p>

                                <div v-if="miniGrafico(t.raw)" class="tarjeta__mini" @click.stop>
                                    <ChartFisheye :options="miniGrafico(t.raw)" height="52px" />
                                </div>

                                <span class="chip" :class="'chip--' + t.estado.tono">{{ t.estado.texto }}</span>

                                <dl class="tarjeta__meta">
                                    <div>
                                        <dt>Datos</dt>
                                        <dd :class="'fresco--' + (t.raw.data_freshness?.level ?? 'unknown')">
                                            {{ (t.raw.data_freshness?.label ?? "sin fecha").replace("Dato de hace ", "hace ").replace("Dato reciente", "al dia") }}
                                        </dd>
                                    </div>
                                    <div v-if="t.raw.horizon">
                                        <dt>Proyecta</dt>
                                        <dd>{{ t.raw.horizon }}</dd>
                                    </div>
                                </dl>
                                <p v-if="Math.abs(t.raw.data_freshness?.clock_drift_days ?? 0) >= 1" class="tarjeta__reloj">
                                    Reloj del sensor {{ Math.round(Math.abs(t.raw.data_freshness.clock_drift_days)) }} dias atrasado
                                </p>
                            </article>
                        </div>
                    </section>

                    <section v-if="graficoDestacado" class="al__graf">
                        <h3 class="al__seccion">{{ tituloDestacado }}</h3>
                        <div class="graf">
                            <ChartFisheye :options="graficoDestacado" height="320px" />
                            <p class="graf__pie">
                                Las franjas de color son los rangos de calidad. La linea roja es el
                                limite a partir del cual el sistema avisa.
                            </p>
                        </div>
                    </section>

                    <el-collapse v-model="abiertos" class="expl">
                        <el-collapse-item name="que-es">
                            <template #title>
                                <span class="expl__t"><el-icon><InfoFilled /></el-icon> Que es un modelo y en que se diferencia de una alarma</span>
                            </template>
                            <div class="expl__cuerpo">
                                <p><strong>Un modelo</strong> es una cuenta que mira los sensores y saca un numero: la nota del agua, el oxigeno que habra en una hora, cuanto deberian crecer los peces. El modelo solo calcula. Nunca decide molestar a nadie.</p>
                                <p><strong>Una alarma</strong> es la regla que tu le pones encima a ese numero: "si baja de 70, avisame". Sin esa regla el modelo sigue calculando, pero se queda callado.</p>
                                <p>Por eso un modelo puede estar funcionando perfecto y aun asi no avisar nunca: le falta la regla. Se define con el boton <strong>Configurar alarmas</strong>.</p>
                                <p class="expl__nota">Estas alarmas son distintas de las de la pestana <em>Alertas</em>. Aquellas se disparan cuando un sensor cruza un rango fijo. Estas nacen de un modelo que combina varias medidas o proyecta hacia adelante.</p>
                            </div>
                        </el-collapse-item>
                        <el-collapse-item v-if="observations.length" name="notas">
                            <template #title><span class="expl__t">Notas tecnicas del calculo ({{ observations.length }})</span></template>
                            <ul class="lista"><li v-for="(o, i) in observations" :key="i">{{ textoNota(o) }}</li></ul>
                            <dl class="dl" v-if="meta.source">
                                <div><dt>Origen</dt><dd>{{ meta.source }}</dd></div>
                                <div v-if="meta.computed_at"><dt>Calculado</dt><dd>{{ cuandoLargo(meta.computed_at) }}</dd></div>
                                <div v-if="meta.window_hours"><dt>Ventana</dt><dd>{{ meta.window_hours }} h</dd></div>
                            </dl>
                        </el-collapse-item>
                    </el-collapse>
                </el-tab-pane>

                <el-tab-pane name="alarmas">
                    <template #label>
                        <span class="tabs__l">
                            Alarmas
                            <span v-if="avisos.length" class="tabs__n">{{ avisos.length }}</span>
                        </span>
                    </template>

                    <div class="hist">
                        <div class="hist__filtros">
                            <div class="campo campo--ancho">
                                <label>Rango de fechas</label>
                                <el-date-picker
                                    v-model="filtroAlarmas.rango"
                                    type="daterange"
                                    range-separator="a"
                                    start-placeholder="Desde"
                                    end-placeholder="Hasta"
                                    format="DD/MM/YYYY"
                                    value-format="YYYY-MM-DD"
                                />
                            </div>
                            <div class="campo">
                                <label>Gravedad</label>
                                <el-select v-model="filtroAlarmas.gravedad">
                                    <el-option label="Todas" value="T" />
                                    <el-option label="Urgente" value="emergencia" />
                                    <el-option label="Grave" value="critico" />
                                    <el-option label="Atencion" value="advertencia" />
                                </el-select>
                            </div>
                            <div class="campo">
                                <label>Modelo</label>
                                <el-select v-model="filtroAlarmas.modelo">
                                    <el-option label="Todos" value="T" />
                                    <el-option v-for="m in modelosConAviso" :key="m.value" :label="m.label" :value="m.value" />
                                </el-select>
                            </div>
                            <el-button :icon="Delete" text @click="limpiarFiltros">Limpiar</el-button>
                        </div>

                        <div class="hist__conteo">
                            <span v-if="conteoGravedad.emergencia" class="chip chip--malo">{{ conteoGravedad.emergencia }} urgente<span v-if="conteoGravedad.emergencia > 1">s</span></span>
                            <span v-if="conteoGravedad.critico" class="chip chip--malo">{{ conteoGravedad.critico }} grave<span v-if="conteoGravedad.critico > 1">s</span></span>
                            <span v-if="conteoGravedad.advertencia" class="chip chip--aviso">{{ conteoGravedad.advertencia }} de atencion</span>
                            <span class="hist__resultado">
                                {{ avisosFiltrados.length }} de {{ avisos.length }} alarmas
                            </span>
                        </div>

                        <el-table :data="avisosPagina" class="hist__tabla" empty-text="Ninguna alarma con esos filtros" row-key="source_event_id">
                            <el-table-column type="expand">
                                <template #default="{ row }">
                                    <dl class="dl dl--mini hist__det">
                                        <div><dt>Mensaje original</dt><dd>{{ row.message }}</dd></div>
                                        <div v-if="row.alarm_code"><dt>Codigo de alarma</dt><dd class="mono">{{ row.alarm_code }}</dd></div>
                                        <div v-if="row.model?.code"><dt>Modelo</dt><dd class="mono">{{ row.model.code }}<span v-if="row.model.version"> ({{ row.model.version }})</span></dd></div>
                                        <div v-if="row.policy?.code"><dt>Politica</dt><dd class="mono">{{ row.policy.code }}</dd></div>
                                        <div v-if="row.horizon_minutes"><dt>Horizonte</dt><dd>{{ row.horizon_minutes }} min</dd></div>
                                        <div v-if="row.source_event_id"><dt>Id del evento</dt><dd class="mono">{{ row.source_event_id }}</dd></div>
                                    </dl>
                                </template>
                            </el-table-column>
                            <el-table-column label="Gravedad" width="118">
                                <template #default="{ row }">
                                    <span class="chip" :class="'chip--' + gravedadDe(row.suggested_severity).tono">
                                        {{ gravedadDe(row.suggested_severity).texto }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Modelo" width="170">
                                <template #default="{ row }">
                                    <span class="hist__modelo">
                                        <img v-if="imagenDe(row.model?.code)" class="hist__img" :src="imagenDe(row.model?.code)" alt="" width="22" height="22" />{{ nombreDe(row.model?.code) }}
                                    </span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Que paso" min-width="320">
                                <template #default="{ row }">{{ mensajeSimple(row) }}</template>
                            </el-table-column>
                            <el-table-column label="Valor" width="110" align="right">
                                <template #default="{ row }">{{ num(row.predicted_value ?? row.value, 2) }}</template>
                            </el-table-column>
                            <el-table-column label="Cuando" width="190">
                                <template #default="{ row }">{{ cuandoLargo(row.occurred_at) }}</template>
                            </el-table-column>
                        </el-table>

                        <div class="hist__pie">
                            <el-pagination
                                v-model:current-page="pagina"
                                v-model:page-size="porPagina"
                                :page-sizes="[10, 25, 50, 100]"
                                :total="avisosFiltrados.length"
                                layout="total, sizes, prev, pager, next"
                                background
                            />
                        </div>
                    </div>
                </el-tab-pane>
            </el-tabs>

            <!-- CAJON: CONFIGURACION -->
            <el-drawer v-model="configAbierta" size="760px" :with-header="false">
                <div class="cfgd">
                    <header class="cfgd__cab">
                        <div>
                            <h3>Configuracion de alarmas</h3>
                            <p>Cada modelo calcula un numero. La alarma es la regla que decide a partir de que punto ese numero merece que alguien mire la piscina.</p>
                        </div>
                        <span class="chip chip--ok">{{ configuraciones.filter((c) => c.activa).length }} activas de {{ configuraciones.length }}</span>
                    </header>

                    <article v-for="c in configuraciones" :key="c.code" class="ac" :class="{ 'ac--off': !c.activa }">
                        <header class="ac__cab">
                            <img v-if="c.img" class="ac__img" :src="c.img" alt="" width="34" height="34" />
                            <div class="ac__id">
                                <strong>{{ c.corto }}</strong>
                                <span class="ac__nom">{{ c.nombre }}</span>
                                <span class="mono ac__code">{{ c.alarmCode }}</span>
                            </div>
                            <div class="ac__estado">
                                <span class="chip" :class="c.activa ? 'chip--ok' : 'chip--off'">
                                    {{ c.activa ? "Alarma activa" : "Sin alarma" }}
                                </span>
                                <span class="fresco" :class="'fresco--' + (c.frescura.level ?? 'unknown')">
                                    {{ c.frescura.label ?? "Sin fecha" }}
                                </span>
                            </div>
                        </header>

                        <p class="ac__vigila">{{ c.info.vigila ?? c.raw.purpose }}</p>

                        <div class="ac__cuerpo">
                            <div class="ac__col">
                                <span class="ac__k">La regla</span>
                                <p v-if="c.activa" class="ac__regla">
                                    Avisa cuando {{ c.corto.replace(/^El |^La /, "").toLowerCase() }}
                                    {{ OPERADOR_TEXTO[c.policy.operator] ?? "llegue a" }}
                                    <strong>{{ c.policy.threshold }} {{ c.policy.unit }}</strong>,
                                    con gravedad <strong>{{ gravedadDe(c.policy.severity).texto.toLowerCase() }}</strong>.
                                </p>
                                <template v-else>
                                    <p class="ac__regla ac__regla--prop" v-if="c.sugerido">
                                        Propuesta: avisar cuando {{ OPERADOR_TEXTO[c.sugerido.operador] }}
                                        <strong>{{ c.sugerido.umbral }} {{ c.sugerido.unidad }}</strong>.
                                    </p>
                                    <p class="ac__regla ac__regla--prop" v-else>Sin regla definida todavia.</p>
                                    <p class="ac__nota">{{ c.sugerido?.porque ?? c.bloqueo }}</p>
                                </template>
                                <p v-if="c.activa && c.policy.rationale" class="ac__nota">{{ c.policy.rationale }}</p>
                            </div>

                            <div class="ac__col" v-if="(c.info.escala ?? []).length">
                                <span class="ac__k">Como se lee el valor</span>
                                <div class="esc">
                                    <div
                                        v-for="b in c.info.escala"
                                        :key="b.etiqueta"
                                        class="esc__b"
                                        :class="['esc__b--' + b.tono, { 'esc__b--aqui': c.banda && c.banda.etiqueta === b.etiqueta }]"
                                    >
                                        <span class="esc__r">{{ b.min }}&ndash;{{ b.max }}</span>
                                        <span class="esc__e">{{ b.etiqueta }}</span>
                                    </div>
                                </div>
                                <p v-if="c.banda" class="ac__nota">
                                    Ahora: <strong>{{ num(c.valor, 2) }} {{ c.info.unidad }}</strong>
                                    &rarr; {{ c.banda.etiqueta.toLowerCase() }}.
                                </p>
                            </div>

                            <div class="ac__col" v-if="(c.info.siSuena ?? []).length">
                                <span class="ac__k">Si suena, que hacer</span>
                                <ol class="ac__pasos"><li v-for="a in c.info.siSuena" :key="a">{{ a }}</li></ol>
                            </div>
                        </div>

                        <el-collapse class="ac__mas">
                            <el-collapse-item :title="c.activa ? 'Cambiar este limite' : 'Activar esta alarma'" :name="c.code">
                                <p class="ac__nota">
                                    Se hace por consola a proposito: cada limite queda con su justificacion
                                    escrita, su version y quien lo aprobo.
                                </p>
                                <pre class="ac__cmd">{{ comandoDe(c) }}</pre>
                                <p v-if="!c.elegible" class="ac__bloqueo">
                                    Aunque le pongas la regla, este modelo no emitira: {{ c.bloqueo }}
                                </p>
                            </el-collapse-item>
                        </el-collapse>
                    </article>
                </div>
            </el-drawer>

            <!-- CAJON: DETALLE DEL MODELO -->
            <el-drawer :model-value="Boolean(detalle)" :with-header="false" size="620px" @close="detalle = null">
                <div v-if="detalle" class="det">
                    <header class="det__top">
                        <img v-if="detalle.img" class="det__img" :src="detalle.img" alt="" width="46" height="46" />
                        <div>
                            <h3>{{ detalle.corto }}</h3>
                            <p class="det__real">{{ detalle.raw.name }}</p>
                            <span class="chip" :class="'chip--' + detalle.estado.tono">{{ detalle.estado.texto }}</span>
                        </div>
                    </header>

                    <p class="det__purpose">{{ detalle.raw.purpose }}</p>

                    <div class="det__caja det__caja--estado">
                        <strong>Por que esta asi</strong>
                        <p>{{ detalle.estado.explica }}</p>
                        <p class="det__nota" v-if="detalle.raw.usage?.detail">{{ detalle.raw.usage.detail }}</p>
                    </div>

                    <div class="det__cifras">
                        <div v-if="detalle.dato">
                            <span class="det__k">Ahora</span>
                            <span class="det__v">{{ detalle.dato.grande }}</span>
                            <span class="det__u">{{ detalle.dato.chico }}</span>
                        </div>
                        <div v-if="detalle.raw.prediction_value !== null && detalle.raw.prediction_value !== undefined">
                            <span class="det__k">Proyectado</span>
                            <span class="det__v">{{ num(detalle.raw.prediction_value, 2) }}</span>
                            <span class="det__u">{{ detalle.raw.unit }}</span>
                        </div>
                        <div v-if="detalle.raw.horizon">
                            <span class="det__k">Alcance</span>
                            <span class="det__v det__v--txt">{{ detalle.raw.horizon }}</span>
                        </div>
                        <div v-if="detalle.raw.data_freshness?.label">
                            <span class="det__k">Ultimo dato</span>
                            <span class="det__v det__v--txt">{{ detalle.raw.data_freshness.label }}</span>
                            <span class="det__u">desde {{ detalle.raw.data_freshness.source_table }}</span>
                        </div>
                        <div v-if="Math.abs(detalle.raw.data_freshness?.clock_drift_days ?? 0) >= 1">
                            <span class="det__k">Reloj del sensor</span>
                            <span class="det__v det__v--txt det__v--alerta">
                                {{ Math.round(Math.abs(detalle.raw.data_freshness.clock_drift_days)) }} dias atrasado
                            </span>
                            <span class="det__u">sus marcas de tiempo no son la hora real</span>
                        </div>
                    </div>

                    <div class="det__caja det__caja--regla">
                        <strong>Su alarma</strong>
                        <p v-if="reglaEnPalabras(detalle.raw)">{{ reglaEnPalabras(detalle.raw) }}</p>
                        <p v-else>{{ detalle.raw.policy?.condition }}</p>
                        <dl class="dl dl--mini">
                            <div><dt>Codigo</dt><dd class="mono">{{ detalle.raw.alarm_code }}</dd></div>
                            <div><dt>Estado</dt><dd>{{ detalle.raw.policy?.status }}</dd></div>
                            <div v-if="detalle.raw.policy?.severity"><dt>Gravedad</dt><dd>{{ detalle.raw.policy.severity }}</dd></div>
                            <div v-if="detalle.raw.policy?.version"><dt>Version</dt><dd>{{ detalle.raw.policy.version }}</dd></div>
                        </dl>
                    </div>

                    <div v-if="(detalle.raw.inputs ?? []).length" class="det__caja">
                        <strong>Que mide</strong>
                        <div class="tags">
                            <span
                                v-for="i in detalle.raw.inputs"
                                :key="i"
                                class="tag"
                                :class="{ 'tag--falta': (detalle.raw.missing_inputs ?? []).includes(i) }"
                            >{{ i }}</span>
                        </div>
                        <p v-if="(detalle.raw.missing_inputs ?? []).length" class="det__nota">
                            Lo marcado en ambar no esta llegando todavia.
                        </p>
                    </div>

                    <el-collapse class="det__mas">
                        <el-collapse-item v-if="detalle.raw.usage?.activation_criteria" title="Que le falta para que se le confie" name="c">
                            <ul class="checks">
                                <li v-for="(v, k) in detalle.raw.usage.activation_criteria" :key="k" :class="{ ok: v }">
                                    <span>{{ v ? "\u2713" : "\u2717" }}</span> {{ criterioTexto(k) }}
                                </li>
                            </ul>
                        </el-collapse-item>

                        <el-collapse-item title="Como viene evolucionando" name="g">
                            <ChartFisheye v-if="graficoDe(detalle.raw)" :options="graficoDe(detalle.raw)" height="300px" />
                            <p v-else class="vacio">Todavia no hay mediciones suficientes para dibujar la curva.</p>
                        </el-collapse-item>

                        <el-collapse-item v-if="detalle.raw.relationship" title="Que variable pesa mas" name="r">
                            <p class="det__nota">{{ detalle.raw.relationship.description }}</p>
                            <ChartFisheye v-if="graficoRelacion(detalle.raw)" :options="graficoRelacion(detalle.raw)" height="260px" />
                            <p v-else class="vacio">Sin datos para comparar el peso de cada variable.</p>
                        </el-collapse-item>

                        <el-collapse-item v-if="esLuz(detalle.raw)" title="Probar un escenario de luz" name="l">
                            <div class="luz">
                                <div><label>Luz maxima (lux)</label><el-input-number v-model="lightScenario.maximum_lux" :min="0" :max="200000" :step="50" controls-position="right" /></div>
                                <div><label>Horas de luz</label><el-input-number v-model="lightScenario.photoperiod_hours" :min="0" :max="24" :step="0.5" controls-position="right" /></div>
                                <div><label>Hora de amanecer</label><el-input-number v-model="lightScenario.dawn_hour" :min="0" :max="23.5" :step="0.5" controls-position="right" /></div>
                                <el-button type="primary" :loading="scenarioLoading" @click="runLightScenario">Calcular</el-button>
                                <p class="det__nota">Este escenario es solo para ver como se comportaria. Nunca genera una alarma real.</p>
                            </div>
                        </el-collapse-item>

                        <el-collapse-item v-if="detalle.raw.formula" title="La formula" name="f">
                            <p class="mono det__expr">{{ detalle.raw.formula.expression ?? detalle.raw.formula.latex }}</p>
                            <p class="det__nota">{{ detalle.raw.formula.detail }}</p>
                            <p v-if="detalle.raw.formula.kernel" class="mono det__expr">{{ detalle.raw.formula.kernel }}</p>
                            <ul class="lista" v-if="detalle.raw.formula.conditions">
                                <li v-for="c in detalle.raw.formula.conditions" :key="c">{{ c }}</li>
                            </ul>
                        </el-collapse-item>

                        <el-collapse-item v-if="entradas(detalle.raw.metrics).length" title="Que tan bien acierta" name="m">
                            <div v-if="comparativa(detalle.raw)" class="vs" :class="{ 'vs--pierde': !comparativa(detalle.raw).ganaModelo }">
                                <div class="vs__lado">
                                    <span class="vs__k">Este modelo</span>
                                    <span class="vs__v">{{ num(comparativa(detalle.raw).modelo, 2) }}</span>
                                    <span class="vs__u">de error</span>
                                </div>
                                <span class="vs__sep">vs</span>
                                <div class="vs__lado">
                                    <span class="vs__k">Metodo simple</span>
                                    <span class="vs__v">{{ num(comparativa(detalle.raw).simple, 2) }}</span>
                                    <span class="vs__u">de error</span>
                                </div>
                            </div>
                            <p v-if="comparativa(detalle.raw)" class="vs__frase">{{ comparativa(detalle.raw).frase }}</p>
                            <p class="det__nota">
                                El "metodo simple" es no usar inteligencia artificial: suponer que dentro de una hora
                                el valor sera el mismo de ahora. Un modelo solo vale la pena si le gana a eso.
                            </p>
                            <el-collapse class="det__anidado">
                                <el-collapse-item title="Ver todos los numeros" name="nums">
                                    <dl class="dl dl--mini">
                                        <div v-for="[k, v] in entradas(detalle.raw.metrics)" :key="k">
                                            <dt>{{ metricaTexto(k) }}</dt>
                                            <dd>{{ typeof v === "number" ? num(v, 4) : v }} <span class="mono vs__key">{{ k }}</span></dd>
                                        </div>
                                    </dl>
                                </el-collapse-item>
                            </el-collapse>
                        </el-collapse-item>

                        <el-collapse-item v-if="detalle.raw.policy?.rationale" title="Por que ese limite" name="p">
                            <p class="det__nota">{{ detalle.raw.policy.rationale }}</p>
                        </el-collapse-item>

                        <el-collapse-item v-if="entradas(detalle.raw.traceability).length" title="De donde salen los datos" name="t">
                            <dl class="dl dl--mini">
                                <div v-for="[k, v] in entradas(detalle.raw.traceability)" :key="k">
                                    <dt>{{ k.replace(/_/g, " ") }}</dt>
                                    <dd class="mono">{{ Array.isArray(v) ? v.join(", ") : String(v) }}</dd>
                                </div>
                            </dl>
                        </el-collapse-item>

                        <el-collapse-item title="Ficha del modelo" name="fi">
                            <dl class="dl dl--mini">
                                <div><dt>Codigo</dt><dd class="mono">{{ detalle.raw.code }}</dd></div>
                                <div v-if="detalle.raw.version"><dt>Version</dt><dd>{{ detalle.raw.version }}</dd></div>
                                <div v-if="detalle.raw.asset_id"><dt>Artefacto</dt><dd class="mono">{{ detalle.raw.asset_id }}</dd></div>
                                <div><dt>Madurez</dt><dd>{{ detalle.raw.maturity }}</dd></div>
                                <div><dt>Estado interno</dt><dd>{{ detalle.raw.model_status }}</dd></div>
                            </dl>
                        </el-collapse-item>
                    </el-collapse>

                    <div class="det__acciones">
                        <el-button :icon="SetUp" @click="openTwin">Gemelo digital</el-button>
                        <el-button @click="openModels">Todos los modelos</el-button>
                    </div>
                </div>
            </el-drawer>
        </div>
    </App>
</template>

<style scoped>
.al { max-width: 1440px; margin: 0 auto; padding: 4px 0 48px; }
.al__seccion { font-size: 15px; font-weight: 700; color: #6b7280; margin: 4px 0 12px; }
.mono { font-family: ui-monospace, Menlo, monospace; font-size: 12px; }

/* ---------- Cabecera ---------- */
.cab { display: flex; align-items: flex-end; justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 18px; }
.cab__filtros { display: flex; gap: 14px; flex-wrap: wrap; flex: 1 1 520px; }
.cab__acciones { display: flex; gap: 10px; align-items: center; }
.cab__badge { margin-left: 8px; font-size: 11px; font-weight: 700; background: #eef2ff; color: #4338ca; padding: 1px 7px; border-radius: 999px; }
.campo { flex: 1 1 180px; min-width: 150px; }
.campo--ancho { flex: 2 1 300px; }
.campo label { display: block; font-size: 12px; font-weight: 600; color: #6b7280; margin-bottom: 5px; }
.campo :deep(.el-select), .campo :deep(.el-date-editor) { width: 100%; }

/* ---------- Estado ---------- */
.estado { display: flex; align-items: center; gap: 18px; padding: 22px 26px; border-radius: 18px; border: 2px solid; margin-bottom: 6px; }
.estado__emoji { font-size: 46px; line-height: 1; }
.estado__txt { flex: 1; }
.estado__txt h2 { font-size: 25px; font-weight: 800; margin: 0 0 3px; }
.estado__txt p { font-size: 15px; margin: 0; opacity: .85; }
.estado__ir { font-weight: 600; }
.estado--ok { background: #f0fdf4; border-color: #86efac; color: #166534; }
.estado--malo { background: #fef2f2; border-color: #fca5a5; color: #991b1b; }
.estado--off { background: #f9fafb; border-color: #e5e7eb; color: #4b5563; }
.estado--calc { background: #eff6ff; border-color: #93c5fd; color: #1e40af; }

/* ---------- Pestanas ---------- */
.tabs :deep(.el-tabs__header) { margin: 0 0 18px; }
.tabs :deep(.el-tabs__item) { font-size: 15px; font-weight: 600; height: 46px; }
.tabs__l { display: inline-flex; align-items: center; gap: 8px; }
.tabs__n { background: #fee2e2; color: #991b1b; font-size: 11px; font-weight: 800; padding: 1px 8px; border-radius: 999px; }

/* ---------- Tarjetas de modelo ---------- */
.tarjetas { display: grid; grid-template-columns: repeat(auto-fit, minmax(190px, 1fr)); gap: 14px; }
.tarjeta { position: relative; background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 20px 16px 14px; text-align: center; cursor: pointer; transition: transform .15s, box-shadow .15s; display: flex; flex-direction: column; }
.tarjeta:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(0,0,0,.07); }
.tarjeta--ok { border-color: #bbf7d0; }
.tarjeta--aviso { border-color: #fde68a; }
.tarjeta--eval { border-color: #ddd6fe; }
.tarjeta--calc { border-color: #bfdbfe; }
.tarjeta__info { position: absolute; top: 10px; right: 10px; border: 0; background: transparent; color: #d1d5db; cursor: pointer; padding: 4px; }
.tarjeta__info:hover { color: #6b7280; }
.tarjeta__img { display: block; margin: 0 auto; width: 60px; height: 60px; }
.tarjeta__img--vacia { background: #f3f4f6; border-radius: 50%; }
.tarjeta__nombre { font-size: 16px; font-weight: 700; color: #1f2937; margin: 8px 0 2px; }
.tarjeta__real { font-size: 11px; color: #9ca3af; margin: 0 0 10px; line-height: 1.3; min-height: 28px; }
.tarjeta__dato { margin: 0 0 8px; display: flex; flex-direction: column; gap: 2px; }
.tarjeta__grande { font-size: 23px; font-weight: 800; color: #111827; }
.tarjeta__chico { font-size: 11px; color: #9ca3af; }
.tarjeta__dato--vacio { font-size: 13px; color: #d1d5db; }
.tarjeta__mini { margin: 0 -4px 10px; pointer-events: none; }
.tarjeta__meta { display: flex; justify-content: center; gap: 14px; margin: 10px 0 0; flex-wrap: wrap; }
.tarjeta__meta > div { display: flex; flex-direction: column; gap: 1px; min-width: 0; max-width: 50%; }
.tarjeta__meta dt { font-size: 9px; text-transform: uppercase; letter-spacing: .05em; color: #cbd5e1; }
.tarjeta__meta dd { margin: 0; font-size: 11px; color: #6b7280; line-height: 1.35; overflow-wrap: anywhere; }
.tarjeta__meta dd.fresco--stale, .tarjeta__meta dd.fresco--very_stale { color: #b45309; font-weight: 600; }
.tarjeta__meta dd.fresco--fresh, .tarjeta__meta dd.fresco--recent { color: #15803d; }
.tarjeta__reloj { margin: 8px 0 0; font-size: 10px; color: #b45309; background: #fffbeb; border-radius: 8px; padding: 4px 6px; line-height: 1.35; }
.det__v--alerta { color: #b45309; }

.chip { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 12px; font-weight: 700; white-space: nowrap; }
.chip--ok { background: #dcfce7; color: #166534; }
.chip--aviso { background: #fef3c7; color: #92400e; }
.chip--malo { background: #fee2e2; color: #991b1b; }
.chip--eval { background: #ede9fe; color: #5b21b6; }
.chip--off { background: #f3f4f6; color: #6b7280; }
.chip--calc { background: #dbeafe; color: #1e40af; }

/* ---------- Grafico principal ---------- */
.al__graf { margin-top: 26px; }
.graf { background: #fff; border: 1px solid #e5e7eb; border-radius: 18px; padding: 16px 12px 10px; }
.graf__pie { font-size: 12px; color: #9ca3af; margin: 6px 0 0; padding: 0 8px; }

/* ---------- Explicadores ---------- */
.expl { margin-top: 22px; border: 1px solid #e5e7eb; border-radius: 14px; padding: 0 16px; }
.expl__t { font-weight: 700; color: #4b5563; display: inline-flex; align-items: center; gap: 6px; }
.expl__cuerpo p { font-size: 14px; line-height: 1.65; color: #374151; margin: 0 0 10px; }
.expl__nota { border-left: 3px solid #d1d5db; padding-left: 12px; color: #6b7280 !important; }
.lista { margin: 0; padding-left: 18px; font-size: 13px; color: #4b5563; line-height: 1.7; }

/* ---------- Historial de alarmas ---------- */
.hist__filtros { display: flex; gap: 14px; align-items: flex-end; flex-wrap: wrap; margin-bottom: 14px; }
.hist__conteo { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
.hist__resultado { margin-left: auto; font-size: 13px; color: #9ca3af; }
.hist__tabla { border: 1px solid #e5e7eb; border-radius: 14px; overflow: hidden; }
.hist__tabla :deep(th) { background: #f9fafb !important; font-size: 12px; color: #6b7280; font-weight: 700; }
.hist__tabla :deep(td) { font-size: 13px; }
.hist__modelo { font-weight: 600; color: #374151; display: inline-flex; align-items: center; gap: 7px; }
.hist__img { width: 22px; height: 22px; flex: none; }
.hist__det { padding: 6px 40px 10px; }
.hist__pie { display: flex; justify-content: flex-end; margin-top: 14px; }

/* ---------- Cajon de configuracion ---------- */
.cfgd { padding: 6px 4px 24px; }
.cfgd__cab { display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 18px; }
.cfgd__cab h3 { font-size: 20px; font-weight: 800; color: #1f2937; margin: 0 0 4px; }
.cfgd__cab p { font-size: 13px; color: #6b7280; line-height: 1.6; margin: 0; max-width: 46ch; }
.ac { background: #fff; border: 1px solid #e5e7eb; border-radius: 16px; padding: 16px 18px; margin-bottom: 12px; }
.ac--off { background: #fcfcfd; }
.ac__cab { display: flex; align-items: flex-start; gap: 12px; margin-bottom: 10px; }
.ac__img { display: block; width: 34px; height: 34px; flex: none; }
.ac__id { flex: 1; display: flex; flex-direction: column; gap: 1px; }
.ac__id strong { font-size: 15px; color: #1f2937; }
.ac__nom { font-size: 12px; color: #9ca3af; }
.ac__code { color: #cbd5e1; }
.ac__estado { display: flex; flex-direction: column; align-items: flex-end; gap: 5px; }
.fresco { font-size: 11px; padding: 2px 8px; border-radius: 999px; white-space: nowrap; }
.fresco--fresh { background: #dcfce7; color: #166534; }
.fresco--recent { background: #ecfeff; color: #155e75; }
.fresco--stale { background: #fef3c7; color: #92400e; }
.fresco--very_stale { background: #fee2e2; color: #991b1b; }
.fresco--unknown { background: #f3f4f6; color: #6b7280; }
.ac__vigila { font-size: 14px; color: #374151; line-height: 1.6; margin: 0 0 14px; }
.ac__cuerpo { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 18px; }
.ac__k { display: block; font-size: 11px; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; margin-bottom: 6px; }
.ac__regla { font-size: 14px; color: #1f2937; margin: 0 0 6px; line-height: 1.55; }
.ac__regla--prop { color: #6b7280; font-style: italic; }
.ac__nota { font-size: 12px; color: #6b7280; line-height: 1.6; margin: 0; }
.ac__pasos { margin: 0; padding-left: 18px; font-size: 13px; color: #4b5563; line-height: 1.65; }
.esc { display: flex; flex-direction: column; gap: 3px; }
.esc__b { display: flex; align-items: center; gap: 8px; padding: 3px 8px; border-radius: 8px; font-size: 12px; }
.esc__b--ok { background: #f0fdf4; color: #166534; }
.esc__b--aviso { background: #fffbeb; color: #92400e; }
.esc__b--malo { background: #fef2f2; color: #991b1b; }
.esc__b--aqui { outline: 2px solid #3b82f6; font-weight: 700; }
.esc__r { font-variant-numeric: tabular-nums; opacity: .7; min-width: 62px; }
.ac__mas { margin-top: 12px; }
.ac__cmd { font-family: ui-monospace, Menlo, monospace; font-size: 11px; background: #0f172a; color: #e2e8f0; padding: 12px; border-radius: 10px; overflow-x: auto; white-space: pre; margin: 8px 0; }
.ac__bloqueo { font-size: 12px; color: #92400e; background: #fffbeb; padding: 8px 10px; border-radius: 8px; margin: 0; }

/* ---------- Cajon de detalle ---------- */
.det { padding: 8px 4px 24px; }
.det__top { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 16px; }
.det__img { display: block; width: 46px; height: 46px; flex: none; }
.det__top h3 { font-size: 21px; font-weight: 800; margin: 0; color: #1f2937; }
.det__real { font-size: 12px; color: #9ca3af; margin: 2px 0 8px; }
.det__purpose { font-size: 15px; color: #374151; line-height: 1.6; margin: 0 0 18px; }
.det__caja { padding: 14px 16px; border-radius: 14px; margin-bottom: 12px; background: #f9fafb; }
.det__caja--estado { background: #f5f3ff; }
.det__caja--regla { background: #eff6ff; }
.det__caja strong { display: block; font-size: 13px; margin-bottom: 6px; color: #374151; }
.det__caja p { margin: 0 0 6px; font-size: 14px; line-height: 1.6; color: #4b5563; }
.det__cifras { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px; margin-bottom: 14px; }
.det__cifras > div { background: #f9fafb; border-radius: 12px; padding: 12px; display: flex; flex-direction: column; gap: 2px; }
.det__k { font-size: 11px; color: #9ca3af; text-transform: uppercase; letter-spacing: .04em; }
.det__v { font-size: 22px; font-weight: 800; color: #111827; }
.det__v--txt { font-size: 14px; font-weight: 600; }
.det__u { font-size: 11px; color: #9ca3af; }
.det__nota { font-size: 13px; color: #6b7280; line-height: 1.6; margin: 6px 0 0; }
.det__expr { background: #f3f4f6; padding: 10px; border-radius: 8px; overflow-x: auto; }
.det__mas { margin-top: 16px; }
.det__anidado { margin-top: 10px; }
.det__acciones { display: flex; gap: 10px; margin-top: 20px; }
.det__acciones :deep(.el-button) { flex: 1; }

.tags { display: flex; flex-wrap: wrap; gap: 6px; }
.tag { font-size: 12px; padding: 3px 10px; border-radius: 999px; background: #e5e7eb; color: #4b5563; }
.tag--falta { background: #fef3c7; color: #92400e; }

.dl { margin: 0; }
.dl > div { display: grid; grid-template-columns: 140px 1fr; gap: 10px; padding: 5px 0; border-bottom: 1px solid #f3f4f6; }
.dl > div:last-child { border-bottom: 0; }
.dl dt { font-size: 12px; color: #9ca3af; }
.dl dd { margin: 0; font-size: 13px; color: #374151; word-break: break-word; }
.dl--mini > div { grid-template-columns: 110px 1fr; }

.checks { list-style: none; margin: 0; padding: 0; font-size: 13px; }
.checks li { display: flex; gap: 8px; padding: 5px 0; color: #b45309; }
.checks li.ok { color: #15803d; }
.checks li span { font-weight: 800; }

.vacio { font-size: 13px; color: #9ca3af; background: #f9fafb; border-radius: 10px; padding: 18px; text-align: center; margin: 0; }
.vs { display: flex; align-items: center; gap: 14px; background: #f0fdf4; border-radius: 14px; padding: 16px; margin-bottom: 10px; }
.vs--pierde { background: #fffbeb; }
.vs__lado { flex: 1; display: flex; flex-direction: column; gap: 2px; text-align: center; }
.vs__k { font-size: 11px; color: #6b7280; text-transform: uppercase; letter-spacing: .04em; }
.vs__v { font-size: 24px; font-weight: 800; color: #111827; }
.vs__u { font-size: 11px; color: #9ca3af; }
.vs__sep { font-size: 12px; font-weight: 700; color: #9ca3af; }
.vs__frase { font-size: 14px; color: #374151; line-height: 1.6; margin: 0 0 8px; }
.vs__key { color: #cbd5e1; margin-left: 6px; }

.pop__t { font-weight: 700; margin: 0 0 4px; }
.pop__d { margin: 0 0 6px; font-size: 13px; color: #4b5563; line-height: 1.55; }
.luz { display: grid; gap: 12px; }
.luz label { display: block; font-size: 13px; color: #6b7280; margin-bottom: 4px; }
</style>
