<template>
  <UModal
    v-model:open="isOpen"
    fullscreen
  >
    <template #content>
      <div class="flex flex-col h-full">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b">
          <h3 class="text-lg font-semibold">Function Reference</h3>
          <UButton
            icon="i-heroicons-x-mark"
            color="neutral"
            variant="ghost"
            size="sm"
            @click="isOpen = false"
          />
        </div>
        
        <!-- Content -->
        <div class="flex flex-1 overflow-hidden">
          <!-- Category Sidebar -->
          <div class="w-40 border-r p-4 space-y-1">
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
          <div class="flex-1 p-6 overflow-y-auto">
            <div class="max-w-3xl">
              <div
                v-for="func in filteredFunctions"
                :key="func.name"
                class="mb-8 pb-6 border-b last:border-b-0"
              >
                <h4 class="font-mono text-base font-semibold text-blue-600">
                  {{ func.signature }}
                </h4>
                <p class="text-sm text-gray-600 mt-2">
                  {{ func.description }}
                </p>
                <div
                  v-if="func.examples && func.examples.length > 0"
                  class="mt-3"
                >
                  <div class="text-xs font-medium text-gray-500 mb-2">Examples:</div>
                  <div class="bg-gray-50 rounded-md p-3 space-y-1">
                    <code
                      v-for="(example, i) in func.examples"
                      :key="i"
                      class="block text-sm text-gray-700 font-mono"
                    >
                      {{ example }}
                    </code>
                  </div>
                </div>
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
