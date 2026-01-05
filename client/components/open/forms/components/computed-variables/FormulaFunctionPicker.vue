<template>
  <div class="w-80 max-h-96 flex flex-col">
    <!-- Sticky Header: Search + Category Tabs -->
    <div class="sticky top-0 bg-white z-10 border-b">
      <!-- Search -->
      <div class="p-2">
        <UInput
          v-model="search"
          placeholder="Search functions..."
          icon="i-heroicons-magnifying-glass"
          size="sm"
        />
      </div>
      
      <!-- Category Tabs -->
      <div class="flex px-2">
        <button
          v-for="category in categories"
          :key="category.id"
          class="px-3 py-2 text-sm font-medium border-b-2 -mb-px"
          :class="activeCategory === category.id 
            ? 'border-blue-500 text-blue-600' 
            : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="activeCategory = category.id"
        >
          {{ category.label }}
        </button>
      </div>
    </div>

    <!-- Functions List -->
    <div class="p-2 space-y-1 overflow-y-auto flex-1">
      <div v-if="filteredFunctions.length === 0" class="text-center text-sm text-gray-500 py-4">
        No functions found
      </div>
      <button
        v-for="func in filteredFunctions"
        :key="func.name"
        class="w-full text-left p-2 rounded-md hover:bg-gray-100"
        @click="selectFunction(func)"
      >
        <div class="font-mono text-sm text-blue-600">
          {{ func.signature }}
        </div>
        <div class="text-xs text-gray-500 mt-0.5">
          {{ func.description }}
        </div>
      </button>
    </div>
  </div>
</template>

<script setup>
import { functionMeta } from '~/lib/formulas/index.js'

const emit = defineEmits(['select'])

const categories = [
  { id: 'all', label: 'All' },
  { id: 'math', label: 'Math' },
  { id: 'text', label: 'Text' },
  { id: 'logic', label: 'Logic' },
  { id: 'array', label: 'Array' }
]

const activeCategory = ref('all')
const search = ref('')

const allFunctions = computed(() => {
  return Object.entries(functionMeta).map(([name, meta]) => ({
    name,
    ...meta
  }))
})

const filteredFunctions = computed(() => {
  let funcs = allFunctions.value
  
  // Filter by category
  if (activeCategory.value !== 'all') {
    funcs = funcs.filter(f => f.category === activeCategory.value)
  }
  
  // Filter by search
  if (search.value) {
    const term = search.value.toLowerCase()
    funcs = funcs.filter(f => 
      f.name.toLowerCase().includes(term) || 
      f.description.toLowerCase().includes(term)
    )
  }
  
  return funcs
})

function selectFunction(func) {
  emit('select', func)
}
</script>
