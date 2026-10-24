<template>
  <div>
    <UPopover v-model:open="isPopoverOpen" arrow :content="popoverContent">
      <UButton
        size="sm"
        variant="ghost"
        label="Columns"
        color="neutral"
        trailing-icon="i-lucide-chevron-down"
        class="ml-auto"
      />

      <template #content>
        <div class="w-80 p-2 flex flex-col">
          <div class="flex items-center justify-between">
            <UInput
              v-model="searchQuery"
              variant="outline"
              placeholder="Search columns..."
              icon="i-heroicons-magnifying-glass"
              size="sm"
            />
            <UButton
              size="sm"
              variant="ghost"
              color="neutral"
              label="Reset"
              @click="tableState.resetPreferences()"
            />
          </div>

          <ScrollableContainer class="mt-1">
            <template v-for="section in columnSections" :key="section.type">
              <div v-if="section.columns.length > 0" class="flex flex-col">
                <div class="flex items-center justify-between">
                  <h4 class="text-xs text-neutral-500">{{ section.title }}</h4>
                  <UButton
                    size="xs"
                    variant="link"
                    :label="section.actionLabel"
                    @click="setColumnsVisibility(section.columns, section.targetVisibility)"
                  />
                </div>

                <VueDraggable
                  :model-value="section.columns"
                  item-key="id"
                  :ghost-class="['opacity-50', 'bg-blue-50', 'rounded-md']"
                  :chosen-class="['bg-blue-100', 'rounded-md']"
                  :animation="200"
                  group="columns"
                  :data-section-type="section.type"
                  @add="handleColumnAdd"
                  @update="handleColumnUpdate"
                >
                  <TableColumnManagerRow
                    v-for="column in section.columns"
                    :key="column.id"
                    :column="column"
                    :table-state="tableState"
                    :preference="columnPreferencesMap[column.id]"
                    :visible="columnVisibilityMap[column.id]"
                    :display-name="columnDisplayName(column)"
                    :technical-name="columnTechnicalName(column)"
                  />
                </VueDraggable>
              </div>
            </template>

            <div
              v-if="showAttributionGroup"
              class="mt-2 pt-2 border-t border-neutral-200 dark:border-neutral-700"
            >
              <button
                type="button"
                class="w-full flex items-start gap-2 p-2 rounded-md text-left hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors"
                :aria-expanded="attributionGroupExpanded"
                aria-controls="attribution-column-options"
                @click="isAttributionExpanded = !isAttributionExpanded"
              >
                <UIcon name="i-lucide-chart-no-axes-combined" class="size-4 mt-0.5 text-neutral-500 shrink-0" />
                <span class="flex-1 min-w-0">
                  <span class="flex items-center gap-2">
                    <span class="text-sm font-medium">Attribution &amp; tracking</span>
                    <UBadge
                      v-if="detectedAttributionCount > 0"
                      size="xs"
                      variant="soft"
                      color="neutral"
                      :label="`${detectedAttributionCount} on this page`"
                    />
                  </span>
                  <span class="block mt-0.5 text-xs text-neutral-500 leading-4">
                    Campaign parameters captured from form URLs
                  </span>
                </span>
                <UIcon
                  name="i-lucide-chevron-down"
                  class="size-4 mt-0.5 text-neutral-500 transition-transform"
                  :class="{ 'rotate-180': attributionGroupExpanded }"
                />
              </button>

              <div v-if="attributionGroupExpanded" id="attribution-column-options" class="mt-1 pl-2">
                <div
                  v-if="visibleAttributionColumns.length > 0"
                  class="flex items-center justify-between px-2 py-1"
                >
                  <span class="text-xs text-neutral-500">
                    {{ visibleAttributionColumns.length }} shown in table
                  </span>
                  <UButton
                    size="xs"
                    variant="link"
                    color="neutral"
                    label="Hide URL parameters"
                    @click="setColumnsVisibility(visibleAttributionColumns, false)"
                  />
                </div>

                <div v-if="hiddenDetectedAttributionColumns.length > 0">
                  <div class="flex items-center justify-between px-2">
                    <h5 class="text-xs text-neutral-500">Detected on this page</h5>
                    <UButton
                      size="xs"
                      variant="link"
                      label="Show detected"
                      @click="setColumnsVisibility(hiddenDetectedAttributionColumns, true)"
                    />
                  </div>

                  <VueDraggable
                    :model-value="hiddenDetectedAttributionColumns"
                    item-key="id"
                    :ghost-class="['opacity-50', 'bg-blue-50', 'rounded-md']"
                    :chosen-class="['bg-blue-100', 'rounded-md']"
                    :animation="200"
                    group="columns"
                    data-section-type="hidden"
                    @add="handleColumnAdd"
                    @update="handleColumnUpdate"
                  >
                    <TableColumnManagerRow
                      v-for="column in hiddenDetectedAttributionColumns"
                      :key="column.id"
                      :column="column"
                      :table-state="tableState"
                      :preference="columnPreferencesMap[column.id]"
                      :visible="false"
                      :display-name="columnDisplayName(column)"
                      :technical-name="columnTechnicalName(column)"
                    />
                  </VueDraggable>
                </div>

                <p
                  v-else-if="detectedAttributionCount > 0 && !normalizedSearchQuery"
                  class="px-2 py-1 text-xs text-neutral-500"
                >
                  All detected parameters are shown in the table.
                </p>

                <div v-if="hiddenOtherAttributionColumns.length > 0" class="mt-1">
                  <button
                    type="button"
                    class="w-full flex items-center gap-2 px-2 py-1.5 rounded-md text-left hover:bg-neutral-50 dark:hover:bg-neutral-800 transition-colors"
                    :aria-expanded="otherAttributionExpanded"
                    aria-controls="other-attribution-column-options"
                    @click="isOtherAttributionExpanded = !isOtherAttributionExpanded"
                  >
                    <UIcon
                      name="i-lucide-chevron-right"
                      class="size-3.5 text-neutral-500 transition-transform"
                      :class="{ 'rotate-90': otherAttributionExpanded }"
                    />
                    <span class="flex-1 text-xs font-medium text-neutral-600 dark:text-neutral-300">
                      Other supported parameters
                    </span>
                    <span class="text-xs text-neutral-500">{{ hiddenOtherAttributionColumns.length }}</span>
                  </button>

                  <div v-if="otherAttributionExpanded" id="other-attribution-column-options">
                    <div class="flex justify-end px-2">
                      <UButton
                        size="xs"
                        variant="link"
                        label="Show all"
                        @click="setColumnsVisibility(hiddenOtherAttributionColumns, true)"
                      />
                    </div>

                    <VueDraggable
                      :model-value="hiddenOtherAttributionColumns"
                      item-key="id"
                      :ghost-class="['opacity-50', 'bg-blue-50', 'rounded-md']"
                      :chosen-class="['bg-blue-100', 'rounded-md']"
                      :animation="200"
                      group="columns"
                      data-section-type="hidden"
                      @add="handleColumnAdd"
                      @update="handleColumnUpdate"
                    >
                      <TableColumnManagerRow
                        v-for="column in hiddenOtherAttributionColumns"
                        :key="column.id"
                        :column="column"
                        :table-state="tableState"
                        :preference="columnPreferencesMap[column.id]"
                        :visible="false"
                        :display-name="columnDisplayName(column)"
                        :technical-name="columnTechnicalName(column)"
                      />
                    </VueDraggable>
                  </div>
                </div>
              </div>
            </div>

            <p
              v-if="filteredColumns.length === 0 && !showAttributionGroup"
              class="px-2 py-4 text-center text-sm text-neutral-500"
            >
              No columns found.
            </p>
          </ScrollableContainer>
          <div class="w-full h-1" />
        </div>
      </template>
    </UPopover>
  </div>
