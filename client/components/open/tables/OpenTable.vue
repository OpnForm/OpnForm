<template>
  <div 
    ref="root" 
    :class="[
      'flex-1 divide-y divide-accented w-full flex flex-col', 
      { 'fixed inset-0 bg-white dark:bg-neutral-900 z-[70]': isExpanded,
        'border-t mt-4': !isExpanded
       }
    ]"
  >
    <div ref="topBar" class="flex items-center gap-2 p-2 overflow-x-auto">
      <UInput 
        size="sm"
        variant="ghost"
        class="max-w-sm min-w-[12ch]" 
        placeholder="Search..." 
        icon="i-heroicons-magnifying-glass-solid"
        v-model="searchInput"
      />
      <USelectMenu
        size="sm"
        variant="ghost"
        class="w-24"
        v-if="hasStatus"
        v-model="statusFilter"
        value-key="value"
        :items="statusList"
        :search-input="false"
      />

      <TableColumnManager 
        class="ml-auto"
        :table-state="tableState"
      />

      <UButton
        v-if="canModify && selectedIds.length > 0"
        size="sm"
        color="error"
        variant="outline"
        :label="`Delete (${selectedIds.length})`"
        :loading="deleteMultiSubmissionsMutation.isPending.value"
        @click="onDeleteMultiClick"
      />

      <FormExportModal 
        :form="form"
        :columns="columnVisibility"
        :selected-ids="selectedIds"
      />

      <UTooltip text="Refresh" arrow>
        <UButton
          size="sm"
          color="neutral"
          variant="ghost"
          icon="i-heroicons-arrow-path"
          :loading="loading"
          @click="$emit('refresh')"
        />
      </UTooltip>

      <UTooltip arrow :text="isExpanded ? 'Exit fullscreen' : 'Fullscreen'">
        <UButton  
          size="sm"
          color="neutral"
          variant="ghost"
          :icon="isExpanded ? 'i-heroicons-arrows-pointing-in' : 'i-heroicons-arrows-pointing-out'"
          @click="toggleExpanded"
        />
      </UTooltip>

      <!-- Add pagination section -->
      <UPagination
        v-if="pagination && pagination.last_page > 1"
        v-model:page="pagination.current_page"
        :items-per-page="pagination.per_page"
        :total="pagination.total"
        size="sm"
        :sibling-count="0"
        :ui="{
          wrapper: 'w-auto',
          list: 'gap-0',
          ellipsis: 'hidden',
          first: 'hidden',
          last: 'hidden'
        }"
        @update:page="$emit('page-change', $event)"
      >
        <template #item="{ page, pageCount }">
          <span class="text-sm font-medium px-2">{{ page }} of {{ pageCount }}</span>
        </template>
      </UPagination>
    </div>

    <UTable
      v-if="form"
      ref="table"
      v-model:row-selection="rowSelection"
      v-model:column-order="columnOrder"
      :columns="allColumns"
      :column-visibility="columnVisibility"
      :column-pinning="columnPinning"
      :column-sizing="columnSizing"
      :data="filteredTableData"
      :loading="loading"
      sticky
      class="flex-1"
      :style="{ maxHeight, ...columnSizeVars }"
      :ui="{
        thead: 'bg-neutral-50',
        th: 'p-1 relative overflow-hidden bg-neutral-50',
        td: 'px-3 py-2 overflow-hidden data-[pinned=left]:bg-white data-[pinned=left]:border-t data-[pinned=left]:border-r data-[pinned=left]:border-neutral-200 border-r',
      }"
    >
      <template #select-header="{ table }">
        <div class="flex items-center justify-center" :style="{ width: '45px' }">
          <UCheckbox
            :model-value="table?.getIsSomeRowsSelected?.() ? 'indeterminate' : (table?.getIsAllRowsSelected?.() || false)"
            @update:model-value="() => table?.toggleAllRowsSelected?.()"
            :disabled="filteredTableData.length === 0"
          />
        </div>
      </template>

      <template #select-cell="{ row }">
        <div class="flex items-center justify-center" :style="{ width: '30px' }">
          <UCheckbox
            :model-value="row?.getIsSelected?.() || false"
            @update:model-value="() => row?.toggleSelected?.()"
          />
        </div>
      </template>

      <template v-for="col in tableColumns" :key="`${col.id}-header`" #[`${col.id}-header`]="{ column }">
        <TableHeader 
          :column="column"
          :table-state="tableState"
          :is-wrapped="columnWrapping[col.id]"
          @resize="handleColumnResize"
        />
      </template>
    
      <template 
        v-for="col in tableColumns" 
        :key="col.id"
        #[`${col.id}-cell`]="{ row }"
      >
        <div 
          :class="getCellClasses(col.id)"
          :style="{ 
            width: `var(--col-${col.id}-size, auto)`, 
          }"
        >
          <component
            :is="fieldComponents[col.type]"
            class="border-neutral-100 dark:border-neutral-900"
            :property="col"
            :value="row.original[col.id]"
          />
        </div>
      </template>
      
      <template #actions-cell="{ row }">
        <div class="flex justify-center" :style="{ width: `var(--col-actions-size, auto)` }">
          <RecordOperations
            :form="form"
            :submission-id="row.original.id"
            :data="sortedData"
          />
        </div>
      </template>
    </UTable>


  </div>
