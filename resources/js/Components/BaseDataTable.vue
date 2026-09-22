<script setup>
import { ref, computed, onMounted } from 'vue'
import DataTable from 'datatables.net-vue3'
import DataTablesLib from 'datatables.net-bs5'
import 'datatables.net-bs5/css/dataTables.bootstrap5.css'
import * as ElementPlusIconsVue from '@element-plus/icons-vue'

DataTable.use(DataTablesLib)

const props = defineProps({
  ajaxUrl: {
    type: String,
    default: ''
  },

  columns: {
    type: Array,
    required: true
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
    default: () => [10, 20, 50, 70, 100]
  },

  searchIng: {
    type: Boolean,
    default: true
  },

  autoLoad: {
    type: Boolean,
    default: true
  }
})

const emit = defineEmits([
  'tableReady',
  'action',
  'dataLoaded'
])

const tableRef = ref(null)
const showFilters = ref(false)
const icons = ElementPlusIconsVue

const actionColumnIndex = computed(() =>
  props.columns.findIndex(col => col.data === 'action')
)

const visibleColumns = computed(() =>
  props.columns.filter(col => col.data !== 'action')
)

const ajaxConfig = computed(() => ({
  url: props.ajaxUrl,

  data(d) {
    return {
      ...d,
      ...props.filters
    }
  }
}))

const tableOptions = computed(() => ({
  lengthMenu: props.lengthMenu,
  order: [[0, 'desc']],
  searching: props.searchIng,
  processing: true,
  serverSide: !!props.ajaxUrl,
  language: {
    url: '/assets/js/es-Es.json',
    emptyTable: 'No hay datos disponibles',
    loadingRecords: 'Cargando datos...'
  },
  initComplete(settings, json) {
    const api = this.api();
    if (!json || !json.data || json.data.length === 0) {
        setTimeout(() => {
            api.draw(false)
        }, 0)
    }
  },
  headerCallback(thead) {
    thead.classList.add(
      'text-start',
      'text-muted',
      'fw-bold',
      'text-uppercase',
      'gs-0',
      'bg-light',
      'fs-7'
    )
  },
  createdRow(row) {
    row.classList.add(
      'text-gray-700',
      'fw-semibold',
      'bg-hover-light'
    )
  }
}))

const handleAction = (action, id, nameFuncion) => {
  emit('action', {
    action,
    id,
    nameFuncion
  })
}

const reload = () => {
  const dt = tableRef.value?.dt

  if (!dt) {
    return
  }

  dt.ajax.reload(null, false)
}

const clearTable = () => {
  const dt = tableRef.value?.dt

  if (!dt) {
    return
  }

  dt.clear().draw()

  emit('dataLoaded', {
    hasData: data.length > 0,
    totalRecords: 0,
    filteredRecords: 0
  })
}

onMounted(() => {
  const dt = tableRef.value?.dt

  if (dt) {
    emit('tableReady', dt)
  }
})

defineExpose({
  reload,
  clearTable,
  triggerAction: handleAction
})
</script>

<template>
  <div :class="showCard ? 'card' : ''">

    <div
      v-if="$slots.filters"
      class="card-header border-0 pt-6"
    >
      <h6
        class="text-primary cursor-pointer text-hover-info"
        @click="showFilters = !showFilters"
      >
        {{ showFilters
          ? '[- Ocultar Filtros]'
          : '[+ Mostrar Filtros]'
        }}
      </h6>

      <slot
        v-if="showFilters"
        name="filters"
      />
    </div>

    <div class="card-body py-0">

      <div class="table-responsive">

        <DataTable
          ref="tableRef"
          :ajax="props.autoLoad ? ajaxConfig : false"
          :columns="columns"
          class="table align-middle table-row-dashed gy-1 table-hover"
          :options="tableOptions"
        >

          <template
            v-for="(item, index) in visibleColumns"
            :key="index"
            v-slot:[`column-${index}`]="slotProps"
          >
            <div v-if="$slots[`column-${index}`]">
              <slot
                :name="`column-${index}`"
                v-bind="slotProps"
              />
            </div>

            <div
              v-else
              v-html="slotProps.cellData"
            />
          </template>

          <template
            v-slot:[`column-${actionColumnIndex}`]="scope"
          >
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
                    :disabled="btn.disabled"
                    aria-label="Acción de tabla"
                    @click="
                      handleAction(
                        btn.action,
                        btn.id,
                        btn.name_funcion
                      )
                    "
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
