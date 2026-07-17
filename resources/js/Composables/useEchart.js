// resources/js/Composables/useEchart.js
//
// Composable reutilizable para montar/actualizar/destruir instancias de
// ECharts (API imperativa, `import * as echarts from 'echarts'`) sin
// depender de `vue-echarts`. Extraído del patrón usado en BiometriaShow.vue
// para no repetirlo en cada componente con gráficos.
//
// Uso:
//   const opcion = computed(() => ({ ...configuracion de echarts... }))
//   const { elRef } = useEchart(opcion)
//   <div ref="elRef" class="chart"></div>
//
// Si `opcion.value` es null/undefined, el gráfico se destruye (dispose)
// y no se vuelve a crear hasta que `opcion` tenga un valor válido —
// útil para el caso "sin datos suficientes".

import { onBeforeUnmount, onMounted, ref, watch, nextTick } from "vue";
import * as echarts from "echarts";

export function useEchart(opcionRef, { resizeParent = true } = {}) {
    const elRef = ref(null);
    let instancia = null;
    let resizeObserver = null;

    function pintar() {
        const opcion = opcionRef.value;

        if (!opcion) {
            instancia?.dispose();
            instancia = null;
            return;
        }

        if (!elRef.value) return;

        if (!instancia) {
            instancia = echarts.init(elRef.value);
        }

        instancia.setOption(opcion, true); // true = notMerge, reemplaza config completa
    }

    function resize() {
        instancia?.resize();
    }

    onMounted(() => {
        if (resizeParent && elRef.value) {
            resizeObserver = new ResizeObserver(() => resize());
            resizeObserver.observe(elRef.value.parentElement ?? elRef.value);
        }

        window.addEventListener("resize", resize);
    });

    watch(
        [elRef, opcionRef],
        async () => {
            await nextTick();
            pintar();
        },
        {
            immediate: true,
            deep: true,
        },
    );

    onBeforeUnmount(() => {
        window.removeEventListener("resize", resize);
        resizeObserver?.disconnect();
        instancia?.dispose();
        instancia = null;
    });

    return { elRef, resize };
}
