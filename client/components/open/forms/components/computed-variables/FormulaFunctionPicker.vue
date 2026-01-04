<template>
  <div class="w-80 max-h-96 overflow-y-auto">
    <!-- Category Tabs -->
    <div class="flex border-b">
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

    <!-- Functions List -->
    <div class="p-2 space-y-1">
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
import { functionMeta, getFunctionsByCategory } from '~/lib/formulas/index.js'

const emit = defineEmits(['select'])

const categories = [
  { id: 'all', label: 'All' },
  { id: 'math', label: 'Math' },
  { id: 'text', label: 'Text' },
  { id: 'logic', label: 'Logic' }
]

const activeCategory = ref('all')

const allFunctions = computed(() => {
  return Object.entries(functionMeta).map(([name, meta]) => ({
    name,
    ...meta
  }))
})

const filteredFunctions = computed(() => {
  if (activeCategory.value === 'all') {
    return allFunctions.value
  }
  return allFunctions.value.filter(f => f.category === activeCategory.value)
})

function selectFunction(func) {
  emit('select', func)
}
</script>
