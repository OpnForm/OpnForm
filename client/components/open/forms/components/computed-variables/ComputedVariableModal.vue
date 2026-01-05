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
          <div class="w-1/2 border-r">
            <FormulaDefinitionPanel
              :local-variable="localVariable"
              :current-formula="currentFormula"
                  :form="form"
                  :other-variables="otherVariables"
              :validation-result="validationResult"
              :errors="errors"
              @update:name="handleNameUpdate"
              @update:formula="handleFormulaUpdate"
                  @validation="handleValidation"
              @show-reference="showReference = true"
            />
          </div>
          
          <!-- Right Column: Test Form -->
          <div class="w-1/2">
            <FormulaTestPanel
              :form="form"
              :referenced-field-ids="referencedFieldIds"
              :referenced-variables="referencedVariables"
              :other-variables="otherVariables"
              :computed-result="computedResult"
              :validation-result="validationResult"
              :local-variable="localVariable"
              @update:test-values="handleTestValuesUpdate"
            />
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
import FormulaDefinitionPanel from './FormulaDefinitionPanel.vue'
import FormulaTestPanel from './FormulaTestPanel.vue'
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

// Track the formula separately to ensure reactivity
const currentFormula = ref('')

// Other computed variables (excluding current one being edited)
const otherVariables = computed(() => {
  const all = props.form?.computed_variables || []
  return all.filter(v => v.id !== localVariable.value.id)
})

// Extract field IDs referenced in the formula
const referencedFieldIds = computed(() => {
  const formula = currentFormula.value
  if (!formula) return []
  try {
    return extractFieldIds(formula)
  } catch {
    return []
  }
})

// Get computed variables referenced in the formula
const referencedVariables = computed(() => {
  const refIds = new Set(referencedFieldIds.value)
  return otherVariables.value.filter(v => refIds.has(v.id))
})

// Format result for display
function formatResult(result) {
  if (result === null || result === undefined) {
    return 'NULL'
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

// Calculate the current formula result
const computedResult = computed(() => {
  if (!validationResult.value.valid || !localVariable.value.formula) {
    return 'NULL'
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

// Watch for variable prop changes
watch(() => props.variable, (newVal) => {
  if (newVal) {
    localVariable.value = { ...newVal }
    currentFormula.value = newVal.formula || ''
  } else {
    localVariable.value = { ...defaultVariable }
    currentFormula.value = ''
  }
  errors.value = {}
  validationResult.value = { valid: true, errors: [] }
}, { immediate: true })

// Watch for modal opening
watch(isOpen, (newVal) => {
  if (newVal) {
    if (props.variable) {
      localVariable.value = { ...props.variable }
      currentFormula.value = props.variable.formula || ''
    } else {
      localVariable.value = { ...defaultVariable }
      currentFormula.value = ''
    }
    errors.value = {}
    testValues.value = {}
  }
})

// Handle events from child components
function handleNameUpdate(name) {
  localVariable.value.name = name
}

function handleFormulaUpdate(formula) {
  localVariable.value.formula = formula
  currentFormula.value = formula
}

function handleValidation(result) {
  validationResult.value = result
}

function handleTestValuesUpdate(values) {
  testValues.value = values
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
  return formula.trim().replace(/\s+/g, ' ')
}

function save() {
  if (!validate()) return
  
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
