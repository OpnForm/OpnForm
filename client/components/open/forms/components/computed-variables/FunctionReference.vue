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
          <!-- Sidebar with Search + Categories -->
          <div class="w-48 border-r flex flex-col">
            <!-- Search -->
            <div class="p-3 border-b">
              <UInput
                v-model="search"
                placeholder="Search functions..."
                icon="i-heroicons-magnifying-glass"
                size="sm"
              />
            </div>
            
            <!-- Category Buttons -->
            <div class="p-3 space-y-1 flex-1 overflow-y-auto">
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
                <span class="ml-1 text-xs text-gray-400">
                  ({{ getCategoryCount(category.id) }})
                </span>
              </button>
            </div>
          </div>

          <!-- Functions List -->
          <div class="flex-1 p-6 overflow-y-auto">
            <div class="max-w-3xl">
              <p v-if="filteredFunctions.length === 0" class="text-gray-500 text-center py-8">
                No functions found matching "{{ search }}"
              </p>
              <div
                v-for="func in filteredFunctions"
                :key="func.name"
                class="mb-8 pb-6 border-b last:border-b-0"
              >
                <h4 class="font-mono text-base font-semibold">
                  <span class="text-blue-600">{{ getFunctionName(func.signature) }}</span>
                  <span class="text-gray-400">{{ getFunctionArgs(func.signature) }}</span>
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
                      class="block text-sm font-mono py-0.5"
                    >
                      <template v-if="example.includes('→')">
                        <span class="text-gray-700">{{ example.split('→')[0].trim() }}</span>
                        <span class="mx-2 text-gray-400">→</span>
                        <span class="text-emerald-600 font-medium">{{ example.split('→')[1].trim() }}</span>
                      </template>
                      <template v-else>
                        <span class="text-gray-700">{{ example }}</span>
                      </template>
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
      f.description.toLowerCase().includes(term) ||
      f.signature?.toLowerCase().includes(term)
    )
  }
  
  return funcs
})

function getCategoryCount(categoryId) {
  if (categoryId === 'all') {
    return allFunctions.value.length
  }
  return allFunctions.value.filter(f => f.category === categoryId).length
}

function getFunctionName(sig) {
  if (!sig) return ''
  const index = sig.indexOf('(')
  return index > -1 ? sig.substring(0, index) : sig
}

function getFunctionArgs(sig) {
  if (!sig) return ''
  const index = sig.indexOf('(')
  return index > -1 ? sig.substring(index) : ''
}

// Reset search when modal closes
watch(isOpen, (open) => {
  if (!open) {
    search.value = ''
    activeCategory.value = 'all'
  }
})
</script>
