<template>
  <UModal
    v-model:open="isOpen"
    :ui="{ content: 'sm:max-w-5xl' }"
  >
    <template #content>
      <div class="flex flex-col h-[80vh] bg-white">
        <!-- Header -->
        <div class="flex items-center justify-between p-4 border-b flex-shrink-0">
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
        
        <!-- Main Content - Two Columns -->
        <div class="flex flex-1 min-h-0">
          <!-- Left Column: Formula Editor -->
          <div class="w-1/2 border-r flex flex-col bg-white">
            <div class="p-4 border-b">
              <h4 class="font-medium text-gray-900 flex items-center gap-2">
                <Icon name="i-heroicons-code-bracket" class="w-4 h-4" />
                Formula Definition
              </h4>
              <p class="text-xs text-gray-500 mt-1">
                Define your variable name and formula
              </p>
            </div>
            
            <div class="p-4 flex-1 overflow-y-auto space-y-4">
              <!-- Name Input -->
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">
                  Variable Name <span class="text-red-500">*</span>
                </label>
                <UInput
                  v-model="localVariable.name"
                  placeholder="e.g., Total Price, Dog Age"
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
                    Function Reference
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
                    class="h-5 w-5 flex-shrink-0"
                  />
                  <span
                    class="text-sm"
                    :class="validationResult.valid ? 'text-green-700' : 'text-red-700'"
                  >
                    {{ validationResult.valid ? 'Valid formula' : validationResult.errors[0]?.message }}
                  </span>
                </div>
              </div>

              <!-- Result Display -->
              <div v-if="validationResult.valid && localVariable.formula" class="p-4 bg-blue-50 rounded-lg">
                <div class="flex items-center justify-between">
                  <div class="flex items-center gap-2">
                    <Icon name="i-heroicons-calculator" class="w-5 h-5 text-blue-600" />
                    <span class="text-sm font-medium text-blue-900">Result</span>
                  </div>
                  <span class="text-lg font-mono font-bold text-blue-700">
                    {{ computedResult }}
                  </span>
                </div>
                <p v-if="testableFields.length === 0" class="text-xs text-blue-600 mt-2">
                  Add fields to your formula to test with sample values
                </p>
              </div>
            </div>
          </div>
          
          <!-- Right Column: Test Form -->
          <div class="w-1/2 flex flex-col bg-white">
            <div class="p-4 border-b">
              <h4 class="font-medium text-gray-900 flex items-center gap-2">
                <Icon name="i-heroicons-beaker" class="w-4 h-4" />
                Test Your Formula
              </h4>
              <p class="text-xs text-gray-500 mt-1">
                Enter sample values to preview the calculation
              </p>
            </div>
            
            <div class="p-4 flex-1 overflow-y-auto">
              <div v-if="testableFields.length === 0 && referencedVariables.length === 0" class="text-center py-8 text-gray-500">
                <Icon name="i-heroicons-information-circle" class="w-8 h-8 mx-auto mb-2 text-gray-400" />
                <p class="text-sm">Add fields to your formula to test with sample values</p>
                <p class="text-xs text-gray-400 mt-2">Use the "Field" button in the formula editor</p>
              </div>
              
              <div v-else class="space-y-4">
                <!-- Form Fields -->
                <div v-if="testableFields.length > 0" class="rounded-lg p-4 border border-gray-200">
                  <h5 class="text-xs font-medium text-gray-500 uppercase tracking-wide mb-3">
                    Form Field Values
                  </h5>
                  <div class="space-y-3">
                    <div v-for="field in testableFields" :key="field.id">
                      <label class="block text-sm font-medium text-gray-700 mb-1">
                        {{ field.name }}
                        <span class="text-xs text-gray-400 font-normal ml-1">({{ field.type }})</span>
                      </label>
                      
                      <!-- Number input -->
                      <UInput
                        v-if="isNumericField(field)"
                        v-model.number="testValues[field.id]"
                        type="number"
                        :placeholder="`Enter ${field.name}`"
                        size="sm"
                      />
                      
                      <!-- Checkbox input -->
                      <UCheckbox
                        v-else-if="field.type === 'checkbox'"
                        v-model="testValues[field.id]"
                        :label="field.name"
                      />
                      
                      <!-- Select input -->
                      <USelect
                        v-else-if="field.type === 'select'"
                        v-model="testValues[field.id]"
                        :options="getSelectOptions(field)"
                        placeholder="Select an option"
                        size="sm"
                      />
                      
                      <!-- Text input (default) -->
                      <UInput
                        v-else
                        v-model="testValues[field.id]"
                        :placeholder="`Enter ${field.name}`"
                        size="sm"
                      />
                    </div>
                  </div>
                </div>
                
                <!-- Computed Variables in Formula -->
                <div v-if="referencedVariables.length > 0" class="rounded-lg p-4 border border-purple-200">
                  <h5 class="text-xs font-medium text-purple-600 uppercase tracking-wide mb-3">
                    Referenced Variables
                  </h5>
                  <div class="space-y-2">
                    <div 
                      v-for="cv in referencedVariables" 
                      :key="cv.id"
                      class="flex items-center justify-between p-2 bg-purple-50 rounded"
                    >
                      <span class="text-sm text-purple-700">{{ cv.name }}</span>
                      <span class="font-mono text-sm text-purple-900">
                        {{ getComputedVariableValue(cv) }}
                      </span>
                    </div>
                  </div>
                </div>
                
                <!-- Quick Actions -->
                <div class="flex gap-2">
                  <UButton
                    size="xs"
                    color="neutral"
                    variant="outline"
                    icon="i-heroicons-arrow-path"
                    @click="resetTestValues"
                  >
                    Reset Values
                  </UButton>
                  <UButton
                    size="xs"
                    color="neutral"
                    variant="outline"
                    icon="i-heroicons-sparkles"
                    @click="fillSampleValues"
                  >
                    Fill Sample Data
                  </UButton>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Footer -->
        <div class="flex justify-end gap-3 p-4 border-t flex-shrink-0">
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
  <FunctionReference v-model="showReference" />
