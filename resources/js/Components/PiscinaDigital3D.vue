<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import * as THREE from "three";

const props = defineProps({
    qualityIndex: { type: Number, default: 100 },
    temperatureC: { type: Number, default: 28 },
    averageWeightG: { type: Number, default: 0 },
    projectedWeightG: { type: Number, default: 0 },
    estimatedFishCount: { type: Number, default: 0 },
    focusedModel: { type: String, default: "Piscina" },
});

const sceneHost = ref(null);
const webglError = ref("");

let renderer;
let scene;
let camera;
let water;
let waterGeometry;
let waterBasePositions = [];
let fishGroup;
let particleCloud;
let particleMaterial;
let animationFrame;
let resizeObserver;
let startedAt = 0;

const clamp = (value, minimum, maximum) => Math.min(maximum, Math.max(minimum, value));

const qualityColor = (quality) => {
    if (quality >= 70) return "#1d9ac8";
    if (quality >= 50) return "#6e948f";
    if (quality >= 25) return "#7a765e";
    return "#6d5c4e";
};

const fishColor = (quality) => {
    if (quality >= 70) return "#ed9c3f";
    if (quality >= 50) return "#c58d4b";
    return "#8a765c";
};

const displayFishCount = () => clamp(Math.round((Number(props.estimatedFishCount) || 500) / 42), 12, 56);

const makeFish = (index) => {
    const fish = new THREE.Group();
    const material = new THREE.MeshStandardMaterial({ color: fishColor(props.qualityIndex), roughness: 0.54 });
    const body = new THREE.Mesh(new THREE.SphereGeometry(0.22, 14, 10), material);
    body.scale.set(1.55, 0.64, 0.46);
    const tail = new THREE.Mesh(new THREE.ConeGeometry(0.13, 0.28, 4), material);
    tail.rotation.z = Math.PI / 2;
    tail.position.x = -0.36;
    const dorsal = new THREE.Mesh(new THREE.ConeGeometry(0.07, 0.15, 3), material);
    dorsal.position.y = 0.13;
    fish.add(body, tail, dorsal);
    fish.userData = {
        phase: index * 0.83,
        radiusX: 1.2 + (index % 8) * 0.34,
        radiusZ: 0.7 + (index % 6) * 0.23,
        speed: 0.21 + (index % 7) * 0.026,
        material,
    };
    return fish;
};

const rebuildFish = () => {
    if (!fishGroup) return;
    fishGroup.clear();
    for (let index = 0; index < displayFishCount(); index += 1) {
        fishGroup.add(makeFish(index));
    }
};

const rebuildParticles = () => {
    if (!particleCloud) return;
    const quality = clamp(Number(props.qualityIndex) || 0, 0, 100);
    const count = quality < 70 ? Math.round((70 - quality) * 2.2) : 0;
    const positions = new Float32Array(Math.max(count, 1) * 3);
    for (let index = 0; index < count; index += 1) {
        positions[index * 3] = (Math.random() - 0.5) * 6.6;
        positions[index * 3 + 1] = -0.02 + Math.random() * 0.48;
        positions[index * 3 + 2] = (Math.random() - 0.5) * 3.8;
    }
    particleCloud.geometry.dispose();
    particleCloud.geometry = new THREE.BufferGeometry();
    particleCloud.geometry.setAttribute("position", new THREE.BufferAttribute(positions, 3));
    particleCloud.geometry.setDrawRange(0, count);
    particleMaterial.opacity = quality < 70 ? clamp((70 - quality) / 45, 0.16, 0.74) : 0;
};

const applyState = () => {
    if (!water || !fishGroup) return;
    const quality = clamp(Number(props.qualityIndex) || 0, 0, 100);
    const ratio = props.projectedWeightG > 0 && props.averageWeightG > 0
        ? props.projectedWeightG / props.averageWeightG
        : 1;
    const fishScale = clamp(0.77 + ratio * 0.22, 0.78, 1.42);
    water.material.color.set(qualityColor(quality));
    water.material.opacity = quality >= 70 ? 0.68 : quality >= 50 ? 0.76 : 0.88;
    fishGroup.children.forEach((fish) => {
        fish.scale.setScalar(fishScale);
        fish.userData.material.color.set(fishColor(quality));
    });
    rebuildParticles();
};

const resize = () => {
    if (!renderer || !camera || !sceneHost.value) return;
    const width = sceneHost.value.clientWidth;
    const height = sceneHost.value.clientHeight;
    if (!width || !height) return;
    camera.aspect = width / height;
    camera.updateProjectionMatrix();
    renderer.setSize(width, height, false);
};

const animate = (timestamp) => {
    animationFrame = requestAnimationFrame(animate);
    const elapsed = (timestamp - startedAt) / 1000;
    const quality = clamp(Number(props.qualityIndex) || 0, 0, 100);
    const temperatureFactor = clamp((Number(props.temperatureC) - 20) / 14, 0.25, 1.2);
    const turbidityFactor = quality < 70 ? 1 + (70 - quality) / 70 : 1;
    const waterAmplitude = (quality < 50 ? 0.055 : 0.027) * turbidityFactor;

    if (waterGeometry) {
        const positions = waterGeometry.attributes.position;
        for (let index = 0; index < positions.count; index += 1) {
            const base = waterBasePositions[index];
            positions.setZ(index, base.z + Math.sin(elapsed * 1.35 + base.x * 1.45 + base.y) * waterAmplitude);
        }
        positions.needsUpdate = true;
        waterGeometry.computeVertexNormals();
    }

    fishGroup?.children.forEach((fish) => {
        const data = fish.userData;
        const angle = elapsed * data.speed * temperatureFactor + data.phase;
        fish.position.set(Math.sin(angle) * data.radiusX, 0.18 + Math.sin(angle * 2.1) * 0.12, Math.cos(angle) * data.radiusZ);
        fish.rotation.y = -angle + Math.PI / 2;
    });

    if (particleCloud) particleCloud.rotation.y = elapsed * 0.04;
    camera.position.x = Math.sin(elapsed * 0.055) * 0.55 + 7.2;
    camera.lookAt(0, 0, 0);
    renderer?.render(scene, camera);
};

