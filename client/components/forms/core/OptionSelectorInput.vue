<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <div
      :class="[
        seamless ? 'flex -space-x-px' : `grid ${gridClass} gap-2`
      ]"
      :style="optionStyle"
      role="listbox"
      :aria-multiselectable="multiple ? 'true' : 'false'"
      :tabindex="disabled ? -1 : 0"
      @keydown="onKeydown"
      ref="root"
    >
      <UTooltip
        v-for="(option, idx) in options"
        
        :key="option[optionKey]"
        :text="option.tooltip"
        :disabled="!option.tooltip"
        :class="optionClasses(isSelected(option))"
      >
        <button
          type="button"
          class="group flex flex-col items-center justify-center transition-colors focus:outline-hidden w-full h-full"
          :class="[buttonClasses(disabled || option.disabled), option.class ? (typeof option.class === 'function' ? option.class(isSelected(option)) : option.class) : {}, isSelected(option) ? 'text-form-color' : 'text-inherit']"
          :aria-selected="isSelected(option) ? 'true' : 'false'"
          :tabindex="disabled || option.disabled ? -1 : 0"
          :disabled="disabled || option.disabled"
          @click="selectOption(option)"
          @focus="focusedIdx = idx"
          @mouseenter="focusedIdx = idx"
          role="option"
        >
          <slot name="icon" :option="option" :selected="isSelected(option)">
            <Icon
              v-if="option.icon"
              :name="isSelected(option) && option.selectedIcon ? option.selectedIcon : option.icon"
              mode="svg"
              :class="[
                'w-4 h-4',
                option.label ? 'mb-1' : '',
                isSelected(option) ? 'text-form-color' : 'text-inherit',
                option.iconClass ? (typeof option.iconClass === 'function' ? option.iconClass(isSelected(option)) : option.iconClass) : {},
                isSelected(option) && option.iconSelectedClass ? (typeof option.iconSelectedClass === 'function' ? option.iconSelectedClass(true) : option.iconSelectedClass) : {}
              ]"
            />
          </slot>
          <span
            v-if="option.label || !option.icon"
            :class="[labelClasses(), isSelected(option) ? 'text-form-color' : 'text-inherit']"
          >{{ isSelected(option) ? option.selectedLabel ?? option.label : option.label }}</span>
        </button>
      </UTooltip>
    </div>

    <template #help>
      <slot name="help" />
    </template>

    <template #error>
      <slot name="error" />
    </template>
  </input-wrapper>
</template>

<script setup>
import { inputProps, useFormInput } from '../useFormInput.js'
import { tv } from 'tailwind-variants'
import { optionSelectorInputTheme } from '~/lib/forms/themes/option-selector-input.theme.js'

/**
 * OptionSelectorInput.vue
 *
 * A form input component for selecting options in a grid layout with icons.
 * Integrates with the form system using InputWrapper and useFormInput.
 *
 * Props:
 *  - options: Array<{ name, label, icon, selectedIcon?, iconClass?, tooltip?, disabled? }>
 *  - multiple: Boolean (default: false)
 *  - optionKey: String (default: 'name')
 *  - columns: Number (default: 3, for grid layout)
 *  - seamless: Boolean (default: false, removes gaps and only applies radius to first/last items)
 *
 * Features:
 *  - Keyboard navigation (arrow keys, enter/space to select)
 *  - Focus management
 *  - Optional tooltips per option
 *  - Form validation integration
 *  - Notion-style look by default
 *  - Seamless mode for connected button appearance
 */

const props = defineProps({
  ...inputProps,
  options: { type: Array, required: true },
  multiple: { type: Boolean, default: false },
  optionKey: { type: String, default: 'name' },
  columns: { type: Number, default: 3 },
  seamless: { type: Boolean, default: false },
  clearable: { type: Boolean, default: false }
})

const emit = defineEmits(['update:modelValue', 'focus', 'blur'])

// Use form input composable
const {
  compVal,
  inputWrapperProps,
  resolvedTheme,
  resolvedSize
} = useFormInput(props, { emit })

// Local state
const focusedIdx = ref(-1)
const root = ref(null)

// Computed properties
const gridClass = computed(() => `grid-cols-${props.columns}`)

const optionStyle = computed(() => ({
  '--bg-form-color': props.color
}))

const variants = computed(() => tv(optionSelectorInputTheme, props.ui))
const optionClasses = (selected) => variants.value({
  theme: resolvedTheme.value,
  size: resolvedSize.value,
  seamless: props.seamless,
  selected
}).option()
const buttonClasses = (isDisabled) => variants.value({
  theme: resolvedTheme.value,
  size: resolvedSize.value,
  disabled: isDisabled
}).button()
const labelClasses = () => variants.value({ size: resolvedSize.value }).label()

// Methods
function isSelected(option) {
  if (props.multiple) {
    return Array.isArray(compVal.value) && compVal.value.includes(option[props.optionKey])
  }
  return compVal.value === option[props.optionKey]
}

function selectOption(option) {
  if (props.disabled || option.disabled) return
  
  if (props.multiple) {
    let newValue = Array.isArray(compVal.value) ? [...compVal.value] : []
    const idx = newValue.indexOf(option[props.optionKey])
    if (idx > -1) {
      // If removing would result in empty selection and not clearable, block
      const nextLen = newValue.length - 1
      if (!props.clearable && nextLen < 1) return
      newValue.splice(idx, 1)
    } else {
      newValue.push(option[props.optionKey])
    }
    compVal.value = newValue
  } else {
    // Only allow clearing (setting to null) if clearable is true
    if (isSelected(option)) {
      if (!props.clearable) return
      compVal.value = null
    } else {
      compVal.value = option[props.optionKey]
    }
  }
}

function onKeydown(e) {
  if (props.disabled) return
  const len = props.options.length
  if (len === 0) return
  
  if (["ArrowRight", "ArrowDown"].includes(e.key)) {
    e.preventDefault()
    focusedIdx.value = (focusedIdx.value + 1) % len
    focusButton(focusedIdx.value)
  } else if (["ArrowLeft", "ArrowUp"].includes(e.key)) {
    e.preventDefault()
    focusedIdx.value = (focusedIdx.value - 1 + len) % len
    focusButton(focusedIdx.value)
  } else if (["Enter", " ", "Spacebar"].includes(e.key)) {
    e.preventDefault()
    if (focusedIdx.value >= 0 && focusedIdx.value < len) {
      selectOption(props.options[focusedIdx.value])
    }
  }
}

function focusButton(idx) {
  nextTick(() => {
    const btns = root.value?.querySelectorAll('button')
    if (btns && btns[idx]) btns[idx].focus()
  })
}

// Watchers
watch(compVal, (val) => {
  // Keep focus on selected
  if (!props.multiple && val != null) {
    const idx = props.options.findIndex(opt => opt[props.optionKey] === val)
    if (idx !== -1) focusedIdx.value = idx
  }
})
</script> 
