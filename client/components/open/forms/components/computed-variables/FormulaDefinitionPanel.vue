<template>
  <div class="w-full flex flex-col bg-white h-full">
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
          :model-value="localVariable.name"
          placeholder="e.g., Total Price, Dog Age"
          :color="errors.name ? 'error' : undefined"
          @update:model-value="$emit('update:name', $event)"
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
            @click="$emit('show-reference')"
          >
            Function Reference
          </UButton>
        </div>
        
        <FormulaEditor
          :model-value="currentFormula"
          :form="form"
          :current-variable-id="localVariable.id"
          :other-variables="otherVariables"
          @update:modelValue="$emit('update:formula', $event)"
          @validation="$emit('validation', $event)"
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
    </div>
  </div>
</template>

<script setup>
import FormulaEditor from './FormulaEditor.vue'

defineProps({
  localVariable: {
    type: Object,
    required: true
  },
  currentFormula: {
    type: String,
    default: ''
  },
  form: {
    type: Object,
    required: true
  },
  otherVariables: {
    type: Array,
    default: () => []
  },
  validationResult: {
    type: Object,
    default: () => ({ valid: true, errors: [] })
  },
  errors: {
    type: Object,
    default: () => ({})
  }
})

defineEmits(['update:name', 'update:formula', 'validation', 'show-reference'])
</script>

