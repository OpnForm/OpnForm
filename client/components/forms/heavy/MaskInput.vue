<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <input
      ref="inputRef"
      :id="id ? id : name"
      v-model="displayValue"
      :disabled="disabled ? true : null"
      :type="nativeType"
      :autocomplete="autocomplete"
      :pattern="pattern"
      :style="inputStyle"
      :class="ui.input({ class: props.ui?.slots?.input })"
      :name="name"
      :accept="accept"
      :placeholder="effectivePlaceholder"
      :min="min"
      :max="max"
      :maxlength="maxCharLimit"
      :aria-label="mask ? `${label || name}, format: ${mask}` : (label || name)"
      :aria-describedby="mask && help ? `${name}-mask-help` : null"
      @change="onChange"
      @keydown.enter="onEnterPress"
      @paste="handlePaste"
      @focus="onFocus"
      @blur="onBlur"
    >

    <template
      v-if="$slots.help"
      #help
    >
      <slot name="help" />
    </template>

    <template
      v-if="maxCharLimit && showCharLimit"
      #bottom_after_help
    >
      <small :class="ui.help({ class: props.ui?.slots?.help })">
        {{ charCount }}/{{ maxCharLimit }}
      </small>
    </template>

    <template
      v-if="$slots.error"
      #error
    >
      <slot name="error" />
    </template>
  </input-wrapper>
</template>

<script>
import {inputProps, useFormInput} from "../useFormInput.js"
import { textInputTheme } from "~/lib/forms/themes/text-input.theme.js"

