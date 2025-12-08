<template>
  <div :style="colorStyle">
    <Icon
      v-show="isChecked"
      name="i-material-symbols-check-box"
      :class="ui.checkedIcon({ class: props.ui?.slots?.checkedIcon })"
    />
    <Icon
      v-show="!isChecked"
      name="i-material-symbols-check-box-outline-blank"
      :class="ui.uncheckedIcon({ class: props.ui?.slots?.uncheckedIcon })"
    />
  </div>
</template>

<script setup>
import { tv } from "tailwind-variants"
import { checkboxIconTheme } from "~/lib/forms/themes/checkbox-icon.theme.js"

const props = defineProps({
  isChecked: {
    type: Boolean,
    required: true
  },
  color: {
    type: String,
    default: '#3B82F6'
  },
  // Theme configuration as strings for tailwind-variants
  size: {type: String, default: null}, 
  theme: {type: String, default: null},
  ui: {type: Object, default: () => ({})}
})

// Inject theme values for centralized resolution
const injectedSize = inject('formSize', null)
const injectedTheme = inject('formTheme', null)

// Resolve size and theme with proper reactivity
const resolvedSize = computed(() => {
  return props.size || injectedSize?.value || 'md'
})

const resolvedTheme = computed(() => {
  return props.theme || injectedTheme?.value || 'default'
})

// Color style for CSS custom property
const colorStyle = computed(() => ({
  '--form-color': props.color
}))

// OPTIMIZED: Single computed following Nuxt UI pattern
const ui = computed(() => {
  return tv(checkboxIconTheme, props.ui)({
    size: resolvedSize.value,
    theme: resolvedTheme.value
  })
})

</script>