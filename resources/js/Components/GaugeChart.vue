<script setup>
import { onMounted, ref, watch, watchEffect } from "vue";
import * as echarts from "echarts";

const props = defineProps({
  value: { type: Number, default: 0 },
  min: { type: Number, default: 0 },
  max: { type: Number, required: true },
  bandsData: { type: Array, default: () => [] },
  unit: { type: String, default: "" },
});

const chartRef = ref(null);
let myChart = null;

const getBandTitle = (val) => {
  for (let band of props.bandsData) {
    if (val >= band.lowScore && val < band.highScore) return band.title;
  }
  return "";
};

const renderChart = () => {
  if (!chartRef.value) return;
  if (!myChart) {
    myChart = echarts.init(chartRef.value, null, { renderer: "svg" });
  }

  const colorRanges = props.bandsData.map((band) => [
    band.highScore / props.max,
    band.color,
  ]);

  const option = {
    series: [
      {
        type: "gauge",
        progress: { show: false, width: 18 },
        axisLine: { lineStyle: { width: 15, color: colorRanges } },
        axisTick: { show: true },
        axisLabel: { distance: 20, color: "#999", fontSize: 11 },
        anchor: { show: true, showAbove: true, size: 15, itemStyle: { borderWidth: 3 } },
        title: { show: false },
        detail: {
          valueAnimation: true,
          fontSize: 14,
          offsetCenter: [0, "90%"],
          formatter: function (v) {
            let band = getBandTitle(v);
            return v.toFixed(2) + " " + props.unit + "\n" + band;
          },
        },
        min: props.min,
        max: props.max,
        data: [{ value: props.value }],
      },
    ],
  };

  myChart.setOption(option, true); // true -> fuerza update completo
};

onMounted(() => {
  renderChart();
  window.addEventListener("resize", () => myChart?.resize());
});

// 1. actualizar solo el valor (más rápido)
watch(() => props.value, (newVal) => {
  if (myChart) {
    myChart.setOption({
      series: [{ data: [{ value: newVal }] }],
    });
  }
});

// 2. re-renderizar si cambian los demás props
watch([() => props.min, () => props.max, () => props.bandsData, () => props.unit], () => {
  renderChart();
}, { deep: true });
</script>

<template>
  <div ref="chartRef" style="width: 100%; height: 250px;"></div>
</template>
