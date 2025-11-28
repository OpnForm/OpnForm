<template>
  <div :class="ui.container({ class: props.ui?.slots?.container })">
    <input
      :id="id || name"
      v-model="internalValue"
      :value="value"
      :name="name"
      type="checkbox"
      :class="ui.input({ class: props.ui?.slots?.input })"
      :style="colorStyle"
      :disabled="disabled ? true : null"
      @keydown="handleKeydown"
    >
    <label
      :for="id || name"
      :class="ui.label({ class: props.ui?.slots?.label })"
    >
      <slot />
    </label>
  </div>
</template>

<script setup>
import { tv } from "tailwind-variants"
import { vCheckboxTheme } from "~/lib/forms/themes/v-checkbox.theme.js"

defineOptions({
  name: 'VCheckbox',
})

const props = defineProps({
  id: { type: String, default: null },
  name: { type: String, default: 'checkbox' },
  modelValue: { type: [Boolean, String], default: false },
  value: { type: [Boolean, String, Number, Object], required: false },
  disabled: { type: Boolean, default: false },
  color: { type: String, default: null },
  // Theme configuration as strings for tailwind-variants
  size: {type: String, default: null}, 
  ui: {type: Object, default: () => ({})}
})

const emit = defineEmits(['update:modelValue', 'click'])

const internalValue = ref(props.modelValue)

// Inject theme values for centralized resolution
const injectedSize = inject('formSize', null)

// Resolve size with proper reactivity
const resolvedSize = computed(() => {
  return props.size || injectedSize?.value || 'md'
})

// Color style for CSS custom property
const colorStyle = computed(() => ({
  '--accent-color': props.color,
  '--form-color': props.color
}))

// OPTIMIZED: Single computed following Nuxt UI pattern
const ui = computed(() => {
  return tv(vCheckboxTheme, props.ui)({
    size: resolvedSize.value,
    disabled: props.disabled
  })
})

watch(
  () => props.modelValue,
  (val) => {
    internalValue.value = val
  },
)

watch(
  () => internalValue.value,
  (val, oldVal) => {
    if (val === 0 || val === '0')
      val = false
    if (val === 1 || val === '1')
      val = true

    if (val !== oldVal)
      emit('update:modelValue', val)
  },
)

const handleKeydown = (event) => {
  if (props.disabled) return

  if (event.key === 'Enter' || event.key === ' ') {
    event.preventDefault()
    internalValue.value = !internalValue.value
  }
}

onMounted(() => {
  if (internalValue.value === null)
    internalValue.value = false
})
</script>
<style>
  .checkbox {
    accent-color: var(--accent-color);
  }
</style>
