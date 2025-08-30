<template>
    <div ref="chartRef" class="w-full h-500px"></div>
</template>

<script setup lang="ts">
import { onMounted, onBeforeUnmount, ref, watch } from "vue";
import * as echarts from "echarts";

type EChartsOption = echarts.EChartsOption;

const props = defineProps<{
    labels: string[];
    tooltips: string[];
    series: any[];
}>();

const chartRef = ref<HTMLDivElement | null>(null);
let myChart: echarts.ECharts | null = null;

function getOption(): EChartsOption {
    return {
        // title: {
        //   text: 'Parámetros de Agua',
        //   subtext: 'Variación por fechas',
        //   left: 'center',
        // },
        tooltip: {
            trigger: "axis",
            formatter: function (params) {
                let index = params[0].dataIndex;
                let dateTitle = props.tooltips[index]; // viene de Laravel
                let html = `<strong>${dateTitle}</strong><br/>`;
                params.forEach((p) => {
                    const val =
                        p.data !== null && p.data !== undefined
                            ? Number(p.data).toFixed(2)
                            : "-";
                    html += `${p.marker} ${p.seriesName}: ${val}<br/>`;
                });
                return html;
            },
        },
        legend: { top: 40 },
        grid: { top: 100, bottom: 60, left: 60, right: 60 },
        xAxis: { type: "category", data: props.labels },
        yAxis: { type: "value" },
        series: props.series,
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
    () => [props.labels, props.series],
    () => {
        if (myChart) {
            myChart.setOption(getOption(), true);
        }
    },
    { deep: true }
);
</script>