</template>

<script setup>
import { VueDraggable } from 'vue-draggable-plus'
import ScrollableContainer from '~/components/dashboard/ScrollableContainer.vue'
import TableColumnManagerRow from './TableColumnManagerRow.vue'
import {
  ATTRIBUTION_PARAMETER_LABELS,
  attributionParameterFromColumnId,
  detectedAttributionParameters,
  isColumnVisibilityTransition,
} from '~/lib/forms/submissionAttribution'

const props = defineProps({
  tableState: {
    type: Object,
    required: true,
  },
  data: {
    type: Array,
    default: () => [],
  },
  popoverContent: {
    type: Object,
    default: () => ({
      align: 'end',
      side: 'bottom',
    }),
  },
})

const isPopoverOpen = ref(false)
const searchQuery = ref('')
const isAttributionExpanded = ref(false)
const isOtherAttributionExpanded = ref(false)

const normalizedSearchQuery = computed(() => searchQuery.value.trim().toLowerCase())

const columnVisibilityMap = computed(() => {
  const map = {}
  const visibility = props.tableState.columnVisibility.value || {}
  const columns = props.tableState.orderedColumns.value || []

  columns.forEach(column => {
    map[column.id] = visibility[column.id] !== false
  })
  return map
})

const columnPreferencesMap = computed(() => {
  const map = {}
  const columns = props.tableState.orderedColumns.value || []

  columns.forEach(column => {
    map[column.id] = props.tableState.getColumnPreference(column.id)
  })
  return map
})

