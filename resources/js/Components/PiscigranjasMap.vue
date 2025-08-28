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

// const customIcon = L.icon({
//     iconUrl: 'assets/media/misc/marker-icon.png',
//     // iconSize:     [40, 50],
//     // iconAnchor:   [25, 41]
// });

onMounted(() => {
  map = L.map('map', {
    maxZoom: 16, // <- Limita el zoom máximo
  }).setView([-9.189967, -75.015152], 5);

  // Capa satélite
  L.tileLayer(
    'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
    {
      attribution:
        '© Esri, USDA, USGS, GeoEye, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
    }
  ).addTo(map);

  markersLayer = L.layerGroup().addTo(map);
  dibujarMarcadores();
});

function dibujarMarcadores() {
  markersLayer.clearLayers();

  props.piscigranjas.forEach((p) => {
    if (p.latitud && p.longitud) {
      L.marker([parseFloat(p.latitud), parseFloat(p.longitud)], {
        icon: customIcon // Usar el icono personalizado
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
