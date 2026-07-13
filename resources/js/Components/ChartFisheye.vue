<template>
    <div ref="chartRef" class="w-full" :style="{ height }"></div>
</template>

<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from "vue";
import * as echarts from "echarts";

type EChartsOption = echarts.EChartsOption;

const props = withDefaults(defineProps<{
    options: any,
    height?: string,
}>(), {
    height: "500px",
});

const chartRef = ref<HTMLDivElement | null>(null);
let myChart: echarts.ECharts | null = null;

function getOption(): EChartsOption {
    const tooltip = props.options?.tooltip ?? {};
    const tooltipRows = Array.isArray(tooltip.data) ? tooltip.data : null;
    if (!tooltipRows) {
        return {
            ...props.options,
            tooltip,
        };
    }

    return {
        ...props.options,
        tooltip: {
            ...tooltip,
            formatter(params: any) {
                const active = Array.isArray(params) ? params : [params];
                const row = tooltipRows[active[0]?.dataIndex];
                if (!row) return "";
                let html = `<strong>${row.title}</strong><br/>`;
                row.items.forEach((item: any, index: number) => {
                    html += `${active[index]?.marker ?? ""} ${item.label}: ${item.value}<br/>`;
                });
                return html;
            }
        }
    };
}

function initChart() {
    if (!chartRef.value) return;
    myChart = echarts.init(chartRef.value);
    myChart.setOption(getOption());
}

function resizeChart() {
    myChart?.resize();
}

onMounted(() => {
    initChart();
    window.addEventListener("resize", resizeChart);
});

onBeforeUnmount(() => {
    window.removeEventListener("resize", resizeChart);
    myChart?.dispose();
    myChart = null;
});

watch(
    () => [props.options],
    () => {
        if (myChart) {
            myChart.setOption(getOption(), true);
        }
    },
    { deep: true }
);
</script>
