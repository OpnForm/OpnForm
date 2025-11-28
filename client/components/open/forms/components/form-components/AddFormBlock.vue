<template>
  <div
    class="pb-16"
    @keydown="handleKeydown"
    tabindex="-1"
  >
    <div class="p-2 border-b border-neutral-300 sticky top-0 z-10 bg-white">
      <div class="flex items-center">
        <UButton
          size="sm"
          color="neutral"
          icon="i-heroicons-x-mark-20-solid"
          variant="outlint"
          @click="closeSidebar"
        />
        <div class="font-medium inline ml-2 flex-grow truncate">
          Add Block
        </div>
        <AiFieldGenerator
          class="py-2 px-4"
        />
      </div>
    </div>

    <div class="py-2 px-4">
      <UInput
        ref="searchInput"
        v-model="searchTerm"
        autofocus
        variant="outline"
        class="w-full"
        placeholder="Search for a block..."
        icon="i-heroicons-magnifying-glass-solid"
        :ui="{ trailing: 'pe-1' }"
        @keydown.down.prevent="handleKeydown"
        @keydown.up.prevent="handleKeydown"
        @keydown.enter.prevent="handleKeydown"
        @keydown.esc="handleKeydown"
      >
        <template v-if="searchTerm?.length" #trailing>
          <UButton
            color="neutral"
            variant="link"
            size="sm"
            icon="i-lucide-circle-x"
            aria-label="Clear"
            title="Clear"
            @click="searchTerm = ''"
          />
        </template>
      </UInput>
    </div>

    <div class="py-2 px-4">
      <p class="text-neutral-500 text-xs font-medium my-2">
        Input Blocks
      </p>
      <VueDraggable
        :model-value="filteredInputBlocks"
        :group="{ name: 'form-elements', pull: 'clone', put: false }"
        class="flex flex-col -mx-2"
        :sort="false"
        :clone="handleInputClone"
        ghost-class="ghost-item"
        item-key="id"
        @start="workingFormStore.draggingNewBlock=true"
        @end="workingFormStore.draggingNewBlock=false"
      >
        <template #default>
          <div
            v-for="(element, index) in filteredInputBlocks"
            :key="element.id || element.name"
            :ref="(el) => setBlockRef(el, index)"
            :data-block-index="index"
            :class="[
              'flex rounded-md items-center gap-2 p-2 group',
              selectedIndex === index ? 'bg-blue-100 dark:bg-blue-900' : 'hover:bg-neutral-50'
            ]"
            role="button"
            :tabindex="selectedIndex === index ? 0 : -1"
            @click.prevent="addBlock(element.name)"
            @keydown.enter.prevent="addBlock(element.name)"
          >
            <BlockTypeIcon :type="element.name" />
            <p class="w-full text-sm text-neutral-500">
              {{ element.title }}
            </p>
            <Icon
              v-if="element.auth_required && !authenticated"
              name="heroicons:lock-closed"
              class="text-neutral-400 w-4 h-4"
            />
          </div>
          <p
            v-if="searchTerm && filteredInputBlocks.length === 0"
            class="text-neutral-400 text-xs px-2 py-1"
          >
            No input blocks match your search.
          </p>
        </template>
      </VueDraggable>
    </div>
    <div class="px-4 border-t mb-4">
      <p class="text-neutral-500 text-xs font-medium my-2">
        Layout Blocks
      </p>
      <VueDraggable
        :model-value="filteredLayoutBlocks"
        :group="{ name: 'form-elements', pull: 'clone', put: false }"
        class="flex flex-col -mx-2"
        :sort="false"
        :clone="handleInputClone"
        ghost-class="ghost-item"
        item-key="id"
        @start="workingFormStore.draggingNewBlock=true"
        @end="workingFormStore.draggingNewBlock=false"
      >
        <template #default>
          <div
            v-for="(element, index) in filteredLayoutBlocks"
            :key="element.id || element.name"
            :ref="(el) => setBlockRef(el, filteredInputBlocks.length + index)"
            :data-block-index="filteredInputBlocks.length + index"
            :class="[
              'flex rounded-md items-center gap-2 p-2',
              selectedIndex === (filteredInputBlocks.length + index) ? 'bg-blue-100 dark:bg-blue-900' : 'hover:bg-neutral-50'
            ]"
            role="button"
            :tabindex="selectedIndex === (filteredInputBlocks.length + index) ? 0 : -1"
            @click.prevent="addBlock(element.name)"
            @keydown.enter.prevent="addBlock(element.name)"
          >
            <BlockTypeIcon :type="element.name" />
            <p class="w-full text-sm text-neutral-500">
              {{ element.title }}
            </p>
            <Icon
              v-if="element.auth_required && !authenticated"
              name="heroicons:lock-closed"
              class="text-neutral-400 w-4 h-4"
            />
          </div>
          <p
            v-if="searchTerm && filteredLayoutBlocks.length === 0"
            class="text-neutral-400 text-xs px-2 py-1"
          >
            No layout blocks match your search.
          </p>
        </template>
      </VueDraggable>
    </div>
  </div>
