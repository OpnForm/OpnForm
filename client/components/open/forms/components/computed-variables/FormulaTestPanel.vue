<template>
  <div class="w-full flex flex-col bg-white h-full">
    <div class="p-4 border-b">
      <h4 class="font-medium text-gray-900 flex items-center gap-2">
        <Icon name="i-heroicons-beaker" class="w-4 h-4" />
        Test Your Formula
      </h4>
      <p class="text-xs text-gray-500 mt-1">
        Enter sample values to preview the calculation
      </p>
    </div>
    
    <div class="p-4 flex-1 overflow-y-auto flex flex-col">
      <!-- Empty state when no fields are referenced -->
      <div 
        v-if="referencedFieldIds.length === 0 && referencedVariables.length === 0" 
        class="text-center py-8 text-gray-500 flex-1"
      >
        <Icon name="i-heroicons-information-circle" class="w-8 h-8 mx-auto mb-2 text-gray-400" />
        <p class="text-sm">Add fields to your formula to test with sample values</p>
        <p class="text-xs text-gray-400 mt-2">Use the "Field" button in the formula editor</p>
      </div>
      
      <div v-else class="space-y-4 flex-1">
        <!-- OpenForm for field inputs -->
        <div v-if="hasTestableFields" class="rounded-lg border border-gray-200 overflow-hidden">
          <div class="px-4 py-2 bg-gray-50 border-b border-gray-200">
            <h5 class="text-xs font-medium text-gray-500 uppercase tracking-wide">
              Form Field Values
            </h5>
          </div>
          <div class="p-4 bg-white">
            <OpenForm
              v-if="formManagerReady"
              :key="formKey"
              :form-manager="formManager"
              @submit.prevent=""
            >
              <template #submit-btn>
                <!-- Hide submit button -->
                <span />
              </template>
            </OpenForm>
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
      </div>
      
      <!-- Result Display - Fixed at bottom of right panel -->
      <div v-if="validationResult.valid && localVariable.formula" class="mt-auto pt-4 border-t">
        <div class="p-4 bg-blue-50 rounded-lg">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Icon name="i-heroicons-calculator" class="w-5 h-5 text-blue-600" />
              <span class="text-sm font-medium text-blue-900">Result</span>
            </div>
            <span class="text-lg font-mono font-bold text-blue-700">
              {{ computedResult }}
            </span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import OpenForm from '~/components/open/forms/OpenForm.vue'
import { FormMode } from '~/lib/forms/FormModeStrategy.js'
import { useFormManager } from '~/lib/forms/composables/useFormManager'
import { evaluateFormula } from '~/lib/formulas/index.js'

const props = defineProps({
  form: {
    type: Object,
    required: true
  },
  referencedFieldIds: {
    type: Array,
    default: () => []
  },
  referencedVariables: {
    type: Array,
    default: () => []
  },
  otherVariables: {
    type: Array,
    default: () => []
  },
  computedResult: {
    type: String,
    default: 'NULL'
  },
  validationResult: {
    type: Object,
    default: () => ({ valid: true, errors: [] })
  },
  localVariable: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['update:testValues'])

// Check if there are testable fields (form fields referenced in formula)
const hasTestableFields = computed(() => {
  const refIds = new Set(props.referencedFieldIds)
  const formProperties = props.form?.properties || []
  return formProperties.some(p => p.id && refIds.has(p.id))
})

// Create filtered form config for OpenForm
const testFormConfig = computed(() => {
  if (!props.form) return null
  
  const refIds = new Set(props.referencedFieldIds)
  
  // Filter properties to only include referenced fields
  const filteredProperties = (props.form.properties || []).filter(p => {
    // Keep fields that are referenced
    return p.id && refIds.has(p.id)
  })
  
  // If no fields to show, return null
  if (filteredProperties.length === 0) return null
  
  return {
    ...props.form,
    properties: filteredProperties,
    size: 'sm', // Force small inputs
    no_branding: true,
    logo_picture: null,
    cover_picture: null,
    // Disable features not needed for testing
    use_captcha: false,
    editable_submissions: false,
    re_fillable: false,
  }
})

// Key to force OpenForm re-render when fields change
const formKey = computed(() => {
  return [...props.referencedFieldIds].sort().join(',')
})

// Form manager instance (keep stable to avoid lifecycle hook warnings)
const formManager = shallowRef(
  useFormManager(null, FormMode.PREFILL, {
    darkMode: false
  })
)
const formManagerReady = ref(false)

// Initialize form manager when referenced fields change
// Watch testFormConfig directly to catch all config changes
watch(
  testFormConfig,
  async (config) => {
    if (!config) {
      formManagerReady.value = false
      return
    }

    formManagerReady.value = false
    await formManager.value.updateConfig(config, {
      skipPendingSubmission: true,
      skipUrlParams: true
    })
    formManagerReady.value = true
  },
  { immediate: true, deep: true }
)

// Watch form data changes and emit to parent
watch(
  () => formManager.value?.data?.value,
  (newData) => {
    if (newData) {
      emit('update:testValues', { ...newData })
    }
  },
  { deep: true }
)

// Format result helper
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

// Calculate value of a computed variable
function getComputedVariableValue(cv) {
  try {
    const context = formManager.value?.data?.value ? { ...formManager.value.data.value } : {}
    
    // First evaluate other variables this one might depend on
    for (const v of props.otherVariables) {
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
    return 'Error'
  }
}
</script>

