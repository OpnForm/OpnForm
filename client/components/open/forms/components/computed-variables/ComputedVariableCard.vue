<template>
  <div class="border border-gray-200 rounded-lg p-3 hover:border-gray-300 transition-colors">
    <div class="flex items-center gap-3">
      <!-- Icon -->
      <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
        <Icon
          name="i-heroicons-variable"
          class="h-4 w-4 text-purple-600"
        />
      </div>
      
      <!-- Name (truncated) -->
      <div class="min-w-0 flex-1">
        <h4 class="font-medium text-gray-900 truncate" :title="variable.name">
          {{ variable.name }}
        </h4>
        <p class="text-xs text-gray-500 font-mono truncate" :title="displayFormula">
          {{ displayFormula }}
        </p>
      </div>
      
      <!-- Actions (aligned right) -->
      <UDropdownMenu
        :items="menuItems"
        class="flex-shrink-0"
      >
        <UButton
          icon="i-heroicons-ellipsis-vertical"
          color="neutral"
          variant="ghost"
          size="sm"
        />
      </UDropdownMenu>
    </div>
  </div>
</template>

<script setup>
import { formulaToDisplay } from '~/lib/formulas/index.js'

const props = defineProps({
  variable: {
    type: Object,
    required: true
  },
  form: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['edit', 'delete'])

// Convert formula to display format (with field names instead of IDs)
const displayFormula = computed(() => {
  const fields = props.form?.properties || []
  const variables = (props.form?.computed_variables || []).filter(v => v.id !== props.variable.id)
  return formulaToDisplay(props.variable.formula, fields, variables)
})

const menuItems = [
  [
    {
      label: 'Edit',
      icon: 'i-heroicons-pencil-square',
      onSelect: () => emit('edit', props.variable)
    }
  ],
  [
    {
      label: 'Delete',
      icon: 'i-heroicons-trash',
      color: 'error',
      onSelect: () => {
        if (confirm(`Are you sure you want to delete "${props.variable.name}"?`)) {
          emit('delete', props.variable)
        }
      }
    }
  ]
]
</script>
