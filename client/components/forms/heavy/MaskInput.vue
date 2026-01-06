<template>
  <input-wrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <input
      ref="inputRef"
      :id="id ? id : name"
      :value="displayValue"
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
      :aria-label="mask && isValidMask ? `${label || name}, expected format: ${getMaskPlaceholder}` : (label || name)"
      :aria-describedby="mask && help ? `${name}-mask-help` : null"
      @input="onInput"
      @change="onChange"
      @keydown="onKeyDown"
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

<script setup>
import { inputProps, useFormInput } from "../useFormInput.js"
import { textInputTheme } from "~/lib/forms/themes/text-input.theme.js"

const props = defineProps({
  ...inputProps,
  nativeType: { type: String, default: "text" },
  accept: { type: String, default: null },
  min: { type: Number, required: false, default: null },
  max: { type: Number, required: false, default: null },
  autocomplete: { type: [Boolean, String, Object], default: null },
  maxCharLimit: { type: Number, required: false, default: null },
  pattern: { type: String, default: null },
  preventEnter: { type: Boolean, default: true },
  mask: { type: String, default: null },
  slotChar: { type: String, default: '_' }
})

const emit = defineEmits(['input-filled'])

const { formatValue, isValidMask, getDisplayValue, getUnmaskedValue, getMaskPlaceholder, parsedMask } = useInputMask(() => props.mask, props.slotChar)

const {
  compVal,
  inputWrapperProps,
  inputStyle,
  ui,
  onFocus,
  onBlur,
  showCharLimit
} = useFormInput(props, { emit }, {
  formPrefixKey: props.nativeType === "file" ? "file-" : null,
  variants: textInputTheme
})

const inputRef = ref(null)
// Track if we're updating compVal internally to prevent watcher loops
const isInternalUpdate = ref(false)

// Computed display value (read-only for template)
const displayValue = computed(() => {
  if (props.mask && isValidMask.value) {
    return getDisplayValue(compVal.value)
  }
  return compVal.value || ''
})

// Character count for limit display
const charCount = computed(() => {
  if (!compVal.value) return 0
  // If mask is active, count only unmasked characters (exclude mask literals)
  if (props.mask && isValidMask.value) {
    const unmasked = getUnmaskedValue(compVal.value)
    return unmasked ? unmasked.length : 0
  }
  return compVal.value.length
})

/**
 * Find the next input slot position (non-literal) at or after the given display position
 */
const findNextInputSlot = (displayPos) => {
  const tokens = parsedMask.value
  let pos = 0
  for (let i = 0; i < tokens.length; i++) {
    if (pos >= displayPos && !tokens[i].literal) {
      return pos
    }
    pos++
  }
  return pos
}

/**
 * Find the previous input slot position (non-literal) before the given display position
 */
const findPrevInputSlot = (displayPos) => {
  const tokens = parsedMask.value
  let lastInputPos = -1
  let pos = 0
  for (let i = 0; i < tokens.length; i++) {
    if (pos >= displayPos) break
    if (!tokens[i].literal) {
      lastInputPos = pos
    }
    pos++
  }
  return lastInputPos
}

/**
 * Convert display position to the index in unmasked value
 */
const displayPosToValueIndex = (displayPos) => {
  const tokens = parsedMask.value
  let valueIndex = 0
  for (let i = 0; i < Math.min(displayPos, tokens.length); i++) {
    if (!tokens[i].literal) {
      valueIndex++
    }
  }
  return valueIndex
}

/**
 * Convert unmasked value index to display position
 */
const valueIndexToDisplayPos = (valueIndex) => {
  const tokens = parsedMask.value
  let currentValueIndex = 0
  for (let i = 0; i < tokens.length; i++) {
    if (!tokens[i].literal) {
      if (currentValueIndex === valueIndex) {
        return i
      }
      currentValueIndex++
    }
  }
  return tokens.length
}

/**
 * Update value and set cursor position
 */
const updateValue = (unmaskedValue, cursorPos) => {
  const formatted = formatValue(unmaskedValue)
  isInternalUpdate.value = true
  compVal.value = formatted

  nextTick(() => {
    if (inputRef.value) {
      const display = getDisplayValue(formatted)
      inputRef.value.value = display
      const safePos = Math.min(cursorPos, display.length)
      inputRef.value.setSelectionRange(safePos, safePos)
    }
  })
}

/**
 * Handle regular input event
 */
const onInput = (event) => {
  if (!props.mask || !isValidMask.value) {
    compVal.value = event.target.value
    return
  }

  // Extract raw characters from input
  const inputValue = event.target.value
  const cleanInput = inputValue.replace(/[^a-zA-Z0-9]/g, '')

  // Format and update
  const formatted = formatValue(cleanInput)
  isInternalUpdate.value = true
  compVal.value = formatted

  // Restore display and set cursor to end of entered content
  nextTick(() => {
    if (inputRef.value) {
      const display = getDisplayValue(formatted)
      inputRef.value.value = display
      // Position cursor after the last filled slot
      const cursorPos = valueIndexToDisplayPos(cleanInput.length)
      inputRef.value.setSelectionRange(cursorPos, cursorPos)
    }
  })
}

/**
 * Handle keydown for special keys (backspace, delete, arrows)
 */