</template>

<script setup>
import { useEventListener, refDebounced } from '@vueuse/core'
import { useTableState } from '~/composables/components/tables/useTableState'
import FormExportModal from '~/components/open/forms/FormExportModal.vue'
import OpenText from "./components/OpenText.vue"
import OpenRichText from "./components/OpenRichText.vue"
import OpenUrl from "./components/OpenUrl.vue"
import OpenSelect from "./components/OpenSelect.vue"
import OpenMatrix from "./components/OpenMatrix.vue"
import OpenDate from "./components/OpenDate.vue"
import OpenFile from "./components/OpenFile.vue"
import OpenCheckbox from "./components/OpenCheckbox.vue"
import OpenPayment from "./components/OpenPayment.vue"
import OpenSubmissionStatus from "./components/OpenSubmissionStatus.vue"
import RecordOperations from "../components/RecordOperations.vue"
import TableHeader from "./components/TableHeader.vue"
import TableColumnManager from "./components/TableColumnManager.vue"
import { useFormSubmissions } from "~/composables/query/forms/useFormSubmissions"

const props = defineProps({
  data: {
    type: Array,
    default: () => [],
  },
  loading: {
    type: Boolean,
    default: false,
  },
  form: {
    type: Object,
    default: () => null,
  },
  pagination: {
    type: Object,
    default: () => null,
  },
})

const emit = defineEmits(["search", "filter", "page-change", "refresh"])

// Get workspace for table state
const { current: workspace } = useCurrentWorkspace()

// Check if the user can modify the table
const canModify = computed(() => (workspace && !workspace.is_readonly))

// Initialize table state (includes preferences internally)
const tableState = useTableState(
  computed(() => props.form),
  canModify.value
)

const { tableColumns: allColumns, columnVisibility, columnPinning, columnSizing, columnWrapping, columnOrder, handleColumnResize: handleColumnResizeState } = tableState

const tableColumns = computed(() => {
  return allColumns.value.filter(column => !['actions', 'select'].includes(column.id))
})

const fieldComponents = {
  text: OpenText,
  rich_text: OpenRichText,
  number: OpenText,
  rating: OpenText,
  scale: OpenText,
  slider: OpenText,
  select: OpenSelect,
  matrix: OpenMatrix,
  multi_select: OpenSelect,
  date: OpenDate,
  files: OpenFile,
  checkbox: OpenCheckbox,
  url: OpenUrl,
  email: OpenText,
  phone_number: OpenText,
  signature: OpenFile,
  payment: OpenPayment,
  barcode: OpenText,
  status: OpenSubmissionStatus,
  ip_address: OpenText,
}

