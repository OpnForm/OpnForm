<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ width: 'sm:max-w-xl' }"
  >
    <template #content>
      <div class="p-6">
        <!-- Header -->
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-lg font-semibold">
            {{ isEditing ? 'Edit Variable' : 'Create Variable' }}
          </h3>
          <UButton
            icon="i-heroicons-x-mark"
            color="neutral"
            variant="ghost"
            size="sm"
            @click="close"
          />
        </div>
        
        <div class="space-y-4">
          <!-- Name Input -->
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Name <span class="text-red-500">*</span>
            </label>
            <UInput
              v-model="localVariable.name"
              placeholder="e.g., Total Price"
              :color="errors.name ? 'error' : undefined"
            />
            <p
              v-if="errors.name"
              class="mt-1 text-sm text-red-500"
            >
              {{ errors.name }}
            </p>
          </div>

          <!-- Formula Editor -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-sm font-medium text-gray-700">
                Formula <span class="text-red-500">*</span>
              </label>
              <UButton
                size="xs"
                color="neutral"
                variant="ghost"
                icon="i-heroicons-question-mark-circle"
                @click="showReference = true"
              >
                Help
              </UButton>
            </div>
            
            <FormulaEditor
              v-model="localVariable.formula"
              :form="form"
              :current-variable-id="localVariable.id"
              :other-variables="otherVariables"
              @validation="handleValidation"
            />
            
            <p
              v-if="errors.formula"
              class="mt-1 text-sm text-red-500"
            >
              {{ errors.formula }}
            </p>
          </div>

          <!-- Validation Status -->
          <div
            v-if="localVariable.formula"
            class="p-3 rounded-lg"
            :class="validationResult.valid ? 'bg-green-50' : 'bg-red-50'"
          >
            <div class="flex items-center gap-2">
              <Icon
                :name="validationResult.valid ? 'i-heroicons-check-circle' : 'i-heroicons-exclamation-circle'"
                :class="validationResult.valid ? 'text-green-500' : 'text-red-500'"
                class="h-5 w-5"
              />
              <span
                class="text-sm"
                :class="validationResult.valid ? 'text-green-700' : 'text-red-700'"
              >
                {{ validationResult.valid ? 'Valid formula' : validationResult.errors[0]?.message }}
              </span>
              <span
                v-if="validationResult.valid && previewValue !== '—'"
                class="ml-auto text-sm font-medium text-green-700"
              >
                Preview: {{ previewValue }}
              </span>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-3 mt-6 pt-4 border-t">
          <UButton
            color="neutral"
            variant="outline"
            @click="close"
          >
            Cancel
          </UButton>
          <UButton
            color="primary"
            :disabled="!canSave"
            @click="save"
          >
            {{ isEditing ? 'Save Changes' : 'Create Variable' }}
          </UButton>
        </div>
      </div>
    </template>
  </UModal>

  <!-- Function Reference Modal -->
  <FunctionReference
    v-model="showReference"
  />
</template>

<script setup>
import FormulaEditor from './FormulaEditor.vue'
import FunctionReference from './FunctionReference.vue'
import { validateFormula, evaluateFormula } from '~/lib/formulas/index.js'

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  variable: {
    type: Object,
    default: null
  },
  form: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['update:modelValue', 'save'])

const isOpen = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
})

const isEditing = computed(() => !!props.variable?.id)

const showReference = ref(false)

const defaultVariable = {
  id: null,
  name: '',
  formula: '',
  result_type: 'auto'
}

const localVariable = ref({ ...defaultVariable })
const errors = ref({})
const validationResult = ref({ valid: true, errors: [] })

// Other computed variables (excluding current one being edited)
const otherVariables = computed(() => {
  const all = props.form?.computed_variables || []
  return all.filter(v => v.id !== localVariable.value.id)
})

// Watch for variable prop changes
watch(() => props.variable, (newVal) => {
  if (newVal) {
    localVariable.value = { ...newVal }
  } else {
    localVariable.value = { ...defaultVariable }
  }
  errors.value = {}
  validationResult.value = { valid: true, errors: [] }
}, { immediate: true })

// Watch for modal opening
watch(isOpen, (newVal) => {
  if (newVal) {
    if (props.variable) {
      localVariable.value = { ...props.variable }
    } else {
      localVariable.value = { ...defaultVariable }
    }
    errors.value = {}
  }
})

// Handle validation from formula editor
function handleValidation(result) {
  validationResult.value = result
}

// Calculate preview value
const previewValue = computed(() => {
  if (!validationResult.value.valid || !localVariable.value.formula) {
    return '—'
  }

  try {
    // Build sample context
    const context = {}
    const fields = props.form?.properties || []
    
    for (const field of fields) {
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

    // Add other computed variables
    for (const v of otherVariables.value) {
      try {
        context[v.id] = evaluateFormula(v.formula, context)
      } catch {
        context[v.id] = null
      }
    }

    const result = evaluateFormula(localVariable.value.formula, context)
    
    if (result === null || result === undefined) {
      return '—'
    }
    
    if (typeof result === 'number') {
      return Number.isInteger(result) ? result : result.toFixed(2)
    }
    
    if (typeof result === 'string' && result.length > 30) {
      return `"${result.substring(0, 30)}..."`
    }
    
    return typeof result === 'string' ? `"${result}"` : String(result)
  } catch {
    return 'Error'
  }
})

const canSave = computed(() => {
  return localVariable.value.name?.trim() && 
         localVariable.value.formula?.trim() && 
         validationResult.value.valid
})

function validate() {
  errors.value = {}
  
  if (!localVariable.value.name?.trim()) {
    errors.value.name = 'Name is required'
  }
  
  if (!localVariable.value.formula?.trim()) {
    errors.value.formula = 'Formula is required'
  } else if (!validationResult.value.valid) {
    errors.value.formula = validationResult.value.errors[0]?.message || 'Invalid formula'
  }
  
  return Object.keys(errors.value).length === 0
}

function save() {
  if (!validate()) return
  
  emit('save', { ...localVariable.value })
}

function close() {
  isOpen.value = false
}
</script>
