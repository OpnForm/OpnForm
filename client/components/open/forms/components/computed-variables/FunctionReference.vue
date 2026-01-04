<template>
  <UModal
    v-model:open="isOpen"
    title="Function Reference"
    :ui="{ width: 'sm:max-w-2xl' }"
  >
    <template #body>
      <div class="flex h-96">
        <!-- Category Sidebar -->
        <div class="w-32 border-r pr-2 space-y-1">
          <button
            v-for="category in categories"
            :key="category.id"
            class="w-full text-left px-3 py-2 rounded-md text-sm"
            :class="activeCategory === category.id 
              ? 'bg-blue-50 text-blue-700 font-medium' 
              : 'text-gray-600 hover:bg-gray-50'"
            @click="activeCategory = category.id"
          >
            {{ category.label }}
          </button>
        </div>

        <!-- Functions List -->
        <div class="flex-1 pl-4 overflow-y-auto">
          <div
            v-for="func in filteredFunctions"
            :key="func.name"
            class="mb-6"
          >
            <h4 class="font-mono text-sm font-semibold text-gray-900">
              {{ func.signature }}
            </h4>
            <p class="text-sm text-gray-600 mt-1">
              {{ func.description }}
            </p>
            <div
              v-if="func.examples && func.examples.length > 0"
              class="mt-2"
            >
              <div class="text-xs font-medium text-gray-500 mb-1">Examples:</div>
              <div class="bg-gray-50 rounded-md p-2 space-y-1">
                <code
                  v-for="(example, i) in func.examples"
                  :key="i"
                  class="block text-xs text-gray-700"
                >
                  {{ example }}
                </code>
              </div>
            </div>
          </div>
        </div>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import { functionMeta } from '~/lib/formulas/index.js'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update:modelValue'])

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

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
</script>
