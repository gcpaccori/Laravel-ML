<script setup>
    import * as am5 from "@amcharts/amcharts5";
    import * as am5map from "@amcharts/amcharts5/map";
    import * as am5xy from "@amcharts/amcharts5/xy";
    import * as am5index from "@amcharts/amcharts5/index";
    import am5themes_Animated from "@amcharts/amcharts5/themes/Animated";
    import { onMounted, onBeforeUnmount, ref } from "vue";
    import am5geodata_peruLow from "@amcharts/amcharts5-geodata/peruLow";


    const props = defineProps({
        title: String,
        toolbar: {
            type: Array,
            required: false
        }
    });

    const mapPiscigranja = () => {
        /* Datos de prueba: piscigranjas por departamento */
        let data = [
        { id: "PE-AMA", name: "Amazonas", value: 12 },
        { id: "PE-ANC", name: "Áncash", value: 25 },
        { id: "PE-CUS", name: "Cusco", value: 40 },
        { id: "PE-PUN", name: "Puno", value: 60 },
        { id: "PE-LIM", name: "Lima", value: 90 },
        { id: "PE-LOR", name: "Loreto", value: 35 },
        { id: "PE-SAM", name: "San Martín", value: 20 }
        ];

        /* Chart code */
        let root = am5.Root.new("map_piscigranja");
        root.setThemes([am5themes_Animated.new(root)]);

        let chart = root.container.children.push(am5map.MapChart.new(root, {
        projection: am5map.geoMercator()
        }));

        /* Serie de polígonos (mapa de Perú) */
        let polygonSeries = chart.series.push(
        am5map.MapPolygonSeries.new(root, {
            geoJSON: null,
            exclude: ["AQ"]
        })
        );

        /* Serie de burbujas */
        let bubbleSeries = chart.series.push(
        am5map.MapPointSeries.new(root, {
            valueField: "value",
            calculateAggregates: true,
            polygonIdField: "id"
        })
        );

        let circleTemplate = am5.Template.new({});

        /* Burbuja con círculo rojo */
        bubbleSeries.bullets.push(function(root, series, dataItem) {
        let container = am5.Container.new(root, {});

        let circle = container.children.push(am5.Circle.new(root, {
            radius: 20,
            fillOpacity: 0.7,
            fill: am5.color(0xff0000),
            tooltipText: "{name}: [bold]{value} piscigranjas[/]"
        }, circleTemplate));

        return am5.Bullet.new(root, {
            sprite: container,
            dynamic: true
        });
        });

        /* Texto dentro de la burbuja */
        bubbleSeries.bullets.push(function(root, series, dataItem) {
        return am5.Bullet.new(root, {
            sprite: am5.Label.new(root, {
            text: "{value}",
            fill: am5.color(0xffffff),
            populateText: true,
            centerX: am5.p50,
            centerY: am5.p50,
            fontWeight: "bold"
            }),
            dynamic: true
        });
        });

        /* Escala de tamaño (círculo proporcional al valor) */
        bubbleSeries.set("heatRules", [{
        target: circleTemplate,
        dataField: "value",
        min: 10,
        max: 50,
        minValue: 0,
        maxValue: 600, // máximo de piscigranjas esperado
        key: "radius"
        }]);

        /* Cargar geodata de Perú */
        am5.net.load("https://cdn.amcharts.com/lib/5/geodata/json/peruLow.json", chart).then(function(result) {
        let geodata = am5.JSONParser.parse(result.response);
        polygonSeries.set("geoJSON", geodata);
        bubbleSeries.data.setAll(data);
        });
    }

    const mapPiscigranjaSend = () => {
        /* Chart code */
        let root = am5.Root.new("map_piscigranja_send");
        root.setThemes([am5themes_Animated.new(root)]);

        let chart = root.container.children.push(
        am5map.MapChart.new(root, {
            projection: am5map.geoMercator(),
            panX: "translateX",
            panY: "translateY",
            wheelY: "zoom"
        })
        );

        // Serie del mapa (Perú)
        let polygonSeries = chart.series.push(
        am5map.MapPolygonSeries.new(root, {
            geoJSON: am5geodata_peruLow
        })
        );

        // Serie para líneas (comunicación)
        let lineSeries = chart.series.push(am5map.MapLineSeries.new(root, {}));
        lineSeries.mapLines.template.setAll({
        stroke: am5.color("#F5276C"),
        strokeWidth: 2,
        strokeOpacity: 0.8,
        strokeDasharray: [10, 5] // patrón de guiones
        });

        // Función de animación en movimiento (flujo hacia Lima)
        function animateLine(line) {
        line.animate({
            key: "strokeDashoffset",
            from: 0,
            to: -30,
            duration: 1000,
            loops: Infinity
        });
        }

        // Serie para piscigranjas (orígenes)
        let farmSeries = chart.series.push(am5map.MapPointSeries.new(root, {}));

        farmSeries.bullets.push(function () {
        let circle = am5.Circle.new(root, {
            radius: 6,
            tooltipText: "{title}: Piscigranja",
            fill: am5.color(0x00cc66),
            stroke: am5.color(0xffffff),
            strokeWidth: 2
        });

        // Efecto de pulso
        circle.animate({
            key: "scale",
            to: 1.5,
            from: 1,
            duration: 1000,
            loops: Infinity,
            easing: am5.ease.yoyo(am5.ease.cubic)
        });

        return am5.Bullet.new(root, { sprite: circle });
        });

        // Serie para central en Lima
        let limaSeries = chart.series.push(am5map.MapPointSeries.new(root, {}));
        limaSeries.bullets.push(function () {
        return am5.Bullet.new(root, {
            sprite: am5.Circle.new(root, {
            radius: 10,
            tooltipText: "Central: Lima",
            fill: am5.color(0xff0000),
            stroke: am5.color(0xffffff),
            strokeWidth: 3
            })
        });
        });

        // Coordenadas de Lima
        let lima = { id: "lima", title: "Lima", geometry: { type: "Point", coordinates: [-77.0428, -12.0464] } };

        // Piscigranjas simuladas
        let farms = [
        { id: "ucayali", title: "Ucayali", geometry: { type: "Point", coordinates: [-74.5500, -8.3791] } },
        { id: "loreto", title: "Loreto", geometry: { type: "Point", coordinates: [-73.2538, -3.7491] } },
        { id: "sanmartin", title: "San Martín", geometry: { type: "Point", coordinates: [-76.3667, -6.5000] } },
        { id: "madrededios", title: "Madre de Dios", geometry: { type: "Point", coordinates: [-70.2167, -12.5933] } }
        ];

        // Cargar datos
        limaSeries.data.setAll([lima]);
        farmSeries.data.setAll(farms);

        // Crear líneas dinámicas hacia Lima con animación
        function updateConnections() {
        let lines = [];
        farms.forEach(f => {
            let lineData = {
            geometry: {
                type: "LineString",
                coordinates: [f.geometry.coordinates, lima.geometry.coordinates]
            }
            };
            lines.push(lineData);
        });
        lineSeries.data.setAll(lines);

        // Agregar animación a cada línea
        lineSeries.mapLines.each(function (line) {
            animateLine(line);
        });
        }

        // Simulación en tiempo real
        updateConnections();
        setInterval(() => {
        updateConnections();
        }, 3000);

        // Animación de entrada
        chart.appear(1000, 100);
    }

    onMounted(() => {
        mapPiscigranja();
        mapPiscigranjaSend();
    });

</script>

<template>
    <App :title="title" :toolbar="toolbar">
        <div class="row">

            <div class="col-lg-6">
                <div class="card card-flush overflow-hidden h-xl-100">
                    <div class="card-header py-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-dark">Total de Piscigranja por Departamento</span>
                            <span class="text-gray-400 mt-1 fw-semibold fs-6">Conteo total de piscigranjas por departamento</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex">
                            <div id="map_piscigranja" style="width: 100%; height: 500px;"></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card card-flush overflow-hidden h-xl-100">
                    <div class="card-header py-5">
                        <h3 class="card-title align-items-start flex-column">
                            <span class="card-label fw-bold text-dark">Tranferencia de información</span>
                            <span class="text-gray-400 mt-1 fw-semibold fs-6">Envio de información a la central</span>
                        </h3>
                    </div>
                    <div class="card-body pt-0">
                        <div class="d-flex">
                            <div id="map_piscigranja_send" style="width: 100%; height: 500px;"></div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </App>
</template>
