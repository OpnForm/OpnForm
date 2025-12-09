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
      @change="onChange"
      @keydown.enter="onEnterPress"
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
      const inputValue = event.target.value

      if (!props.mask || !isValidMask.value) {
        // No mask or invalid mask - behave as normal input
        compVal.value = inputValue
        return
      }

      // Remove underscores from input value for processing
      const cleanInputValue = inputValue.replace(/_/g, '')
      
      // Get the previous formatted value to compare
      const previousFormatted = maskedValue.value
      const formatted = formatValue(cleanInputValue)

      // If the formatted value is the same as before, it means the new character was invalid
      // In this case, we should revert to the previous state
      if (formatted === previousFormatted && cleanInputValue.length > previousFormatted.length) {
        // Invalid character entered - revert the input
        nextTick(() => {
          if (inputRef.value) {
            const cursorPos = inputRef.value.selectionStart - 1
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
      compVal.value = formatted

      // Update input display value
      nextTick(() => {
        if (inputRef.value) {
          const displayValue = getDisplayValue(formatted)
          if (inputRef.value.value !== displayValue) {
            const cursorPos = inputRef.value.selectionStart
            inputRef.value.value = displayValue
            // Maintain cursor position logic here
            if (inputRef.value.setSelectionRange) {
              inputRef.value.setSelectionRange(cursorPos, cursorPos)
            }
          }
        }
      })
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
      } else if (compVal.value) {
        // Reformat existing value with new mask
        maskedValue.value = formatValue(compVal.value)
      }
    })

    // Watch for compVal changes from parent
    watch(compVal, (newVal) => {
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
