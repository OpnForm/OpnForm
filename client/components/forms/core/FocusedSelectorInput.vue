<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <div
      ref="root"
      :style="optionStyle"
      :class="variantSlots.container({ class: props.ui?.slots?.container })"
      role="listbox"
      :aria-multiselectable="multiple ? 'true' : 'false'"
      :tabindex="0"
      @keydown.capture="onKeydown"
    >
      <div
        v-for="(option, idx) in options"
        :key="option[optionKey]"
        :class="optionClasses(option)"
      >
        <button
          type="button"
          :class="buttonClasses(isSelected(option), disabled || option.disabled)"
          :aria-selected="isSelected(option) ? 'true' : 'false'"
          :tabindex="-1"
          :disabled="disabled || option.disabled"
          @click="selectOption(option, false)"
          @focus="focusedIdx = idx"
          @keydown.stop
          role="option"
        >
          <!-- Label (A, B, C, etc.) -->
          <span :class="labelClasses(isSelected(option))">
            {{ getOptionLabel(idx) }}
          </span>

          <!-- Option text -->
          <span :class="textClasses()">
            {{ getOptionName(option) }}
          </span>

          <!-- Checkmark for selected state -->
          <Icon
            name="heroicons:check-20-solid"
            :class="checkmarkClasses(option)"
          />
        </button>
      </div>
    </div>

    <template #help>
      <slot name="help" />
    </template>

    <template 
      v-if="multiple && (minSelection || maxSelection)"
      #bottom_after_help
    >
      <small class="text-xs text-neutral-500 dark:text-neutral-400 mt-1 block">
        <span v-if="minSelection && maxSelection">
          {{ selectedCount }} of {{ minSelection }}-{{ maxSelection }}
        </span>
        <span v-else-if="minSelection">
          {{ selectedCount }} selected (min {{ minSelection }})
        </span>
        <span v-else-if="maxSelection">
          {{ selectedCount }}/{{ maxSelection }} selected
        </span>
      </small>
    </template>

    <template #error>
      <slot name="error" />
    </template>
  </input-wrapper>
</template>

<script setup>
import { inputProps, useFormInput } from '../useFormInput.js'
import { tv } from 'tailwind-variants'
import { focusedSelectorInputTheme } from '~/lib/forms/themes/focused-selector-input.theme.js'

/**
 * FocusedSelectorInput.vue
 *
 * A form input component for focused mode with one option per line and keyboard shortcuts.
 * Integrates with the form system using InputWrapper and useFormInput.
 *
 * Props:
 *  - options: Array<{ name, label, disabled?, letter? }>
 *  - multiple: Boolean (default: false)
 *  - optionKey: String (default: 'name')
 *  - clearable: Boolean (default: false)
 *
 * Features:
 *  - Keyboard shortcuts (A, B, C, etc. to select options)
 *  - Custom letter shortcuts via option.letter property
 *  - Arrow keys navigation
 *  - Enter/Space to select focused option OR skip/confirm if no option focused
 *  - Emits 'input-filled' in single selection mode after animation
 *  - Emits 'input-filled' when Enter pressed without focus (skip or confirm)
 *  - Multiple selection mode: updates value, no auto input-filled on selection
 *  - Form color integration for selected state
 */

const props = defineProps({
  ...inputProps,
  options: { type: Array, required: true },
  multiple: { type: Boolean, default: false },
  optionKey: { type: String, default: 'value' },
  emitKey: { type: String, default: 'value' },
  displayKey: { type: String, default: 'name' },
  clearable: { type: Boolean, default: false },
  allowCreation: { type: Boolean, default: false },
  minSelection: { type: Number, default: null },
  maxSelection: { type: Number, default: null },
  presentation: { type: String, default: 'classic' }
})

const emit = defineEmits(['update:modelValue', 'focus', 'blur', 'input-filled'])

// Use form input composable with variants config
const {
  compVal,
  inputWrapperProps,
  resolvedTheme,
  resolvedSize,
  resolvedBorderRadius,
  ui
} = useFormInput(props, { emit }, {
  variants: focusedSelectorInputTheme
})

// Local state
const focusedIdx = ref(-1)
const root = ref(null)
const animatingOption = ref(null) // Track which option is animating

// Computed properties
const optionStyle = computed(() => ({
  '--bg-form-color': props.color,
  '--text-form-color': props.color
}))

// Use variants from composable (already handles UI merging properly)
const variantSlots = computed(() => ui.value)

// Helper functions that accept dynamic variants (selected, disabled, animating)
// Note: These need to recreate variants since ui from composable is already computed
// with base variants. For dynamic per-option variants, we need to call tv() again
const focusedSelectorVariants = computed(() => tv(focusedSelectorInputTheme, props.ui))

