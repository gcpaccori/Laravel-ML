<script setup>
    import { ref, onMounted, nextTick, computed } from 'vue';
    import DataTable from 'datatables.net-vue3';
    import DataTablesLib from 'datatables.net-bs5'; // Usa este en lugar de 'datatables.net'
    import 'datatables.net-bs5/css/dataTables.bootstrap5.css';
    import * as ElementPlusIconsVue from '@element-plus/icons-vue'

    // Inicializar DataTable
    DataTable.use(DataTablesLib);

    // Props
    const props = defineProps({
        ajaxUrl: {
            type: String,
            required: false,
        },
        columns: {
            type: Object,
            required: true,
        },
        filters: {
            type: Object,
            default: () => ({})
        },
        showCard: {
            type: Boolean,
            default: true
        },
        lengthMenu: {
            type: Array,
            default: [10,20,50,70,100]
        },
        autoLoad: {
            type: Boolean,
            default: true
        }
    });

    const scrollTopWrapper = ref(null);
    const scrollTopInner = ref(null);
    const scrollBottomWrapper = ref(null);

    const emit = defineEmits(['tableReady', 'action', 'dataLoaded']);

    const tableRef = ref(null);
    const showFilters = ref(false);
    const icons = ElementPlusIconsVue;
    const hasData = ref(false);

const ajaxConfig = ref({
  url: props.ajaxUrl,
  data: function (d) {
    return { ...d, ...props.filters };
  }
});

    const IndexBtnAction = computed(() => {
        return props.columns.findIndex(col => col.data === 'action');
    });

    const visibleColumns = computed(() =>
        props.columns.filter(col => col.data !== 'action')
    );

    // Computed para determinar si usar serverSide
    const useServerSide = computed(() => {
        if (props.autoLoad) {
            return true; // Si autoLoad está activo, usar serverSide por defecto
        }
        return hasData.value; // Si no hay autoLoad, usar serverSide solo si hay datos
    });

    const handleAction = (action, id, nameFuncion) => {
        emit('action', { action, id, nameFuncion });
    };

    // Función para cargar datos manualmente
    const loadData = () => {
        clearTable(); // Limpiar la tabla antes de cargar nuevos datos
        if (tableRef.value && tableRef.value.dt) {

          const dt = tableRef.value.dt;
          const settings = dt?.settings()[0];

          if (!settings.ajax) {
            // Si aún no tiene ajax definido, se lo asignamos manualmente
            settings.ajax = {
              url: props.ajaxUrl,
              data: function (d) {
                return { ...d, ...props.filters };
              }
            };
          } else {
            // Si ya tenía ajax, actualizamos los filtros
            settings.ajax.data = function (d) {
              return { ...d, ...props.filters };
            };
          }

          // Finalmente recargamos la tabla (desde la página 1)
            dt.ajax.reload((json) => {
                hasData.value = json.recordsTotal > 0;
                // Emitir evento con información de los datos cargados
                emit('dataLoaded', {
                    hasData: hasData.value,
                    totalRecords: json.recordsTotal,
                    filteredRecords: json.recordsFiltered
                });
            });
        }
    };

    // Función para limpiar/vaciar la tabla
    const clearTable = () => {
        if (tableRef.value && tableRef.value.dt) {
            // Si la tabla tiene configuración AJAX, la desactivamos
            if (hasData.value || props.autoLoad) {
                tableRef.value.dt.clear().draw();
                hasData.value = false;

                // Emitir evento de datos limpiados
                emit('dataLoaded', {
                    hasData: false,
                    totalRecords: 0,
                    filteredRecords: 0
                });
            }
        }
    };

    defineExpose({
        triggerAction: handleAction, // Para usarlo en el componente padre
        loadData: loadData, // Exponer la función para cargar datos manualmente
        clearTable: clearTable // Exponer la función para limpiar la tabla
    });

    onMounted( async() => {
        const dt = tableRef.value.dt;
        emit('tableReady', dt);

        const top = scrollTopWrapper.value
        const topInner = scrollTopInner.value
        const bottom = scrollBottomWrapper.value

        const syncScroll = async () => {
            top.scrollLeft = bottom.scrollLeft
            topInner.style.width = bottom.scrollWidth + 'px'
        }

        top.addEventListener('scroll', () => {
            bottom.scrollLeft = top.scrollLeft
        })

        bottom.addEventListener('scroll', () => {
            top.scrollLeft = bottom.scrollLeft
        })

        await nextTick(); // espera que todo se renderice
        setTimeout(syncScroll, 1500) // tiempo para que renderice la tabla
    });

</script>

