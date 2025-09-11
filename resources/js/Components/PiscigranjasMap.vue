<template>
  <div>
    <div id="map" class="h-600px w-full"></div>
  </div>
</template>

<script setup>
import { onMounted, watch } from 'vue';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const props = defineProps({
  piscigranjas: {
    type: Array,
    required: true,
  },
});

let map;
let markersLayer;

// Icono personalizado
const customIcon = L.divIcon({
  html: `
    <div style="
      background-color: #2563eb;
      width: 30px;
      height: 30px;
      border-radius: 50% 50% 50% 0;
      transform: rotate(-45deg);
      border: 3px solid #ffffff;
      box-shadow: 0 2px 8px rgba(0,0,0,0.3);
      display: flex;
      align-items: center;
      justify-content: center;
    ">
      <div style="
        transform: rotate(45deg);
        color: white;
        font-size: 16px;
        font-weight: bold;
      ">🐟</div>
    </div>
  `,
  className: 'custom-div-icon',
  iconSize: [30, 30],
  iconAnchor: [15, 30],
  popupAnchor: [0, -30]
});

onMounted(() => {
    // Capas base
    const esriSat = L.tileLayer(
      'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      {
        attribution: '© Esri, USGS, GeoEye, IGN, and the GIS User Community',
        maxZoom: 17,
      }
    );

    const osmStreet = L.tileLayer(
      'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19,
      }
    );

    // Overlay de etiquetas de Esri (para poner encima del satélite)
    const esriLabels = L.tileLayer(
      'https://services.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}',
      {
        attribution: '© Esri',
        maxZoom: 17,
      }
    );

    // Definimos combinaciones
    const baseMaps = {
      "🌍 Satélite": esriSat,
      "🗺️ Calles (OSM)": osmStreet,
      "🌍 Satélite + Calles": L.layerGroup([esriSat, esriLabels]),
    };

  map = L.map('map', {
    center: [-9.189967, -75.015152],
    zoom: Math.min(5, esriSat.options.maxZoom), // zoom inicial no mayor al permitido
    maxZoom: esriSat.options.maxZoom,
  });


  // Añadimos la capa inicial
  esriSat.addTo(map);

  // Control de capas
  L.control.layers(baseMaps).addTo(map);

  // Escuchar cambios de capa para ajustar maxZoom
  map.on('baselayerchange', function (e) {
    let maxZoom;

    if (e.name === "🗺️ Calles (OSM)") {
      maxZoom = osmStreet.options.maxZoom;
    } else {
      maxZoom = 17; // Esri Satélite y Satélite+Calles
    }

    // Cambiar el maxZoom del mapa
    map.setMaxZoom(maxZoom);

    // Si el zoom actual es mayor, ajustarlo al límite
    if (map.getZoom() > maxZoom) {
      map.setZoom(maxZoom);
    }
  });

  // Capa para los marcadores
  markersLayer = L.layerGroup().addTo(map);
  dibujarMarcadores();
});

function dibujarMarcadores() {
  markersLayer.clearLayers();

  props.piscigranjas.forEach((p) => {
    if (p.latitud && p.longitud) {
      L.marker([parseFloat(p.latitud), parseFloat(p.longitud)], {
        icon: customIcon
      })
        .addTo(markersLayer)
        .bindPopup(`
          <div style="font-family: Arial, sans-serif;">
            <h3 style="margin: 0 0 8px 0; color: #1e40af; font-size: 16px;">
              🐟 ${p.nombre}
            </h3>
            <p style="margin: 4px 0; color: #6b7280; font-size: 14px;">
              📍 ${p.direccion || 'Dirección no especificada'}
            </p>
            <p style="margin: 4px 0; color: #6b7280; font-size: 14px;">
              👤 <strong>Propietario:</strong> ${p.propietario || 'No especificado'}
            </p>
          </div>
        `);
    }
  });

  if (props.piscigranjas.length > 0) {
    const group = new L.featureGroup(
      props.piscigranjas
        .filter((p) => p.latitud && p.longitud)
        .map((p) => L.marker([parseFloat(p.latitud), parseFloat(p.longitud)]))
    );
    map.fitBounds(group.getBounds().pad(0.2));
  }
}

// Redibujar cuando cambian los props
watch(
  () => props.piscigranjas,
  () => {
    dibujarMarcadores();
  }
);
</script>

<style>
#map {
  border-radius: 0.5rem;
}
</style>