const initScene = () => {
    if (!sceneHost.value) return;
    try {
        scene = new THREE.Scene();
        scene.background = new THREE.Color("#dceff5");
        camera = new THREE.PerspectiveCamera(42, 1, 0.1, 100);
        camera.position.set(7.2, 5.3, 7.4);
        camera.lookAt(0, 0, 0);

        renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.outputColorSpace = THREE.SRGBColorSpace;
        sceneHost.value.appendChild(renderer.domElement);

        scene.add(new THREE.HemisphereLight("#ffffff", "#8195a4", 2.1));
        const light = new THREE.DirectionalLight("#ffffff", 2.4);
        light.position.set(4, 8, 3);
        scene.add(light);

        const floor = new THREE.Mesh(
            new THREE.BoxGeometry(8.4, 0.34, 5.6),
            new THREE.MeshStandardMaterial({ color: "#d9e3e8", roughness: 0.9 }),
        );
        floor.position.y = -0.28;
        scene.add(floor);

        const wallMaterial = new THREE.MeshStandardMaterial({ color: "#f4f7f8", roughness: 0.72 });
        const walls = [
            [0, 0.35, -2.58, 8.1, 0.86, 0.18],
            [0, 0.35, 2.58, 8.1, 0.86, 0.18],
            [-3.96, 0.35, 0, 0.18, 0.86, 5.1],
            [3.96, 0.35, 0, 0.18, 0.86, 5.1],
        ];
        walls.forEach(([x, y, z, width, height, depth]) => {
            const wall = new THREE.Mesh(new THREE.BoxGeometry(width, height, depth), wallMaterial);
            wall.position.set(x, y, z);
            scene.add(wall);
        });

        waterGeometry = new THREE.PlaneGeometry(7.72, 4.86, 38, 28);
        waterGeometry.rotateX(-Math.PI / 2);
        waterBasePositions = Array.from({ length: waterGeometry.attributes.position.count }, (_, index) => ({
            x: waterGeometry.attributes.position.getX(index),
            y: waterGeometry.attributes.position.getY(index),
            z: waterGeometry.attributes.position.getZ(index),
        }));
        water = new THREE.Mesh(
            waterGeometry,
            new THREE.MeshPhysicalMaterial({
                color: qualityColor(props.qualityIndex),
                transparent: true,
                opacity: 0.7,
                roughness: 0.28,
                metalness: 0,
                clearcoat: 0.22,
                side: THREE.DoubleSide,
            }),
        );
        water.position.y = 0.14;
        scene.add(water);

        fishGroup = new THREE.Group();
        scene.add(fishGroup);
        particleMaterial = new THREE.PointsMaterial({ color: "#775f45", size: 0.075, transparent: true, opacity: 0 });
        particleCloud = new THREE.Points(new THREE.BufferGeometry(), particleMaterial);
        scene.add(particleCloud);
        rebuildFish();
        applyState();
        resize();
        resizeObserver = new ResizeObserver(resize);
        resizeObserver.observe(sceneHost.value);
        window.addEventListener("resize", resize);
        startedAt = performance.now();
        animate(startedAt);
    } catch (error) {
        webglError.value = "No fue posible iniciar la visualizacion 3D en este navegador.";
    }
};

onMounted(initScene);

watch(
    () => [props.qualityIndex, props.temperatureC, props.averageWeightG, props.projectedWeightG, props.estimatedFishCount],
    () => {
        rebuildFish();
        applyState();
    },
);

onBeforeUnmount(() => {
    cancelAnimationFrame(animationFrame);
    resizeObserver?.disconnect();
    window.removeEventListener("resize", resize);
    waterGeometry?.dispose();
    water?.material?.dispose();
    fishGroup?.traverse((object) => {
        if (object.geometry) object.geometry.dispose();
        if (object.material) object.material.dispose();
    });
    particleCloud?.geometry?.dispose();
    particleMaterial?.dispose();
    renderer?.dispose();
    renderer?.domElement?.remove();
});
</script>

<template>
    <section class="pool-scene" aria-label="Simulacion 3D de la piscina">
        <div ref="sceneHost" class="pool-scene__canvas"></div>
        <div class="pool-scene__caption">
            <span>Simulacion 3D</span>
            <strong>{{ focusedModel }}</strong>
        </div>
        <div v-if="webglError" class="pool-scene__error">{{ webglError }}</div>
    </section>
</template>

<style scoped>
.pool-scene {
    position: relative;
    width: 100%;
    min-height: 560px;
    background: #dceff5;
    overflow: hidden;
}

.pool-scene__canvas {
    width: 100%;
    height: 560px;
}

.pool-scene__canvas :deep(canvas) {
    display: block;
    width: 100%;
    height: 100%;
}

.pool-scene__caption {
    position: absolute;
    top: 18px;
    left: 20px;
    display: flex;
    flex-direction: column;
    color: #17324d;
    pointer-events: none;
}

.pool-scene__caption span {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.pool-scene__caption strong { font-size: 16px; }

.pool-scene__error {
    position: absolute;
    inset: 0;
    display: grid;
    place-items: center;
    color: #7c2d12;
    background: #fff7ed;
    padding: 24px;
    text-align: center;
}

@media (max-width: 720px) {
    .pool-scene,
    .pool-scene__canvas { min-height: 430px; height: 430px; }
}
</style>
