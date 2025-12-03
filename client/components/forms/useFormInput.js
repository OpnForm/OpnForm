import {ref, computed, watch, inject} from "vue"
import {default as _get} from "lodash/get"
import {default as _set} from "lodash/set"
import {default as _has} from "lodash/has"
import { tv } from "tailwind-variants"

export const inputProps = {
  id: {type: String, default: null},
  name: {type: String, required: true},
  label: {type: String, required: false},
  form: {type: Object, required: false},
  // Theme configuration as strings for tailwind-variants
  theme: {type: String, default: null},
  size: {type: String, default: null}, 
  borderRadius: {type: String, default: null},
  ui: {type: Object, default: () => ({})},
  modelValue: {required: false},
  required: {type: Boolean, default: false},
  disabled: {type: Boolean, default: false},
  placeholder: {type: String, default: null},
  uppercaseLabels: {type: Boolean, default: false},
  hideFieldName: {type: Boolean, default: false},
  showCharLimit: {type: Boolean, default: false},
  help: {type: String, default: null},
  helpPosition: {type: String, default: "below_input"},
  color: {type: String, default: "#3B82F6"},
  wrapperClass: { type: String, default: "" },
  media: { type: Object, default: null },
  isDark: { type: Boolean, default: false },
  locale: { type: String, default: "en" },
  isAdminPreview: { type: Boolean, default: false }
}

export function useFormInput(props, context, options = {}) {
  const composableOptions = {
    formPrefixKey: null,
    variants: null, // Tailwind-variants configuration object
    additionalVariants: {}, // Component-specific variants
    ...options
  }
  const content = ref(props.modelValue)

  // Inject theme values at composable level - centralized for all form inputs
  const injectedTheme = inject('formTheme', null)
  const injectedSize = inject('formSize', null)
  const injectedBorderRadius = inject('formBorderRadius', null)
  const injectedForm = inject('form', null)

  // Resolve theme values with proper reactivity
  const resolvedTheme = computed(() => {
    return props.theme || injectedTheme?.value || 'default'
  })

  const resolvedSize = computed(() => {
    return props.size || injectedSize?.value || 'md'
  })

  const resolvedBorderRadius = computed(() => {
    return props.borderRadius || injectedBorderRadius?.value || 'small'
  })

  const resolvedForm = computed(() => {
    return props.form || injectedForm?.value || null
  })

  const inputStyle = computed(() => {
    return {
      "--tw-ring-color": props.color,
    }
  })

  const hasValidation = computed(() => {
    return (
      resolvedForm.value !== null &&
      resolvedForm.value !== undefined &&
      _has(resolvedForm.value, "errors")
    )
  })

  const hasError = computed(() => {
    return hasValidation.value && resolvedForm.value?.errors?.has(props.name)
  })

  const compVal = computed({
    get: () => {
      if (resolvedForm.value) {
        return _get(resolvedForm.value, (composableOptions.formPrefixKey || "") + props.name)
      }
      return content.value
    },
    set: (val) => {
      if (resolvedForm.value) {
        const fullPath = (composableOptions.formPrefixKey || "") + props.name
        const pathParts = fullPath.split('.')
        
        // Ensure all parent objects exist before setting nested properties.
        // This is necessary for deeply nested form field paths (e.g., 'address.street.number')
        // where intermediate objects may not exist yet. Without this, lodash _set() would fail
        // or create the nested structure incorrectly on some form backends.
        // 
        // Edge case handling:
        // - If a path segment exists but is not an object (null, primitive value), it gets
        //   replaced with an empty object so the nested structure can be created.
        // - This ensures the form data structure is always valid for deeply nested fields.
        if (pathParts.length > 1) {
          let current = resolvedForm.value
          for (let i = 0; i < pathParts.length - 1; i++) {
            const part = pathParts[i]
            if (!current[part] || typeof current[part] !== 'object') {
              current[part] = {}
            }
            current = current[part]
          }
        }
        
        _set(resolvedForm.value, fullPath, val)
      } else {
        content.value = val
      }

      if (hasValidation.value) {
        resolvedForm.value.errors.clear(props.name)
      }

      context.emit("update:modelValue", compVal.value)
    },
  })

  const inputWrapperProps = computed(() => {
    const wrapperProps = {}
    Object.keys(inputProps).forEach((key) => {
      if (!["modelValue", "disabled", "placeholder", "color", "theme", "size", "borderRadius", "ui"].includes(key)) {
        wrapperProps[key] = props[key]
      }
    })
    // Add resolved theme values to wrapper props
    wrapperProps.theme = resolvedTheme.value
    wrapperProps.size = resolvedSize.value
    wrapperProps.borderRadius = resolvedBorderRadius.value
    wrapperProps.form = resolvedForm.value
    return wrapperProps
  })

  // CENTRALIZED VARIANTS: Single computed property for all tailwind-variants
  // Following Nuxt UI pattern: tv handles all merging via slot class parameter
  const ui = computed(() => {
    if (!composableOptions.variants) return {}
    
    // tv() slot functions handle ui merging via their class parameter
    // Components pass props.ui?.slots?.slotName via class: [ui.slot({ class: props.ui?.slots?.slotName })]
    return tv(composableOptions.variants, {
      twMerge: true  // Enabled by default, ensures conflict resolution
    })({
      theme: resolvedTheme.value,        // props.theme resolved with injection
      size: resolvedSize.value,
      borderRadius: resolvedBorderRadius.value,
      hasError: hasError.value,
      disabled: props.disabled,
      // Component-specific variants (e.g., loading, multiple, etc.)
      ...composableOptions.additionalVariants
    })
  })

  const onFocus = (event) => {
    context.emit('focus', event)
  }

  const onBlur = (event) => {
    context.emit('blur', event)
  }

  // Watch for changes in props.modelValue and update the local content
  watch(
    () => props.modelValue,
    (newValue) => {
      if (content.value !== newValue) {
        content.value = newValue
      }
    },
  )

  return {
    compVal,
    inputStyle,
    hasValidation,
    hasError,
    inputWrapperProps,
    onFocus,
    onBlur,
    // Resolved theme values - available to all form input components
    resolvedTheme,
    resolvedSize,
    resolvedBorderRadius,
    resolvedForm,
    // Centralized UI variants - ready to use in templates
    ui,
  }
}