const optionClasses = (option) => {
  const selected = isSelected(option)
  const isAnimating = animatingOption.value === getOptionValue(option)
  return focusedSelectorVariants.value({
    theme: resolvedTheme.value,
    size: resolvedSize.value,
    borderRadius: resolvedBorderRadius.value,
    selected,
    animating: isAnimating,
    disabled: props.disabled
  }).option({ class: props.ui?.slots?.option })
}

const buttonClasses = (selected, isDisabled) => focusedSelectorVariants.value({
  theme: resolvedTheme.value,
  size: resolvedSize.value,
  selected,
  disabled: isDisabled
}).optionButton({ class: props.ui?.slots?.optionButton })

const labelClasses = (selected) => focusedSelectorVariants.value({
  theme: resolvedTheme.value,
  size: resolvedSize.value,
  borderRadius: resolvedBorderRadius.value,
  selected
}).label({ class: props.ui?.slots?.label })

const textClasses = () => variantSlots.value.optionText({ class: props.ui?.slots?.optionText })

const checkmarkClasses = (option) => {
  const selected = isSelected(option)
  const isAnimating = animatingOption.value === getOptionValue(option)
  return focusedSelectorVariants.value({
    theme: resolvedTheme.value,
    size: resolvedSize.value,
    selected,
    animating: isAnimating
  }).checkmark({ class: props.ui?.slots?.checkmark })
}

// Methods
function getOptionLabel(idx) {
  const option = props.options[idx]
  // Use custom letter if provided, otherwise default to A, B, C, etc.
  if (option?.letter && typeof option.letter === 'string' && option.letter.length > 0) {
    return option.letter.toUpperCase().charAt(0)
  }
  // Convert index to A, B, C, etc.
  return String.fromCharCode(65 + idx)
}

function getOptionName(option) {
  if (!option) return ''
  if (option[props.displayKey] !== undefined) {
    return option[props.displayKey]
  }
  return option[props.optionKey]?.toString() || ''
}

function getOptionValue(option) {
  if (props.emitKey && option[props.emitKey] !== undefined) {
    return option[props.emitKey]
  }
  return option[props.optionKey]
}

function isSelected(option) {
  const optValue = getOptionValue(option)
  if (props.multiple) {
    return Array.isArray(compVal.value) && compVal.value.some(val => {
      // Handle both primitive values and objects
      if (typeof val === 'object' && val !== null && typeof optValue === 'object' && optValue !== null) {
        return val[props.optionKey] === optValue[props.optionKey]
      }
      return val === optValue
    })
  }
  if (typeof compVal.value === 'object' && compVal.value !== null && typeof optValue === 'object' && optValue !== null) {
    return compVal.value[props.optionKey] === optValue[props.optionKey]
  }
  return compVal.value === optValue
}

function selectOption(option, fromKeyboard = false) {
  if (props.disabled || option.disabled) return
  
  const optValue = getOptionValue(option)
  
  if (props.multiple) {
    // Multiple selection mode: just update value, no input-filled emission
    let newValue = Array.isArray(compVal.value) ? [...compVal.value] : []
    
    // Check if already selected
    const selectedIdx = newValue.findIndex(val => {
      if (typeof val === 'object' && val !== null && typeof optValue === 'object' && optValue !== null) {
        return val[props.optionKey] === optValue[props.optionKey]
      }
      return val === optValue
    })
    
    if (selectedIdx > -1) {
      // Check min selection constraint
      if (props.minSelection && newValue.length <= props.minSelection) {
        return // Don't allow deselection if it would go below min
      }
      newValue.splice(selectedIdx, 1)
    } else {
      // Check max selection constraint
      if (props.maxSelection && newValue.length >= props.maxSelection) {
        return // Don't allow selection if at max
      }
      newValue.push(optValue)
    }
    compVal.value = newValue
  } else {
    // For single selection, animate then emit input-filled
    const newValue = isSelected(option) && props.clearable ? null : optValue
    
    if (newValue != null) {
      // Update value immediately
      compVal.value = newValue
      
      // Show animation
      animatingOption.value = optValue
      
      // Wait for animation then emit
      setTimeout(() => {
        animatingOption.value = null
        emit('input-filled')
      }, fromKeyboard ? 400 : 300) // Slightly longer for keyboard for better visual feedback
    } else if (props.clearable) {
      compVal.value = null
    }
  }
}

