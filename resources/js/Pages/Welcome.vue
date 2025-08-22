<script setup>
    const props = defineProps({
        accionesGrilla: {
            type: Array,
            required: false,
            default: []
        }
    });

    const showModal = () => {
        console.log('EXPORTAR A EXCEL');
    };

    function handleActionGrilla(nombre) {
        const funciones = {
            handleNew: showModal,
            // más funciones aquí...
        }

        if (funciones[nombre]) {
            funciones[nombre]()
        } else {
            console.warn(`No se encontró la función '${nombre}'`);
        }
    }
</script>

<template>
    <App >
        <h1>EXPORTAR</h1>

        <template #btnCreate>
            <h1>{{accionesGrilla}}</h1>
            <template v-for="(action, index) in accionesGrilla" :key="index">
                <el-button
                    :icon="action.icon"
                    :type="action.type"
                    class="mr-2"
                    @click="handleActionGrilla(action.name_funcion)"
                >
                    {{ action.action }}
                </el-button>
            </template>
        </template>
    </App>
</template>
