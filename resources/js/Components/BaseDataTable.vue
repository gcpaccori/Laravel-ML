<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import DataTable from 'datatables.net-vue3'
import DataTablesLib from 'datatables.net-bs5'
import 'datatables.net-bs5/css/dataTables.bootstrap5.css'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'

DataTable.use(DataTablesLib)

const props = defineProps({
  ajaxUrl: { type: String, default: '' },
  columns: { type: Array, required: true },
  filters: { type: Object, default: () => ({}) },
  showCard: { type: Boolean, default: true },
  lengthMenu: { type: Array, default: () => [10, 20, 50, 70, 100] },
  autoLoad: { type: Boolean, default: true }
})
const emit = defineEmits(['tableReady', 'action', 'dataLoaded'])

/* refs */
const tableRef = ref(null)
const showFilters = ref(false)
const hasData = ref(false)
const icons = ElementPlusIconsVue

/* índice columna acción */
const actionColumnIndex = computed(() =>
  props.columns.findIndex(col => col.data === 'action')
)
const visibleColumns = computed(() =>
  props.columns.filter(col => col.data !== 'action')
)

/* serverSide logic */
const useServerSide = computed(() => {
  // lógica: si autoLoad => serverSide true; si no, depende si ya tenemos datos
  if (props.autoLoad) return true
  return hasData.value
})

/* ajax config (plain object computed) */
const ajaxConfig = computed(() => ({
  url: props.ajaxUrl,
  data(d) {
    return { ...d, ...props.filters }
  }
}))

/* tableOptions - generar valores primitivos (no refs) */
const tableOptions = computed(() => {
  const base = {
    lengthMenu: props.lengthMenu,
    order: [0, 'desc'],
    searching: true,
    processing: true,
    // serverSide: !!useServerSide.value, // boolean primitivo
    serverSide: false, // boolean primitivo
    language: {
      url: '/assets/js/es-Es.json',
      emptyTable: 'No hay datos disponibles',
      loadingRecords: 'Cargando datos...'
    },
    headerCallback(thead) {
      thead.classList.add(
        'text-start', 'text-muted', 'fw-bold', 'text-uppercase', 'gs-0', 'bg-light', 'fs-7'
      )
    },
    createdRow(row) {
      row.classList.add('text-gray-700', 'fw-semibold', 'bg-hover-light');
    }
  }

  // Si no es serverSide, puedes activar deferLoading para no pedir datos en init
  if (!useServerSide.value) base.deferLoading = 0
  return base
})

/* Exponer métodos al padre */
const handleAction = (action, id, nameFuncion) => {
  emit('action', { action, id, nameFuncion })
}

const clearTable = () => {
  if (tableRef.value?.dt) {
    try { tableRef.value.dt.clear().draw() } catch(e) { /* ignore */ }
  }
  hasData.value = false
  emit('dataLoaded', { hasData: false, totalRecords: 0, filteredRecords: 0 })
}

const loadData = () => {
  const dt = tableRef.value?.dt
  if (!dt) {
    console.warn('⚠️ La tabla aún no está lista, espera al evento tableReady.')
    return
  }

  const settings = dt.settings()[0]
  settings.ajax = ajaxConfig.value

  dt.ajax.reload(json => {
    hasData.value = json.recordsTotal > 0
    emit('dataLoaded', {
      hasData: hasData.value,
      totalRecords: json.recordsTotal,
      filteredRecords: json.recordsFiltered
    })
  })
}

/* KEY para forzar remount cuando cambie serverSide (o url) */
const tableKey = ref(0)

/* Cuando useServerSide cambie, destruye la instancia y remonta (incrementando key) */
watch(
  () => useServerSide.value,
  (newVal, oldVal) => {
    if (newVal === oldVal) return
    // destruye DT si existe
    if (tableRef.value?.dt) {
      try { tableRef.value.dt.destroy(true) } catch (e) {}
      tableRef.value = null
    }
    // Pequeña espera para asegurar limpieza y forzar remount
    setTimeout(() => {
      tableKey.value++
    }, 50)
  }
)

/* También remonta si cambia el ajaxUrl (opcional) */
watch(
  () => props.ajaxUrl,
  (newVal, oldVal) => {
    if (newVal === oldVal) return
    if (tableRef.value?.dt) {
      try { tableRef.value.dt.destroy(true) } catch (e) {}
      tableRef.value = null
    }
    setTimeout(() => tableKey.value++, 50)
  }
)

onMounted( async() => {
    const dt = tableRef.value.dt;
    emit('tableReady', dt);
})

/* Expose */
defineExpose({ loadData, clearTable, triggerAction: handleAction })
</script>

<template>
  <div :class="showCard ? 'card' : ''">
    <div v-if="$slots.filters" class="card-header border-0 pt-6">
      <h6 class="text-primary cursor-pointer text-hover-info" @click="showFilters = !showFilters">
        {{ showFilters ? '[- Ocultar Filtros]' : '[+ Mostrar Filtros]' }}
      </h6>
      <slot v-if="showFilters" name="filters" />
    </div>

    <div class="card-body py-0">
      <div class="table-responsive">
        <!-- Usamos :key para forzar remount cuando cambie serverSide/ajaxUrl -->
        <DataTable
          :key="tableKey"
          ref="tableRef"
          :ajax="props.autoLoad ? ajaxConfig : false"
          :columns="columns"
          class="table align-middle table-row-dashed gy-1 table-hover"
          :options="tableOptions"
        >
          <template v-for="(item, index) in visibleColumns" :key="index" v-slot:[`column-${index}`]="slotProps">
            <div v-if="$slots[`column-${index}`]">
              <slot :name="`column-${index}`" v-bind="slotProps" />
            </div>
            <div v-else v-html="slotProps.cellData" />
          </template>

          <template v-slot:[`column-${actionColumnIndex}`]="scope">
            <div class="d-flex justify-content-center">
              <el-button-group>
                <el-tooltip
                  v-for="(btn, idx) in scope.rowData.action"
                  :key="idx"
                  :content="btn.action"
                  placement="top"
                >
                  <el-button
                    size="small"
                    :type="btn.type"
                    :icon="icons[btn.icon]"
                    @click="handleAction(btn.action, btn.id, btn.name_funcion)"
                    aria-label="Acción de tabla"
                    :disabled="btn.disabled"
                  />
                </el-tooltip>
              </el-button-group>
            </div>
          </template>
        </DataTable>
      </div>
    </div>
  </div>
</template>