function onKeydown(e) {
  if (props.disabled) return
  const len = props.options.length
  if (len === 0) return
  
  // Handle letter shortcuts (A, B, C, etc. or custom letters)
  if (e.key.length === 1 && /[a-zA-Z0-9]/.test(e.key)) {
    e.preventDefault()
    e.stopPropagation()
    const upperKey = e.key.toUpperCase()
    
    // First, check for custom letters
    let matchedIdx = -1
    for (let i = 0; i < len; i++) {
      const option = props.options[i]
      if (option?.letter && typeof option.letter === 'string') {
        if (option.letter.toUpperCase().charAt(0) === upperKey) {
          matchedIdx = i
          break
        }
      }
    }
    
    // If no custom letter matched, try default A-Z mapping
    if (matchedIdx === -1) {
      const charCode = upperKey.charCodeAt(0)
      const defaultIdx = charCode - 65 // A=0, B=1, C=2, etc.
      
      // Check if this index exists and doesn't have a custom letter
      if (defaultIdx >= 0 && defaultIdx < len && !props.options[defaultIdx]?.letter) {
        matchedIdx = defaultIdx
      }
    }
    
    // Select the matched option
    if (matchedIdx >= 0 && matchedIdx < len && !props.options[matchedIdx]?.disabled) {
      selectOption(props.options[matchedIdx], true) // Pass true for keyboard selection
      // Don't set focusedIdx for letter selection - keep it at -1
      // This way, pressing Enter after letter selection will emit input-filled
      // Don't focus button for letter selection - keep focus on container
    }
    return
  }
  
  // Arrow key navigation
  if (["ArrowDown"].includes(e.key)) {
    e.preventDefault()
    e.stopPropagation()
    // Initialize focusedIdx if not set, or increment
    if (focusedIdx.value === -1) {
      focusedIdx.value = 0
    } else {
      focusedIdx.value = (focusedIdx.value + 1) % len
    }
    focusButton(focusedIdx.value)
  } else if (["ArrowUp"].includes(e.key)) {
    e.preventDefault()
    e.stopPropagation()
    // Initialize focusedIdx if not set, or decrement
    if (focusedIdx.value === -1) {
      focusedIdx.value = len - 1
    } else {
      focusedIdx.value = (focusedIdx.value - 1 + len) % len
    }
    focusButton(focusedIdx.value)
  } else if (["Enter", " ", "Spacebar"].includes(e.key)) {
    e.preventDefault()
    e.stopPropagation()
    if (focusedIdx.value >= 0 && focusedIdx.value < len) {
      // Option is focused via arrow keys - select it
      selectOption(props.options[focusedIdx.value], true)
    } else {
      // No option focused - user wants to skip or confirm multiple selection
      // Emit input-filled to move to next field
      emit('input-filled')
    }
  }
}

function focusButton(idx) {
  nextTick(() => {
    const btns = root.value?.querySelectorAll('button[role="option"]')
    if (btns && btns[idx]) btns[idx].focus()
  })
}

// Computed for validation display
const selectedCount = computed(() => {
  if (!props.multiple || !Array.isArray(compVal.value)) return 0
  return compVal.value.length
})

// Watchers
watch(compVal, () => {
  // Reset focusedIdx when value changes to allow Enter key to emit input-filled
  // instead of selecting the previously focused option
  focusedIdx.value = -1
})
</script>

<style scoped>
@keyframes flash-blink-light {
  0%, 100% {
    background-color: color-mix(in srgb, var(--bg-form-color) 20%, transparent);
  }
  20% {
    background-color: color-mix(in srgb, var(--bg-form-color) 45%, transparent);
  }
  40% {
    background-color: color-mix(in srgb, var(--bg-form-color) 20%, transparent);
  }
  60% {
    background-color: color-mix(in srgb, var(--bg-form-color) 45%, transparent);
  }
  80% {
    background-color: color-mix(in srgb, var(--bg-form-color) 20%, transparent);
  }
}

@keyframes flash-blink-dark {
  0%, 100% {
    background-color: color-mix(in srgb, var(--bg-form-color) 25%, transparent);
  }
  20% {
    background-color: color-mix(in srgb, var(--bg-form-color) 50%, transparent);
  }
  40% {
    background-color: color-mix(in srgb, var(--bg-form-color) 25%, transparent);
  }
  60% {
    background-color: color-mix(in srgb, var(--bg-form-color) 50%, transparent);
  }
  80% {
    background-color: color-mix(in srgb, var(--bg-form-color) 25%, transparent);
  }
}

.flash-animation {
  animation: flash-blink-light 0.5s ease-in-out;
}

:global(.dark) .flash-animation {
  animation: flash-blink-dark 0.5s ease-in-out;
}
</style>
