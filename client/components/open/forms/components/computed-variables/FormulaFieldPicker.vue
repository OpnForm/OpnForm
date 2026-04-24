<template>
  <div class="w-64 max-h-80 overflow-y-auto">
    <!-- Search -->
    <div class="p-2 border-b">
      <UInput
        v-model="search"
        placeholder="Search fields..."
        icon="i-heroicons-magnifying-glass"
        size="sm"
      />
    </div>

    <!-- Fields Section -->
    <div v-if="filteredFields.length > 0" class="p-2">
      <div class="text-xs font-medium text-gray-500 uppercase mb-2">
        Form Fields
      </div>
      <div class="space-y-1">
        <button
          v-for="field in filteredFields"
          :key="field.id"
          class="w-full flex items-center gap-2 p-2 rounded-md hover:bg-gray-100 text-left"
          @click="selectField(field)"
        >
          <BlockTypeIcon
            :type="field.type"
            class="h-4 w-4 text-gray-500"
          />
          <span class="text-sm text-gray-700 truncate">{{ field.name }}</span>
        </button>
      </div>
    </div>

    <!-- Variables Section -->
    <div v-if="filteredVariables.length > 0" class="p-2 border-t">
      <div class="text-xs font-medium text-gray-500 uppercase mb-2">
        Variables
      </div>
      <div class="space-y-1">
        <button
          v-for="variable in filteredVariables"
          :key="variable.id"
          class="w-full flex items-center gap-2 p-2 rounded-md hover:bg-gray-100 text-left"
          @click="selectField(variable)"
        >
          <Icon
            name="i-heroicons-variable"
            class="h-4 w-4 text-purple-500"
          />
          <span class="text-sm text-gray-700 truncate">{{ variable.name }}</span>
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div
      v-if="filteredFields.length === 0 && filteredVariables.length === 0"
      class="p-4 text-center text-sm text-gray-500"
    >
      No fields found
    </div>
  </div>
</template>

<script setup>
import BlockTypeIcon from '~/components/open/forms/components/BlockTypeIcon.vue'

const props = defineProps({
  fields: {
    type: Array,
    default: () => []
  },
  variables: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['select'])

const search = ref('')

const filteredFields = computed(() => {
  if (!search.value) return props.fields
  const term = search.value.toLowerCase()
  return props.fields.filter(f => f.name.toLowerCase().includes(term))
})

const filteredVariables = computed(() => {
  if (!search.value) return props.variables
  const term = search.value.toLowerCase()
  return props.variables.filter(v => v.name.toLowerCase().includes(term))
})

function selectField(field) {
  emit('select', field)
}
</script>
