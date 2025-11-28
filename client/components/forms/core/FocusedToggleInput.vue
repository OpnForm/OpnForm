<template>
  <FocusedSelectorInput
    v-bind="focusedSelectorProps"
    :options="toggleOptions"
    :model-value="selectedOption"
    :multiple="false"
    :clearable="false"
    @update:model-value="handleSelection"
    @input-filled="$emit('input-filled')"
  >
    <template #label>
      <slot name="label" />
    </template>
    <template #help>
      <slot name="help" />
    </template>
    <template #error>
      <slot name="error" />
    </template>
  </FocusedSelectorInput>
</template>

<script setup>
import { inputProps, useFormInput } from '../useFormInput.js'
import FocusedSelectorInput from './FocusedSelectorInput.vue'

/**
 * FocusedToggleInput.vue
 *
 * A focused mode replacement for ToggleSwitchInput that uses FocusedSelectorInput
 * with Yes/No options (Y/N keyboard shortcuts).
 *
 * Features:
 *  - Always shows two options: Yes (Y) and No (N)
 *  - Emits boolean values (true/false)
 *  - Keyboard shortcuts Y and N
 *  - Emits 'input-filled' on selection
 *  - All styling props passed through to FocusedSelectorInput
 */

const props = defineProps({
  ...inputProps
})

const emit = defineEmits(['update:modelValue', 'focus', 'blur', 'input-filled'])

const { t } = useI18n()

// Use form input composable
const {
  compVal
} = useFormInput(props, { emit })

// Toggle options with translations and custom letters
const toggleOptions = computed(() => [
  {
    name: t('forms.toggle.yes'),
    value: true,
    letter: 'Y'
  },
  {
    name: t('forms.toggle.no'),
    value: false,
    letter: 'N'
  }
])

// Convert boolean value to selected option (null if not set)
const selectedOption = computed(() => {
  // Return actual value: true, false, or null
  if (compVal.value === true) return true
  if (compVal.value === false) return false
  return null
})

// Handle selection and emit boolean
const handleSelection = (value) => {
  compVal.value = value === true
}

// Forward all props with UI override for constrained width
// FocusedSelectorInput template will pass ui.slots.container via class parameter
// tv() slot function uses twMerge to merge with base classes
const focusedSelectorProps = computed(() => {
  return {
    ...props,
    optionKey: 'value',
    emitKey: 'value',
    displayKey: 'name',
    // Pass max-w-xs to container slot
    // FocusedSelectorInput template will pass this via class parameter to ui.container()
    // tv slot function handles merging with base classes
    ui: {
      ...(props.ui || {}),
      slots: {
        ...(props.ui?.slots || {}),
        container: 'max-w-xs'
      }
    }
  }
})
</script>

