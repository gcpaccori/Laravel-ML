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
      L.marker([parseFloat(p.latitud), parseFloat(p.longitud)])
        .addTo(markersLayer)
        .bindPopup(`
          <b>${p.nombre}</b><br>
          ${p.direccion || ''}<br>
          Propietario: ${p.propietario || ''}
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