</template>

<script setup>
import FormulaEditor from './FormulaEditor.vue'
import FunctionReference from './FunctionReference.vue'
import { evaluateFormula, extractFieldIds } from '~/lib/formulas/index.js'

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
const testValues = ref({})
const currentFormulaFieldIds = ref([])

// Other computed variables (excluding current one being edited)
const otherVariables = computed(() => {
  const all = props.form?.computed_variables || []
  return all.filter(v => v.id !== localVariable.value.id)
})

// Get all fields from the form
// Use id if available, otherwise generate a stable identifier from name (same logic as FormulaEditor)
const allFields = computed(() => {
  return (props.form?.properties || [])
    .filter(p => p.type && !p.type.startsWith('nf-'))
    .map((p, index) => ({
      id: p.id || `field_${index}_${(p.name || '').toLowerCase().replace(/\s+/g, '_')}`,
      name: p.name,
      type: p.type,
      options: p.options || p[p.type]?.options || []
    }))
})

// Extract field IDs referenced in the formula (using tracked ref for reactivity)
const referencedFieldIds = computed(() => {
  // Access currentFormulaFieldIds to ensure reactivity
  return currentFormulaFieldIds.value
})

// Update field IDs when formula changes
function updateReferencedFieldIds() {
  const formula = localVariable.value.formula
  if (!formula) {
    currentFormulaFieldIds.value = []
    return
  }
  try {
    currentFormulaFieldIds.value = extractFieldIds(formula)
  } catch {
    currentFormulaFieldIds.value = []
  }
}

// Get fields that are used in the formula (for test form)
const testableFields = computed(() => {
  const refIds = new Set(referencedFieldIds.value)
  // Filter fields that are referenced in the formula
  return allFields.value.filter(f => refIds.has(f.id))
})

// Get computed variables referenced in the formula
const referencedVariables = computed(() => {
  const refIds = new Set(referencedFieldIds.value)
  return otherVariables.value.filter(v => refIds.has(v.id))
})

// Check if field is numeric type
function isNumericField(field) {
  return ['number', 'rating', 'scale', 'slider'].includes(field.type)
}

// Get select options for a field
function getSelectOptions(field) {
  const options = field.options || []
  if (Array.isArray(options)) {
    return options.map(opt => typeof opt === 'string' ? opt : opt.name || opt.value || opt)
  }
  return []
}