const columnAttributionParameter = column => attributionParameterFromColumnId(column.id)
const isAttributionColumn = column => columnAttributionParameter(column) !== null

const columnDisplayName = (column) => {
  const parameter = columnAttributionParameter(column)
  return parameter ? ATTRIBUTION_PARAMETER_LABELS[parameter] : (column.header || column.id)
}

const columnTechnicalName = (column) => columnAttributionParameter(column) || ''

const columnMatchesQuery = (column) => {
  if (!normalizedSearchQuery.value) return true

  return [column.id, column.header, columnDisplayName(column)]
    .filter(Boolean)
    .some(value => String(value).toLowerCase().includes(normalizedSearchQuery.value))
}

const filteredColumns = computed(() => {
  const columns = props.tableState.orderedColumns.value || []
  if (!Array.isArray(columns)) return []

  return columns.filter(column => column.id !== 'actions' && columnMatchesQuery(column))
})

const attributionColumns = computed(() => {
  const columns = props.tableState.orderedColumns.value || []
  if (!Array.isArray(columns)) return []

  return columns.filter(column => column.id !== 'actions' && isAttributionColumn(column))
})

const visibleColumns = computed(() => filteredColumns.value.filter(column => (
  columnVisibilityMap.value[column.id] !== false
)))

const hiddenColumns = computed(() => filteredColumns.value.filter(column => (
  columnVisibilityMap.value[column.id] === false && !isAttributionColumn(column)
)))

const hiddenAttributionColumns = computed(() => filteredColumns.value.filter(column => (
  columnVisibilityMap.value[column.id] === false && isAttributionColumn(column)
)))

const visibleAttributionColumns = computed(() => attributionColumns.value.filter(column => (
  columnVisibilityMap.value[column.id] !== false
)))

const detectedAttributionParameterSet = computed(() => new Set(detectedAttributionParameters(props.data)))
const detectedAttributionCount = computed(() => detectedAttributionParameterSet.value.size)

const hiddenDetectedAttributionColumns = computed(() => hiddenAttributionColumns.value.filter(column => (
  detectedAttributionParameterSet.value.has(columnAttributionParameter(column))
)))

const hiddenOtherAttributionColumns = computed(() => hiddenAttributionColumns.value.filter(column => (
  !detectedAttributionParameterSet.value.has(columnAttributionParameter(column))
)))

const showAttributionGroup = computed(() => filteredColumns.value.some(isAttributionColumn))
const attributionGroupExpanded = computed(() => isAttributionExpanded.value || normalizedSearchQuery.value.length > 0)
const otherAttributionExpanded = computed(() => isOtherAttributionExpanded.value || normalizedSearchQuery.value.length > 0)

const columnSections = computed(() => [
  {
    type: 'visible',
    title: 'Shown in table',
    actionLabel: 'Hide All',
    targetVisibility: false,
    columns: visibleColumns.value,
  },
  {
    type: 'hidden',
    title: 'Hidden in table',
    actionLabel: 'Show All',
    targetVisibility: true,
    columns: hiddenColumns.value,
  },
])

const handleColumnAdd = async (evt) => {
  const column = evt.data || evt.clonedData
  if (!column) return

  const sourceSectionType = evt.from?.dataset?.sectionType
  const targetSectionType = evt.to?.dataset?.sectionType
  if (!isColumnVisibilityTransition(sourceSectionType, targetSectionType)) return

  const isVisibleTarget = targetSectionType === 'visible'
  if (isVisibleTarget) {
    props.tableState.toggleColumnVisibility(column.id)
    await nextTick()
    props.tableState.setColumnOrder(column.id, evt.newIndex)
  } else {
    props.tableState.toggleColumnVisibility(column.id)
  }
}

const handleColumnUpdate = (evt) => {
  const column = evt.data
  if (!column) return
  props.tableState.setColumnOrder(column.id, evt.newIndex)
}

const setColumnsVisibility = (columns, targetVisibility) => {
  columns.forEach(column => {
    if ((columnVisibilityMap.value[column.id] !== false) !== targetVisibility) {
      props.tableState.toggleColumnVisibility(column.id)
    }
  })
}
</script>