const onKeyDown = (event) => {
  if (!props.mask || !isValidMask.value) return

  const cursorStart = inputRef.value?.selectionStart || 0
  const cursorEnd = inputRef.value?.selectionEnd || 0
  const hasSelection = cursorStart !== cursorEnd
  const currentUnmasked = getUnmaskedValue(compVal.value) || ''

  if (event.key === 'Backspace') {
    event.preventDefault()

    if (hasSelection) {
      // Delete selection: remove chars between selection
      const startIndex = displayPosToValueIndex(cursorStart)
      const endIndex = displayPosToValueIndex(cursorEnd)
      const newValue = currentUnmasked.slice(0, startIndex) + currentUnmasked.slice(endIndex)
      updateValue(newValue, valueIndexToDisplayPos(startIndex))
    } else if (cursorStart > 0) {
      // Find previous input slot and delete that character
      const prevSlot = findPrevInputSlot(cursorStart)
      if (prevSlot >= 0) {
        const valueIndex = displayPosToValueIndex(prevSlot + 1) - 1
        if (valueIndex >= 0) {
          const newValue = currentUnmasked.slice(0, valueIndex) + currentUnmasked.slice(valueIndex + 1)
          updateValue(newValue, prevSlot)
        }
      }
    }
    return
  }

  if (event.key === 'Delete') {
    event.preventDefault()

    if (hasSelection) {
      // Delete selection
      const startIndex = displayPosToValueIndex(cursorStart)
      const endIndex = displayPosToValueIndex(cursorEnd)
      const newValue = currentUnmasked.slice(0, startIndex) + currentUnmasked.slice(endIndex)
      updateValue(newValue, valueIndexToDisplayPos(startIndex))
    } else {
      // Delete character at current input slot
      const currentSlot = findNextInputSlot(cursorStart)
      const valueIndex = displayPosToValueIndex(currentSlot + 1) - 1
      if (valueIndex >= 0 && valueIndex < currentUnmasked.length) {
        const newValue = currentUnmasked.slice(0, valueIndex) + currentUnmasked.slice(valueIndex + 1)
        updateValue(newValue, cursorStart)
      }
    }
    return
  }

  // Handle typing a character
  if (event.key.length === 1 && !event.ctrlKey && !event.metaKey) {
    event.preventDefault()

    const char = event.key
    const tokens = parsedMask.value

    // Find the slot where we're inserting
    const insertSlot = findNextInputSlot(cursorStart)
    if (insertSlot >= tokens.length) return // No more slots

    const token = tokens[insertSlot]

    // Check if character matches the mask pattern
    if (token && token.regex && token.regex.test(char)) {
      const valueIndex = displayPosToValueIndex(insertSlot)

      // Insert or replace character
      let newValue
      if (hasSelection) {
        const startIndex = displayPosToValueIndex(cursorStart)
        const endIndex = displayPosToValueIndex(cursorEnd)
        newValue = currentUnmasked.slice(0, startIndex) + char + currentUnmasked.slice(endIndex)
      } else {
        // Insert at position (or append)
        newValue = currentUnmasked.slice(0, valueIndex) + char + currentUnmasked.slice(valueIndex)
      }

      // Limit to mask capacity
      const maxChars = tokens.filter(t => !t.literal).length
      if (newValue.length > maxChars) {
        newValue = newValue.slice(0, maxChars)
      }

      // Calculate new cursor position (after the inserted char, skip literals)
      let newCursorPos = insertSlot + 1
      while (newCursorPos < tokens.length && tokens[newCursorPos].literal) {
        newCursorPos++
      }

      updateValue(newValue, newCursorPos)
    }
    // If char doesn't match, do nothing (ignore invalid input)
  }
}

/**
 * Handle paste event
 */
const handlePaste = (event) => {
  if (!props.mask || !isValidMask.value) {
    return // Let default paste behavior handle it
  }

  event.preventDefault()
  const pastedText = (event.clipboardData || window.clipboardData).getData('text')

  if (pastedText) {
    const formatted = formatValue(pastedText)
    isInternalUpdate.value = true
    compVal.value = formatted

    nextTick(() => {
      if (inputRef.value) {
        const display = getDisplayValue(formatted)
        inputRef.value.value = display
        // Set cursor to end of pasted content
        const unmaskedLength = getUnmaskedValue(formatted)?.length || 0
        const cursorPos = valueIndexToDisplayPos(unmaskedLength)
        inputRef.value.setSelectionRange(cursorPos, cursorPos)
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
    return
  } else if (compVal.value && isValidMask.value) {
    // Reformat existing value with new mask
    isInternalUpdate.value = true
    const reformatted = formatValue(compVal.value)
    compVal.value = reformatted
  }
})

// Watch for compVal changes from parent (but skip if we're the ones updating it)
watch(compVal, (newVal) => {
  if (isInternalUpdate.value) {
    isInternalUpdate.value = false
    return
  }

  // External update - reformat if needed
  if (props.mask && isValidMask.value && newVal) {
    const reformatted = formatValue(newVal)
    if (reformatted !== newVal) {
      isInternalUpdate.value = true
      compVal.value = reformatted
    }
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
  emit('input-filled')
  return false
}
</script>