// Calculate value of a computed variable
function getComputedVariableValue(cv) {
  try {
    const context = { ...testValues.value }
    // First evaluate other variables this one might depend on
    for (const v of otherVariables.value) {
      if (v.id !== cv.id) {
        try {
          context[v.id] = evaluateFormula(v.formula, context)
        } catch {
          context[v.id] = null
        }
      }
    }
    const result = evaluateFormula(cv.formula, context)
    return formatResult(result)
  } catch {
    return '—'
  }
}

// Calculate the current formula result
const computedResult = computed(() => {
  if (!validationResult.value.valid || !localVariable.value.formula) {
    return '—'
  }

  try {
    const context = { ...testValues.value }
    
    // Evaluate other computed variables first
    for (const v of otherVariables.value) {
      try {
        context[v.id] = evaluateFormula(v.formula, context)
      } catch {
        context[v.id] = null
      }
    }

    const result = evaluateFormula(localVariable.value.formula, context)
    return formatResult(result)
  } catch {
    return 'Error'
  }
})

// Format result for display
function formatResult(result) {
  if (result === null || result === undefined) {
    return '—'
  }
  
  if (typeof result === 'number') {
    return Number.isInteger(result) ? result.toString() : result.toFixed(2)
  }
  
  if (typeof result === 'boolean') {
    return result ? 'TRUE' : 'FALSE'
  }
  
  if (typeof result === 'string') {
    return result.length > 50 ? `"${result.substring(0, 50)}..."` : `"${result}"`
  }
  
  return String(result)
}

// Reset test values
function resetTestValues() {
  testValues.value = {}
}

// Fill sample values based on field types
function fillSampleValues() {
  const newValues = {}
  for (const field of testableFields.value) {
    switch (field.type) {
      case 'number':
      case 'slider':
        newValues[field.id] = 10
        break
      case 'rating':
        newValues[field.id] = 4
        break
      case 'scale':
        newValues[field.id] = 5
        break
      case 'checkbox':
        newValues[field.id] = true
        break
      case 'select': {
        const options = getSelectOptions(field)
        newValues[field.id] = options[0] || ''
        break
      }
      default:
        newValues[field.id] = field.name || 'Sample'
    }
  }
  testValues.value = newValues
}

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
    testValues.value = {}
    currentFormulaFieldIds.value = []
    
    // Update field IDs and auto-fill sample values
    nextTick(() => {
      updateReferencedFieldIds()
      if (testableFields.value.length > 0) {
        fillSampleValues()
      }
    })
  }
})

// Watch formula changes to update referenced field IDs and test values
watch(() => localVariable.value.formula, () => {
  // First, update the referenced field IDs
  updateReferencedFieldIds()
  
  // Then, fill sample values for any new fields
  nextTick(() => {
    const currentFields = new Set(Object.keys(testValues.value))
    for (const field of testableFields.value) {
      if (!currentFields.has(field.id)) {
        // New field added, set a sample value
        switch (field.type) {
          case 'number':
          case 'slider':
            testValues.value[field.id] = 10
            break
          case 'rating':
            testValues.value[field.id] = 4
            break
          case 'scale':
            testValues.value[field.id] = 5
            break
          case 'checkbox':
            testValues.value[field.id] = true
            break
          default:
            testValues.value[field.id] = field.name || 'Sample'
        }
      }
    }
  })
}, { immediate: true })

// Handle validation from formula editor
function handleValidation(result) {
  validationResult.value = result
}

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

// Normalize formula by trimming and collapsing whitespace
function normalizeFormula(formula) {
  if (!formula) return ''
  // Trim leading/trailing whitespace and collapse multiple spaces/newlines into single space
  return formula.trim().replace(/\s+/g, ' ')
}

function save() {
  if (!validate()) return
  
  // Normalize the formula before saving
  const normalizedVariable = {
    ...localVariable.value,
    name: localVariable.value.name.trim(),
    formula: normalizeFormula(localVariable.value.formula)
  }
  
  emit('save', normalizedVariable)
}

function close() {
  isOpen.value = false
}
</script>