<template>
    <div v-if="showCard" class="card">
        <div v-if="$slots.filters" class="card-header border-0 pt-6">
            <!-- Botón para mostrar u ocultar los filtros -->
            <h6 class="text-primary cursor-pointer text-hover-info" @click="showFilters = !showFilters">
                {{ showFilters ? '[- Ocultar Filtros]' : '[+ Mostrar Filtros]' }}
            </h6>

            <!-- Mostrar contenido de filtros solo si está activo -->
            <template v-if="showFilters">
                <slot name="filters"/>
            </template>
        </div>

        <div class="card-body py-4">

            <!-- Scroll superior sincronizado -->
            <div class="scroll-x scroll-x-top mb-2" ref="scrollTopWrapper">
                <div ref="scrollTopInner" style="height: 1px;"></div>
            </div>

            <div class="table-responsive" ref="scrollBottomWrapper">
                <DataTable
                    ref="tableRef"
                    :ajax = "autoLoad ? {
                        url: ajaxUrl,
                        data: function (d) {
                            return { ...d, ...filters }; // mezcla filtros personalizados con los datos internos de DT
                        }
                    }:false"
                    :columns="columns"
                    class="table align-middle table-row-dashed gy-2"
                    :options="{
                        lengthMenu: props.lengthMenu,
                        order: [0, 'desc'],
                        searching: true,
                        processing: true,
                        serverSide: false,
                        language: {
                            url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
                        },
                        headerCallback: function(thead, data, start, end, display) {
                            // thead.classList.remove('text-start', 'text-muted', 'fw-bold', 'text-uppercase', 'gs-0', 'bg-light');
                            thead.classList.add('text-start', 'text-muted', 'fw-bold', 'text-uppercase', 'gs-0', 'bg-light', 'fs-7');
                        },
                        createdRow: function(row, data, dataIndex) {
                            row.classList.add('text-gray-700', 'fw-semibold', 'bg-hover-light');
                        }
                    }"
                >
                    <!-- Todos los slots dinámicos enviados desde el padre -->
                    <template v-for="(item, index) in visibleColumns" :key="index" v-slot:[`column-${index}`]="slotProps">
                        <div v-if="$slots[`column-${index}`]">
                            <slot :name="`column-${index}`" v-bind="slotProps"/>
                        </div>
                        <div v-else v-html="slotProps.cellData" />
                    </template>

                    <!-- SLOT DE ACCIONES -->
                    <template v-slot:[`column-${IndexBtnAction}`]="props">
                        <div class="d-flex text-center justify-content-center">
                            <el-button-group class="ml-4">
                                <el-tooltip
                                    v-for="(btn, index) in props.rowData.action"
                                    :key="index"
                                    class="box-item"
                                    effect="dark"
                                    :content="btn.action"
                                    placement="top"
                                >
                                    <el-button
                                        size="small"
                                        :type="btn.type"
                                        :icon="icons[btn.icon]"
                                        @click="handleAction(btn.action, btn.id, btn.name_funcion)"
                                    />
                                </el-tooltip>
                            </el-button-group>
                        </div>
                    </template>
                </DataTable>
            </div>
        </div>
    </div>
    <div v-else>
        <div v-if="$slots.filters" class="card-header border-0 pt-6">
            <!-- Botón para mostrar u ocultar los filtros -->
            <h6 class="text-primary cursor-pointer text-hover-info" @click="showFilters = !showFilters">
                {{ showFilters ? '[- Ocultar Filtros]' : '[+ Mostrar Filtros]' }}
            </h6>

            <!-- Mostrar contenido de filtros solo si está activo -->
            <template v-if="showFilters">
                <slot name="filters"/>
            </template>
        </div>

        <!-- Scroll superior sincronizado -->
        <div class="scroll-x scroll-x-top mb-2" ref="scrollTopWrapper">
            <div ref="scrollTopInner" style="height: 1px;"></div>
        </div>

        <div class="table-responsive" ref="scrollBottomWrapper">
            <DataTable
                ref="tableRef"
                v-bind="autoLoad ? { ajax: ajaxConfig } : {}"
                :columns="columns"
                class="table align-middle table-row-dashed gy-2"
                :options="{
                    deferLoading: 0,
                    lengthMenu: props.lengthMenu,
                    order: [0, 'desc'],
                    searching: true,
                    processing: true,
                    serverSide: false,
                    language: {
                        url: 'https://cdn.datatables.net/plug-ins/1.13.5/i18n/es-ES.json'
                    },
                    headerCallback: function(thead, data, start, end, display) {
                        // thead.classList.remove('text-start', 'text-muted', 'fw-bold', 'text-uppercase', 'gs-0', 'bg-light');
                        thead.classList.add('text-start', 'text-muted', 'fw-bold', 'text-uppercase', 'gs-0', 'bg-light', 'fs-7');
                    },
                    createdRow: function(row, data, dataIndex) {
                        row.classList.add('text-gray-700', 'fw-semibold', 'bg-hover-light');
                    }
                }"
            >
                <!-- Todos los slots dinámicos enviados desde el padre -->
                <template v-for="(item, index) in visibleColumns" :key="index" v-slot:[`column-${index}`]="slotProps">
                    <div v-if="$slots[`column-${index}`]">
                        <slot :name="`column-${index}`" v-bind="slotProps"/>
                    </div>
                    <div v-else v-html="slotProps.cellData" />
                </template>

                <!-- SLOT DE ACCIONES -->
                <template v-slot:[`column-${IndexBtnAction}`]="props">
                    <div class="d-flex text-center justify-content-center">
                        <el-button-group class="ml-4">
                            <el-tooltip
                                v-for="(btn, index) in props.rowData.action"
                                :key="index"
                                class="box-item"
                                effect="dark"
                                :content="btn.action"
                                placement="top"
                            >
                                <el-button
                                    size="small"
                                    :type="btn.type"
                                    :icon="icons[btn.icon]"
                                    @click="handleAction(btn.action, btn.id, btn.name_funcion)"
                                />
                            </el-tooltip>
                        </el-button-group>
                    </div>
                </template>
            </DataTable>
        </div>
    </div>
</template>

