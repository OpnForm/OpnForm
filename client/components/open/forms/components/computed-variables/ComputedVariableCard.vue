<template>
  <div class="border border-gray-200 rounded-lg p-4 hover:border-gray-300 transition-colors">
    <div class="flex items-start justify-between">
      <div class="flex items-start gap-3 min-w-0 flex-1">
        <div class="flex-shrink-0 w-8 h-8 bg-purple-100 rounded-lg flex items-center justify-center">
          <Icon
            name="i-heroicons-variable"
            class="h-4 w-4 text-purple-600"
          />
        </div>
        <div class="min-w-0 flex-1">
          <h4 class="font-medium text-gray-900 truncate">
            {{ variable.name }}
          </h4>
          <p class="text-sm text-gray-500 mt-1 font-mono truncate">
            {{ displayFormula }}
          </p>
          <div class="flex items-center gap-2 mt-2">
            <span class="text-xs text-gray-400">Preview:</span>
            <span class="text-sm font-medium text-gray-700">
              {{ previewValue }}
            </span>
          </div>
        </div>
      </div>
      
      <UDropdown
        :items="menuItems"
        :popper="{ placement: 'bottom-end' }"
      >
        <UButton
          icon="i-heroicons-ellipsis-vertical"
          color="neutral"
          variant="ghost"
          size="sm"
        />
      </UDropdown>
    </div>
  </div>
</template>

<script setup>
import { evaluateFormula, formulaToDisplay } from '~/lib/formulas/index.js'

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

// Calculate preview value using sample data
const previewValue = computed(() => {
  try {
    // Build sample context from form fields
    const context = {}
    const fields = props.form?.properties || []
    
    for (const field of fields) {
      // Use sample values based on field type
      switch (field.type) {
        case 'number':
        case 'rating':
        case 'scale':
        case 'slider':
          context[field.id] = 10
          break
        case 'text':
        case 'email':
          context[field.id] = field.name || 'Sample'
          break
        case 'checkbox':
          context[field.id] = true
          break
        default:
          context[field.id] = field.name || 'Sample'
      }
    }

    // Add other computed variables (evaluated in order)
    const otherVars = (props.form?.computed_variables || []).filter(v => v.id !== props.variable.id)
    for (const v of otherVars) {
      try {
        context[v.id] = evaluateFormula(v.formula, context)
      } catch {
        context[v.id] = null
      }
    }

    const result = evaluateFormula(props.variable.formula, context)
    
    if (result === null || result === undefined) {
      return '—'
    }
    
    if (typeof result === 'number') {
      return Number.isInteger(result) ? result : result.toFixed(2)
    }
    
    if (typeof result === 'boolean') {
      return result ? 'true' : 'false'
    }
    
    if (typeof result === 'string' && result.length > 50) {
      return `"${result.substring(0, 50)}..."`
    }
    
    return typeof result === 'string' ? `"${result}"` : String(result)
  } catch (error) {
    return 'Error'
  }
})

const menuItems = [
  [
    {
      label: 'Edit',
      icon: 'i-heroicons-pencil-square',
      click: () => emit('edit', props.variable)
    }
  ],
  [
    {
      label: 'Delete',
      icon: 'i-heroicons-trash',
      color: 'red',
      click: () => {
        if (confirm(`Are you sure you want to delete "${props.variable.name}"?`)) {
          emit('delete', props.variable)
        }
      }
    }
  ]
]
</script>
