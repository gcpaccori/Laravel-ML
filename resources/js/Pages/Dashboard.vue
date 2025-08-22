<script setup>
    import * as am5 from "@amcharts/amcharts5";
    import * as am5map from "@amcharts/amcharts5/map";
    import * as am5xy from "@amcharts/amcharts5/xy";
    import * as am5index from "@amcharts/amcharts5/index";
    import am5themes_Animated from "@amcharts/amcharts5/themes/Animated";
    import { onMounted, onBeforeUnmount, ref } from "vue";
    import usaLow from "@amcharts/amcharts5-geodata/usaLow";


    const props = defineProps({
        title: String,
        toolbar: {
            type: Array,
            required: false
        }
    });

    // Data
    let datos = [
    {
        age: "85+",
        male: -0.1,
        female: 0.3
    },
    {
        age: "80-54",
        male: -0.2,
        female: 0.3
    },
    {
        age: "75-79",
        male: -0.3,
        female: 0.6
    },
    {
        age: "70-74",
        male: -0.5,
        female: 0.8
    },
    {
        age: "65-69",
        male: -0.8,
        female: 1.0
    },
    {
        age: "60-64",
        male: -1.1,
        female: 1.3
    },
    {
        age: "55-59",
        male: -1.7,
        female: 1.9
    },
    {
        age: "50-54",
        male: -2.2,
        female: 2.5
    },
    {
        age: "45-49",
        male: -2.8,
        female: 3.0
    },
    {
        age: "40-44",
        male: -3.4,
        female: 3.6
    },
    {
        age: "35-39",
        male: -4.2,
        female: 4.1
    },
    {
        age: "30-34",
        male: -5.2,
        female: 4.8
    },
    {
        age: "25-29",
        male: -5.6,
        female: 5.1
    },
    {
        age: "20-24",
        male: -5.1,
        female: 5.1
    },
    {
        age: "15-19",
        male: -3.8,
        female: 3.8
    },
    {
        age: "10-14",
        male: -3.2,
        female: 3.4
    },
    {
        age: "5-9",
        male: -4.4,
        female: 4.1
    },
    {
        age: "0-4",
        male: -5.0,
        female: 4.8
    }
    ];

    let maleSeries;
    let femaleSeries;

    onMounted(() => {
        let root = am5.Root.new("chartdiv");
        let root1 = am5.Root.new("chartdiv-bar");
        root.setThemes([am5themes_Animated.new(root)]);
        root1.setThemes([am5themes_Animated.new(root1)]);

        let chart = root.container.children.push(
            am5map.MapChart.new(root, {
            panX: "translateX",
            panY: "translateY",
            projection: am5map.geoMercator()
        }));

        let polygonSeries = chart.series.push(
            am5map.MapPolygonSeries.new(root, {
            geoJSON: usaLow,
            // valueField: "value",
            // calculateAggregates: true,
        }));

        polygonSeries.mapPolygons.template.setAll({
            tooltipText: "{name}",
            interactive: true,
            stroke: am5.color(0xffffff),
            strokeWidth: 1,
            fill: am5.color(KTUtil.getCssVariableValue("--bs-gray-300"))
        });

        polygonSeries.mapPolygons.template.states.create("hover", {
            fill: am5.color(KTUtil.getCssVariableValue("--bs-success"))
        });


        polygonSeries.mapPolygons.template.events.on("pointerover", function(ev) {
            heatLegend.showValue(ev.target.dataItem.get("value"));
        });

        // Cargar dinámicamente el mapa de Perú

        let heatLegend = chart.children.push(
            am5.HeatLegend.new(root, {
            orientation: "vertical",
            startColor: am5.color(0x8ab7ff),
            endColor: am5.color(0x25529a),
            startText: "Menor",
            endText: "Mayor",
            stepCount: 5,

                // Posicionamiento a la derecha
                x: am5.percent(100),
                centerX: am5.percent(100),
                y: am5.percent(50),
                centerY: am5.percent(50)
            })
        );

        heatLegend.startLabel.setAll({
            fontSize: 12,
            fill: heatLegend.get("startColor")
        });

        heatLegend.endLabel.setAll({
            fontSize: 12,
            fill: heatLegend.get("endColor")
        });

        polygonSeries.events.on("datavalidated", function () {
            heatLegend.set("startValue", polygonSeries.getPrivate("valueLow"));
            heatLegend.set("endValue", polygonSeries.getPrivate("valueHigh"));
        });

        polygonSeries.mapPolygons.template.events.on("click", function (ev) {
            const regionData = ev.target.dataItem.dataContext;
            const regionName = regionData.name;
            document.getElementById("region-title").innerText = regionName;
    let datos = [
    {
        age: "85+",
        male: -10,
        female: 10
    },
    {
        age: "80-54",
        male: -0.2,
        female: 0.9
    },
    {
        age: "75-79",
        male: -0.9,
        female: 0.1
    },
    {
        age: "70-74",
        male: -0.8,
        female: 0.8
    },
    {
        age: "65-69",
        male: -0.8,
        female: 1.0
    },
    {
        age: "60-64",
        male: -1.1,
        female: 1.3
    },
    {
        age: "55-59",
        male: -1.7,
        female: 1.9
    },
    {
        age: "50-54",
        male: -2.2,
        female: 2.5
    },
    {
        age: "45-49",
        male: -2.8,
        female: 3.0
    },
    {
        age: "40-44",
        male: -3.4,
        female: 3.6
    },
    {
        age: "35-39",
        male: -4.2,
        female: 4.1
    },
    {
        age: "30-34",
        male: -5.2,
        female: 4.8
    },
    {
        age: "25-29",
        male: -5.6,
        female: 5.1
    },
    {
        age: "20-24",
        male: -5.1,
        female: 5.1
    },
    {
        age: "15-19",
        male: -3.8,
        female: 3.8
    },
    {
        age: "10-14",
        male: -3.2,
        female: 3.4
    },
    {
        age: "5-9",
        male: -4.4,
        female: 4.1
    },
    {
        age: "0-4",
        male: -5.0,
        female: 4.8
    }
    ];
            // Crear el gráfico de barras
            maleSeries.data.setAll(datos);
            femaleSeries.data.setAll(datos);
            yAxis.data.setAll(datos);
            yAxisRight.data.setAll(datos);
        });

        // BARRAS
        let chart1 = root1.container.children.push(
            am5xy.XYChart.new(root1, {
                panX: false,
                panY: false,
                // wheelX: "panX",
                // wheelY: "zoomX",
                layout: root1.verticalLayout,
                arrangeTooltips: false,
                paddingLeft: 0,
                paddingRight: 10
            })
        );

        chart1.getNumberFormatter().set("numberFormat", "#s%");

        let yAxis = chart1.yAxes.push(
        am5xy.CategoryAxis.new(root1, {
                categoryField: "age",
                renderer: am5xy.AxisRendererY.new(root1, {
                    inversed: true,
                    opposite: false,
                    cellStartLocation: 0.1,
                    cellEndLocation: 0.9,
                    minorGridEnabled: true,
                    minGridDistance: 20
                })
            })
        );
        yAxis.data.setAll(datos);
        // EJE IZQUIERDO
        yAxis.get("renderer").labels.template.setAll({
            fill: am5.color(KTUtil.getCssVariableValue("--bs-gray-500")), // Color del texto
            fontSize: 12,              // Tamaño de fuente
            fontWeight: "500",       // Negrita
        });


        let yAxisRight = chart1.yAxes.push(
            am5xy.CategoryAxis.new(root1, {
                    categoryField: "age",
                    renderer: am5xy.AxisRendererY.new(root1, {
                    inversed: true,
                    opposite: true,
                    cellStartLocation: 0.1,
                    cellEndLocation: 0.9,
                    minorGridEnabled: true,
                    minGridDistance: 20
                })
            })
        );
        yAxisRight.data.setAll(datos);
        // EJE DERECHO
        yAxisRight.get("renderer").labels.template.setAll({
            fill: am5.color(KTUtil.getCssVariableValue("--bs-gray-500")), // Color del texto
            fontSize: 12,              // Tamaño de fuente
            fontWeight: "500",       // Negrita
        });

        let xAxis = chart1.xAxes.push(
            am5xy.ValueAxis.new(root1, {
                renderer: am5xy.AxisRendererX.new(root1, {
                    minGridDistance: 60,
                    strokeOpacity: 0
                })
            })
        );

        xAxis.get("renderer").labels.template.setAll({
            fill: am5.color(KTUtil.getCssVariableValue("--bs-gray-500")), // Color del texto
            fontSize: 12,              // Tamaño de fuente
            fontWeight: "500",        // Negrita
        });

        // Eliminar cuadrícula del eje si quieres un fondo aún más limpio
        xAxis.get("renderer").grid.template.setAll({ strokeOpacity: 0 });
        yAxis.get("renderer").grid.template.setAll({ strokeOpacity: 0 });
        yAxisRight.get("renderer").grid.template.setAll({
            stroke: am5.color(KTUtil.getCssVariableValue("--bs-gray-500")),
            strokeDasharray: [4, 4],
            strokeOpacity: 1
        });


        function createSeries(field, labelCenterX, pointerOrientation, rangeValue, color) {
            let series = chart1.series.push(
                am5xy.ColumnSeries.new(root1, {
                xAxis: xAxis,
                yAxis: yAxis,
                valueXField: field,
                categoryYField: "age",
                sequencedInterpolation: true,
                clustered: false,
                tooltip: am5.Tooltip.new(root1, {
                        pointerOrientation: pointerOrientation,
                        labelText: `${field === "male" ? "Hombres" : "Mujeres"}, edad {categoryY}: {valueX}`,
                    })
                })
            );

            series.columns.template.setAll({
                height: am5.p100,
                strokeOpacity: 0,
                fillOpacity: 0.8,
                fill: am5.color(color),
                cornerRadiusBR: 4,
                cornerRadiusTR: 4,
                cornerRadiusBL: 0,
                cornerRadiusTL: 0,
            });

            series.bullets.push(function() {
                return am5.Bullet.new(root1, {
                locationX: 1,
                locationY: 0.5,
                sprite: am5.Label.new(root1, {
                        centerY: am5.p50,
                        // text: "{valueX}",
                        populateText: true,
                        centerX: labelCenterX,
                    })
                });
            });

            series.data.setAll(datos);
            series.appear();

            let rangeDataItem = xAxis.makeDataItem({
                value: rangeValue
            });
            xAxis.createAxisRange(rangeDataItem);
            rangeDataItem.get("grid").setAll({
                strokeOpacity: 0,
                stroke: series.get("stroke")
            });

            let label = rangeDataItem.get("label");
            label.setAll({
                text: field.toUpperCase(),
                fontSize: 13,
                fontWeight: "500",
                fill: am5.color(color),
                paddingTop: -20,
                isMeasured: false,
                centerX: labelCenterX
            });
            label.adapters.add("dy", function() {
                return -chart1.plotContainer.height();
            });

            return series;
        }

        maleSeries = createSeries("male", am5.p100, "right", -3, KTUtil.getCssVariableValue("--bs-success"));
        femaleSeries = createSeries("female", 0, "left", 3, KTUtil.getCssVariableValue("--bs-primary"));

        let cursor = chart1.set("cursor", am5xy.XYCursor.new(root1, {
            behavior: "zoomY"
        }));
        cursor.lineY.set("forceHidden", true);
        cursor.lineX.set("forceHidden", true);

        chart1.appear(1000, 100);
    });

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="card card-flush overflow-hidden h-xl-100">
            <div class="card-header py-5">
                <h3 class="card-title align-items-start flex-column">
                    <span class="card-label fw-bold text-dark">Human Resources</span>
                    <span class="text-gray-400 mt-1 fw-semibold fs-6">Reports by states and ganders</span>
                </h3>
            </div>
            <div class="card-body pt-0">
                <h1 id="region-title" class="text-xl font-bold mb-4">Seleccione una región</h1>
                <div class="d-flex">
                    <div id="chartdiv-bar" style="width: 50%; height: 500px;"></div>
                    <div id="chartdiv" style="width: 50%; height: 500px;"></div>
                </div>
            </div>
        </div>
    </App>
</template>
