<template>
  <UPopover
    v-model:open="isPopoverOpen"
    arrow
    :content="{ side: 'bottom', align: 'end' }"
  >
    <UButton
      size="xs"
      color="neutral"
      variant="ghost"
      icon="i-heroicons-sparkles"
      :loading="loading"
    >
      {{ actionLabel }}
    </UButton>

    <template #content>
      <div class="p-4 w-80">
        <div class="space-y-2">
          <p class="text-sm font-medium">{{ popoverTitle }}</p>
          <p class="text-xs text-neutral-500">
            {{ popoverDescription }}
          </p>

          <TextAreaInput
            name="formula_prompt"
            :disabled="loading"
            :form="aiFormula"
            :placeholder="promptPlaceholder"
          />

          <UButton
            class="mt-2"
            icon="i-heroicons-sparkles"
            :label="actionLabel"
            block
            :loading="loading"
            @click="handleGenerate"
          />
        </div>
      </div>
    </template>
  </UPopover>
</template>

<script setup>
import { formsApi } from '~/api'
import { formulaToDisplay, formulaToStorage } from '~/lib/formulas/index.js'

const props = defineProps({
  form: {
    type: Object,
    required: true
  },
  currentFormula: {
    type: String,
    default: ''
  },
  currentVariable: {
    type: Object,
    default: null
  },
  otherVariables: {
    type: Array,
    default: () => []
  }
})

const emit = defineEmits(['generated', 'generated-name'])

const unsupportedFieldTypes = ['matrix']
const isPopoverOpen = ref(false)
const loading = ref(false)
const aiRequestId = ref(null)

const aiFormula = useForm({
  formula_prompt: ''
})

const hasExistingFormula = computed(() => Boolean(props.currentFormula?.trim()))
const actionLabel = computed(() => hasExistingFormula.value ? 'Update with AI' : 'Generate with AI')
const popoverTitle = computed(() => hasExistingFormula.value ? 'Update formula with AI' : 'Generate formula with AI')
const popoverDescription = computed(() => hasExistingFormula.value
  ? 'Describe the change you want. AI will use the current formula and your form fields.'
  : 'Describe what you want to calculate and AI will generate a formula using your form fields.'
)
const promptPlaceholder = computed(() => hasExistingFormula.value
  ? 'e.g., Apply a 10% discount when quantity is over 100'
  : 'e.g., Multiply price by quantity and apply 10% discount when quantity is over 100'
)

const availableFields = computed(() => {
  return (props.form?.properties || [])
    .filter(p => p.type && !p.type.startsWith('nf-') && !unsupportedFieldTypes.includes(p.type))
    .map((p, index) => ({
      id: p.id || `field_${index}_${(p.name || '').toLowerCase().replace(/\s+/g, '_')}`,
      name: p.name,
      type: p.type
    }))
})

const formulaContext = computed(() => {
  const context = {
    fields: availableFields.value.map(field => ({
      name: field.name,
      type: field.type
    })),
    computed_variables: props.otherVariables.map(variable => ({
      name: variable.name
    })),
    current_variable: {
      name: props.currentVariable?.name ?? ''
    }
  }

  if (hasExistingFormula.value) {
    context.current_formula = formulaToDisplay(
      props.currentFormula,
      availableFields.value,
      props.otherVariables
    )
  }

  return context
})

const handleGenerate = () => {
  if (loading.value) return

  if (!aiFormula.formula_prompt?.trim()) {
    useAlert().warning('Please describe the formula you want to generate.')
    return
  }

  loading.value = true
  aiRequestId.value = null

  formsApi.ai.generateFormula({
    formula_prompt: aiFormula.formula_prompt.trim(),
    context: formulaContext.value
  }).then(data => {
    aiRequestId.value = data.ai_form_completion_id
    fetchGeneratedFormula(data.ai_form_completion_id)
  }).catch(error => {
    console.error('Failed to generate formula:', error)
    useAlert().error(error.response?.data?.message ?? 'Failed to generate formula.')
    loading.value = false
  })
}

const fetchGeneratedFormula = (generationId) => {
  if (!aiRequestId.value) {
    loading.value = false
    return
  }

  const checkFormulaStatus = () => {
    if (!aiRequestId.value) {
      loading.value = false
      return
    }

    formsApi.ai.getFormula(generationId).then(data => {
      if (data.ai_form_completion.status === 'completed') {
        if (aiRequestId.value) {
          const result = JSON.parse(data.ai_form_completion.result)
          const storageFormula = formulaToStorage(
            result.formula,
            availableFields.value,
            props.otherVariables
          )
          emit('generated', storageFormula)
          if (!props.currentVariable?.name?.trim() && result.variable_name?.trim()) {
            emit('generated-name', result.variable_name.trim())
          }
        }
        loading.value = false
        isPopoverOpen.value = false
        useAlert().success(hasExistingFormula.value ? 'Formula updated successfully.' : 'Formula generated successfully.')
        aiFormula.formula_prompt = ''
      } else if (data.ai_form_completion.status === 'failed') {
        useAlert().error(data.ai_form_completion.error ?? 'Something went wrong, please try again.')
        loading.value = false
      } else {
        setTimeout(checkFormulaStatus, 4000)
      }
    }).catch(error => {
      console.error(error)
      useAlert().error(error.response?.data?.message ?? 'Failed to generate formula.')
      loading.value = false
    })
  }

  checkFormulaStatus()
}
</script>