const table = ref(null)
const root = ref(null)
const topBar = ref(null)
const isExpanded = ref(false)
const maxHeight = ref('800px') // fallback default
const searchInput = ref("")
const debouncedSearch = refDebounced(searchInput, 300)
const statusFilter = ref('all')
const alert = useAlert()

// Use form submissions composable for multi-delete
const { deleteMultiSubmissions } = useFormSubmissions()
const deleteMultiSubmissionsMutation = deleteMultiSubmissions()

// Watch and emit instead of filtering locally:
watch(debouncedSearch, (newSearch) => {
  emit('search', newSearch)
})

watch(statusFilter, (newStatus) => {
  emit('filter', { status: newStatus })
})

// Table row selection
const rowSelection = ref({})
const selectedIds = computed(() => {
  return table.value?.tableApi?.getFilteredSelectedRowModel()?.rows.map(row => row.original.id) || []
})
const clearSelection = () => {
  rowSelection.value = {}
}
const onDeleteMultiClick = () => {
  alert.confirm(`Do you really want to delete selected ${selectedIds.value.length} record${selectedIds.value.length > 1 ? 's' : ''}?`, deleteMultiRecord)
}
const deleteMultiRecord = () => {
  deleteMultiSubmissionsMutation.mutateAsync({ 
    formId: props.form.id, 
    submissionIds: selectedIds.value 
  }).then((data) => {
    clearSelection()
    if (data.type === "success") {
      alert.success(data.message)
    } else {
      alert.error("Something went wrong!")
    }
  }).catch((error) => {
    clearSelection()
    alert.error(error.data?.message || "Something went wrong!")
  })
}


const statusList = [
  { label: 'All', value: 'all' },
  { label: 'Submitted', value: 'completed' },
  { label: 'In Progress', value: 'partial' }
]

// Default sort by created_at desc
const sortedData = computed(() => {
  return props.data.sort((a, b) => new Date(b.created_at) - new Date(a.created_at))
})

// Replace with simple data pass-through:
const filteredTableData = computed(() => props.data || [])

const hasStatus = computed(() => {
  return props.form?.is_pro && (props.form.enable_partial_submissions ?? false)
})

// Since UTable only renders when form exists, no need for safe wrappers

// Cell styling based on wrapping preferences
const getCellClasses = (columnId) => {
  const isWrapped = columnWrapping.value?.[columnId] || false
  return {
    'text-truncate whitespace-nowrap': !isWrapped,
    'whitespace-normal': isWrapped,
  }
}

// Column size CSS variables - similar to TanStack Table React example
const columnSizeVars = computed(() => {
  // Ensure reactivity to columnSizing changes
  const sizing = columnSizing.value

  if (!sizing) return {}

  const colSizes = {}

  for (const [colId, size] of Object.entries(sizing)) {
    colSizes[`--header-${colId}-size`] = `${size}px`
    colSizes[`--col-${colId}-size`] = `${size}px`
  }

  return colSizes
})

const computeMaxHeight = () => {
  if (!root.value || !topBar.value) return
  
  const topBarHeight = topBar.value.offsetHeight
  
  if (isExpanded.value) {
    maxHeight.value = `${window.innerHeight - topBarHeight}px`
  } else {
    const rootRect = root.value.getBoundingClientRect()
    // 16px bottom margin for breathing room
    maxHeight.value = `${window.innerHeight - rootRect.top - topBarHeight}px`
  }
}

const toggleExpanded = () => {
  isExpanded.value = !isExpanded.value
  nextTick(() => {
    computeMaxHeight()
  })
}

defineShortcuts({
  escape: () => {
    if (isExpanded.value) {
      toggleExpanded()
    }
  }
})

const handleColumnResize = (columnId, newSize) => {
  handleColumnResizeState(columnId, newSize)
}

onMounted(() => {
  computeMaxHeight()
})

useEventListener(window, 'resize', computeMaxHeight)
</script> 