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
          <!-- Header -->
          <div class="flex items-center justify-between">
            <UInput
              variant="outline"
              placeholder="Search columns..."
              icon="i-heroicons-magnifying-glass"
              size="sm"
              v-model="searchQuery"
            />
            <div class="flex items-center gap-1">
            <UButton
              size="sm"
              variant="ghost"
              color="neutral"
              label="Reset"
              @click="tableState.resetPreferences()"
            />
          </div>
          </div>

          <!-- Column Sections -->
          <ScrollableContainer class="mt-1">
            <template v-for="section in columnSections" :key="section.type">
            <div 
              v-if="section.columns.length > 0"
              class="flex flex-col"
            >
              <div class="flex items-center justify-between">
                <h4 class="text-xs text-neutral-500">{{ section.title }}</h4>
                <UButton
                  size="xs"
                  variant="link"
                  :label="section.actionLabel"
                  @click="toggleAllColumns(section.targetVisibility)"
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
                  <template #default>
                    <div v-for="column in section.columns" :key="column.id" class="group">
                      <div class="flex items-center gap-1 p-2 rounded-md hover:bg-neutral-50 transition-colors">
                        <!-- Drag Handle -->
                        <div class="w-4 h-4 flex items-center justify-center opacity-60 group-hover:opacity-100 transition-opacity">
                          <UIcon name="clarity:drag-handle-line" class="h-6 w-6 -ml-1 shrink-0 text-neutral-400" />
                        </div>

                        <!-- Column Icon -->
                        <div class="w-4 h-4 flex items-center justify-center">
                          <BlockTypeIcon 
                            v-if="column.type"
                            :type="column.type"
                            bg-class="bg-transparent"
                            text-class="text-neutral-600"
                            class="flex-shrink-0"
                          />
                        </div>

                        <!-- Column Name with Tooltip -->
                        <UTooltip :text="column.header || column.id" :content="{ align: 'start' }">
                          <span class="flex-1 text-sm truncate">
                            {{ column.header || column.id }}
                          </span>
                        </UTooltip>

                        <!-- Removed Indicator -->
                        <UTooltip v-if="column.isRemoved" text="Column was removed from form" :content="{ align: 'end' }">
                          <UIcon name="i-heroicons-trash" class="w-3 h-3 text-neutral-400" />
                        </UTooltip>

                        <!-- Column Actions -->
                        <div class="flex items-center gap-1">
                          <!-- Pin Toggle -->
                          <UTooltip :text="getPinTooltip(column.id)">
                            <UButton
                              size="xs"
                              :variant="columnPreferencesMap[column.id]?.pinned ? 'soft' : 'ghost'"
                              :icon="getPinIcon(column.id)"
                              :color="columnPreferencesMap[column.id]?.pinned ? 'primary' : 'neutral'"
                              @click.prevent="tableState.toggleColumnPin(column.id)"
                            />
                          </UTooltip>

                          <!-- Wrap Toggle -->
                          <UTooltip :text="columnPreferencesMap[column.id]?.wrapped ? 'Disable text wrapping' : 'Enable text wrapping'">
                            <UButton
                              size="xs"
                              :variant="columnPreferencesMap[column.id]?.wrapped ? 'soft' : 'ghost'"
                              icon="i-ic-baseline-wrap-text"
                              :color="columnPreferencesMap[column.id]?.wrapped ? 'primary' : 'neutral'"
                              @click.prevent="tableState.toggleColumnWrapping(column.id)"
                            />
                          </UTooltip>

                          <!-- Visibility Toggle Button -->
                          <UTooltip :text="columnVisibilityMap[column.id] ? 'Hide' : 'Show'">
                            <UButton
                              size="xs"
                              variant="ghost"
                              color="neutral"
                              :icon="columnVisibilityMap[column.id] ? 'i-heroicons-eye-solid' : 'i-heroicons-eye-slash-solid'"
                              @click.prevent="props.tableState.toggleColumnVisibility(column.id)"
                            />
                          </UTooltip>
                        </div>
                      </div>
                    </div>
                  </template>
                </VueDraggable>
            </div>
          </template>
          </ScrollableContainer>
          <div class="w-full h-1"></div>
        </div>
      </template>
    </UPopover>
  </div>
</template>

<script setup>
import { VueDraggable } from 'vue-draggable-plus'
import BlockTypeIcon from '~/components/open/forms/components/BlockTypeIcon.vue'
import ScrollableContainer from '~/components/dashboard/ScrollableContainer.vue'

const props = defineProps({
  tableState: {
    type: Object,
    required: true,
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

// Computed maps for better performance - avoid repeated function calls in templates
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



// Filter columns based on search query and maintain order
const filteredColumns = computed(() => {
  const columns = props.tableState.orderedColumns.value || []
  if (!Array.isArray(columns)) return []
  
  const query = searchQuery.value.toLowerCase()
  return columns.filter(column => 
    column.id !== 'actions' && 
    (column.header || column.id).toLowerCase().includes(query)
  )
})

// Computed visible columns (reactive to table state) - now using the map
const visibleColumns = computed(() => {
  const visibilityMap = columnVisibilityMap.value
  return filteredColumns.value.filter(column => visibilityMap[column.id] !== false)
})

// Computed hidden columns (reactive to table state) - now using the map
const hiddenColumns = computed(() => {
  const visibilityMap = columnVisibilityMap.value
  return filteredColumns.value.filter(column => visibilityMap[column.id] === false)
})

// Column sections configuration (now fully reactive)
const columnSections = computed(() => {
  return [
    {
      type: 'visible',
      title: 'Shown in table',
      actionLabel: 'Hide All',
      targetVisibility: false,
      columns: visibleColumns.value
    },
    {
      type: 'hidden',
      title: 'Hidden in table',
      actionLabel: 'Show All',
      targetVisibility: true,
      columns: hiddenColumns.value
    }
  ]
})

// Handle drag end event
const handleColumnAdd = async (evt) => {
  const column = evt.data || evt.clonedData
  if (!column) return
  const isVisibleTarget = evt.to?.dataset?.sectionType === 'visible'
  if (isVisibleTarget) {
    props.tableState.toggleColumnVisibility(column.id)
    await nextTick()
    props.tableState.setColumnOrder(column.id, evt.newIndex)
  } else {
    // Moved into hidden
    props.tableState.toggleColumnVisibility(column.id)
  }
}

const handleColumnUpdate = (evt) => {
  const column = evt.data
  if (!column) return
  props.tableState.setColumnOrder(column.id, evt.newIndex)
}

// Get pin icon - now using computed map
const getPinIcon = (columnId) => {
  const pref = columnPreferencesMap.value[columnId]
  return pref?.pinned === 'left' ? 'i-ic-baseline-push-pin' : 'i-ic-baseline-push-pin'
}

// Get pin tooltip - now using computed map
const getPinTooltip = (columnId) => {
  const pref = columnPreferencesMap.value[columnId]
  return pref?.pinned === 'left' ? 'Unpin column' : 'Pin column to left'
}

// Toggle all columns visibility - now using computed map
const toggleAllColumns = (targetVisibility) => {
  const columns = props.tableState.orderedColumns.value || []
  const visibilityMap = columnVisibilityMap.value
  
  columns
    .filter(column => column.id !== 'actions')
    .forEach(column => {
      if ((visibilityMap[column.id] !== false) !== targetVisibility) {
        props.tableState.toggleColumnVisibility(column.id)
      }
    })
}
</script>

<style scoped>
/* Ensure drag cursor is applied to the entire row */
.group {
  cursor: grab;
}

.group:active {
  cursor: grabbing;
}
</style>
