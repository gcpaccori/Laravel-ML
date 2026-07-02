<template>
    <div ref="chartRef" class="w-full h-500px"></div>
</template>

<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from "vue";
import * as echarts from "echarts";

type EChartsOption = echarts.EChartsOption;

const props = defineProps<{
    options: any
}>();

const chartRef = ref<HTMLDivElement | null>(null);
let myChart: echarts.ECharts | null = null;

function getOption(): EChartsOption {
    return {
        ...props.options,
        tooltip: {
            ...props.options?.tooltip,
            formatter(params: any) {
                const tooltip = props.options.tooltip.data[params[0].dataIndex];
                let html = `<strong>${tooltip.title}</strong><br/>`;
                tooltip.items.forEach((item: any, index: number) => {
                    html += `${params[index]?.marker ?? ""} ${item.label}: ${item.value}<br/>`;
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

onMounted(() => {
    initChart();
    window.addEventListener("resize", () => myChart?.resize());
});

onBeforeUnmount(() => {
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