</template>

<script setup>
import { VueDraggable } from 'vue-draggable-plus'
import blocksTypes from '~/data/blocks_types.json'
import BlockTypeIcon from '../BlockTypeIcon.vue'
import AiFieldGenerator from './components/AiFieldGenerator.vue'
import Fuse from 'fuse.js'

const workingFormStore = useWorkingFormStore()
const { isAuthenticated: authenticated } = useIsAuthenticated()

const formStyle = computed(() => workingFormStore.content?.presentation_style || 'classic')

const allowedBlocks = computed(() => {
  const all = Object.values(blocksTypes)
  return all.filter(block => {
    const modes = block.available_in || ['classic', 'focused']
    return modes.includes(formStyle.value)
  })
})

const searchTerm = ref('')
const normalizedSearch = computed(() => searchTerm.value.trim().toLowerCase())

const fuseOptions = {
  keys: ['title', 'name'],
  threshold: 0.3,
  ignoreLocation: true,
  includeScore: false,
}

// Create a single Fuse instance that's reused
const fuseInstance = computed(() => {
  return new Fuse(allowedBlocks.value, fuseOptions)
})

// Search through all blocks once
const filteredBlocks = computed(() => {
  if (!normalizedSearch.value) return allowedBlocks.value
  return fuseInstance.value.search(normalizedSearch.value).map(r => r.item)
})

// Split filtered results into input and layout blocks
const filteredInputBlocks = computed(() => {
  return filteredBlocks.value.filter(block => !block.name.startsWith('nf-'))
})

const filteredLayoutBlocks = computed(() => {
  return filteredBlocks.value.filter(block => block.name.startsWith('nf-'))
})

// Combined flat list of all blocks for keyboard navigation
const allFilteredBlocks = computed(() => {
  return [...filteredInputBlocks.value, ...filteredLayoutBlocks.value]
})

const selectedIndex = ref(-1)
const blockRefsMap = new Map()
const searchInput = ref(null)

// Reset selection when search changes
watch([filteredInputBlocks, filteredLayoutBlocks], () => {
  selectedIndex.value = -1
  blockRefsMap.clear()
})

// Set block refs for scrolling
const setBlockRef = (el, index) => {
  if (el) {
    blockRefsMap.set(index, el)
  } else {
    blockRefsMap.delete(index)
  }
}

// Scroll selected block into view
const scrollToSelected = () => {
  nextTick(() => {
    if (selectedIndex.value >= 0) {
      // Try to use ref map first, fallback to querySelector
      const element = blockRefsMap.get(selectedIndex.value) || 
        document.querySelector(`[data-block-index="${selectedIndex.value}"]`)
      
      if (element) {
        element.scrollIntoView({
          behavior: 'smooth',
          block: 'nearest'
        })
      }
    }
  })
}

// Handle keyboard navigation
const handleKeydown = (event) => {
  const totalBlocks = allFilteredBlocks.value.length
  
  if (totalBlocks === 0) return

  // Only handle arrow keys, Enter, and Escape
  if (!['ArrowDown', 'ArrowUp', 'Enter', 'Escape'].includes(event.key)) {
    return
  }

  event.preventDefault()
  event.stopPropagation()

  switch (event.key) {
    case 'ArrowDown':
      // If starting from -1, go to 0, otherwise increment
      selectedIndex.value = selectedIndex.value < totalBlocks - 1 
        ? selectedIndex.value + 1 
        : 0
      scrollToSelected()
      break
      
    case 'ArrowUp':
      // If at 0 or -1, wrap to last item, otherwise decrement
      selectedIndex.value = selectedIndex.value > 0 
        ? selectedIndex.value - 1 
        : totalBlocks - 1
      scrollToSelected()
      break
      
    case 'Enter':
      if (selectedIndex.value >= 0 && selectedIndex.value < totalBlocks.length) {
        const selectedBlock = allFilteredBlocks.value[selectedIndex.value]
        if (selectedBlock) {
          addBlock(selectedBlock.name)
        }
      } else if (totalBlocks > 0) {
        // If no selection, select and add the first block
        selectedIndex.value = 0
        const firstBlock = allFilteredBlocks.value[0]
        if (firstBlock) {
          addBlock(firstBlock.name)
        }
      }
      break
      
    case 'Escape':
      if (searchTerm.value) {
        searchTerm.value = ''
        selectedIndex.value = -1
        nextTick(() => {
          const inputEl = searchInput.value?.$el?.querySelector('input') || searchInput.value?.$el
          if (inputEl) {
            inputEl.focus()
          }
        })
      }
      break
  }
}

const closeSidebar = () => {
  workingFormStore.closeAddFieldSidebar()
}

const addBlock = (type) => {
  workingFormStore.addBlock(type)
}

const handleInputClone = (item) => {
  return item.name
}

workingFormStore.resetBlockForm()
</script>

<style lang="scss" scoped>
.ghost-item {
  @apply bg-blue-100 dark:bg-blue-900 rounded-md w-full col-span-full;
}
</style>