export default {
  name: "MaskInput",
  components: {},

  props: {
    ...inputProps,
    nativeType: {type: String, default: "text"},
    accept: {type: String, default: null},
    min: {type: Number, required: false, default: null},
    max: {type: Number, required: false, default: null},
    autocomplete: {type: [Boolean, String, Object], default: null},
    maxCharLimit: {type: Number, required: false, default: null},
    pattern: {type: String, default: null},
    preventEnter: { type: Boolean, default: true },
    mask: { type: String, default: null },
    slotChar: { type: String, default: '_' }
  },

  setup(props, context) {
    const { formatValue, isValidMask, getDisplayValue, getUnmaskedValue } = useInputMask(() => props.mask, props.slotChar)

    const { compVal } = useFormInput(
      props,
      context,
      {
        formPrefixKey: props.nativeType === "file" ? "file-" : null,
        variants: textInputTheme
      },
    )

    const maskedValue = ref('')
    const inputRef = ref(null)
    // Track if we're updating compVal internally to prevent watcher loops
    let isInternalUpdate = false

    const displayValue = computed({
      get() {
        if (props.mask && isValidMask.value) {
          return getDisplayValue(compVal.value)
        } else {
          return compVal.value
        }
      },
      set(newValue) {
        if (props.mask && isValidMask.value) {
          handleInput({ target: { value: newValue } })
        } else {
          compVal.value = newValue
        }
      }
    })

    const handleInput = (event) => {
      const inputValue = typeof event === 'string' ? event : event.target.value

      if (!props.mask || !isValidMask.value) {
        // No mask or invalid mask - behave as normal input
        compVal.value = inputValue
        return
      }

      // Store cursor position before processing
      const cursorBefore = inputRef.value?.selectionStart || 0
      
      // Remove slot characters from input value for processing
      const slotCharRegex = new RegExp(props.slotChar.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'g')
      const cleanInputValue = inputValue.replace(slotCharRegex, '')
      
      // Get the previous formatted value to compare
      const previousFormatted = maskedValue.value
      const formatted = formatValue(cleanInputValue)

      // If the formatted value is the same as before, it means the new character was invalid
      // In this case, we should revert to the previous state
      if (formatted === previousFormatted && cleanInputValue.length > previousFormatted.replace(/[^a-zA-Z0-9]/g, '').length) {
        // Invalid character entered - revert the input
        nextTick(() => {
          if (inputRef.value) {
            const cursorPos = Math.max(0, cursorBefore - 1)
            inputRef.value.value = getDisplayValue(previousFormatted)
            // Set cursor position to where it was before the invalid character
            if (inputRef.value.setSelectionRange && cursorPos >= 0) {
              inputRef.value.setSelectionRange(cursorPos, cursorPos)
            }
          }
        })
        return
      }

      // Valid input - update the values
      maskedValue.value = formatted
      isInternalUpdate = true
      compVal.value = formatted

      // Calculate new cursor position based on how many characters were added/removed
      const previousUnmaskedLength = previousFormatted.replace(/[^a-zA-Z0-9]/g, '').length
      const newUnmaskedLength = formatted.replace(/[^a-zA-Z0-9]/g, '').length
      const charsAdded = newUnmaskedLength - previousUnmaskedLength
      
      // Update input display value
      nextTick(() => {
        if (inputRef.value) {
          const displayValue = getDisplayValue(formatted)
          if (inputRef.value.value !== displayValue) {
            // Calculate cursor position: try to maintain relative position
            let newCursorPos = cursorBefore
            if (charsAdded > 0) {
              // Character added - move cursor forward
              newCursorPos = Math.min(displayValue.length, cursorBefore + charsAdded)
            } else if (charsAdded < 0) {
              // Character removed - keep cursor at same position
              newCursorPos = Math.max(0, cursorBefore + charsAdded)
            }
            
            inputRef.value.value = displayValue
            // Set cursor position
            if (inputRef.value.setSelectionRange) {
              inputRef.value.setSelectionRange(newCursorPos, newCursorPos)
            }
          }
        }
      })
    }

    const handlePaste = (event) => {
      if (!props.mask || !isValidMask.value) {
        return // Let default paste behavior handle it
      }

      event.preventDefault()
      const pastedText = (event.clipboardData || window.clipboardData).getData('text')
      
      if (pastedText) {
        // Format the pasted text according to the mask
        const formatted = formatValue(pastedText)
        maskedValue.value = formatted
        isInternalUpdate = true
        compVal.value = formatted
        
        // Update display
        nextTick(() => {
          if (inputRef.value) {
            const displayValue = getDisplayValue(formatted)
            inputRef.value.value = displayValue
            // Set cursor to end of formatted value
            const cursorPos = displayValue.length
            if (inputRef.value.setSelectionRange) {
              inputRef.value.setSelectionRange(cursorPos, cursorPos)
            }
          }
        })
      }
    }

    const effectivePlaceholder = computed(() => {
      if (props.placeholder) return props.placeholder
      if (props.mask && isValidMask.value) return getDisplayValue('')
      return null
    })

    // Watch for mask changes (form editor support)
    watch(() => props.mask, (newMask) => {
      if (!newMask) {
        maskedValue.value = compVal.value || ''
      } else if (compVal.value && isValidMask.value) {
        // Reformat existing value with new mask
        isInternalUpdate = true
        const reformatted = formatValue(compVal.value)
        maskedValue.value = reformatted
        compVal.value = reformatted
      }
    })

    // Watch for compVal changes from parent (but skip if we're the ones updating it)
    watch(compVal, (newVal) => {
      // Skip if this update was triggered by our own handleInput
      if (isInternalUpdate) {
        isInternalUpdate = false
        return
      }
      
      if (props.mask && isValidMask.value && newVal) {
        maskedValue.value = formatValue(newVal)
      } else {
        maskedValue.value = newVal || ''
      }
    }, { immediate: true })


    const onChange = (event) => {
      if (props.nativeType !== "file") return

      const file = event.target.files[0]
       
      props.form[props.name] = file
    }

    const onEnterPress = (event) => {
      if (props.preventEnter) {
        event.preventDefault()
      }
      context.emit('input-filled')
      return false
    }

    return {
      ...useFormInput(
        props,
        context,
        {
          formPrefixKey: props.nativeType === "file" ? "file-" : null,
          variants: textInputTheme
        },
      ),
      onEnterPress,
      onChange,
      handleInput,
      handlePaste,
      maskedValue,
      effectivePlaceholder,
      inputRef,
      isValidMask,
      displayValue,
      getUnmaskedValue,
      props
    }
  },
  computed: {
    charCount() {
      if (!this.compVal) return 0
      // If mask is active, count only unmasked characters (exclude mask literals)
      if (this.mask && this.isValidMask) {
        const unmasked = this.getUnmaskedValue(this.compVal)
        return unmasked ? unmasked.length : 0
      }
      return this.compVal.length
    },
  },
}
</script>
