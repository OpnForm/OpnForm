import { computed, toRef } from 'vue'

/**
 * Mask pattern tokens mapping
 * @constant
 */
const MASK_PATTERNS = {
  '9': /[0-9]/,
  'a': /[a-zA-Z]/,
  '*': /[a-zA-Z0-9]/
}

/**
 * Regex pattern to validate mask format
 * Allows: 9 (digit), a (letter), * (alphanumeric), ? (optional), and common punctuation
 * @constant
 */
const MASK_VALIDATION_REGEX = /^[9a*().\s\-?]*$/

/**
 * Composable for handling input masking functionality
 * @param {string|import('vue').Ref<string>|Function} maskPattern - The mask pattern (e.g., "(999) 999-9999")
 * @param {string|import('vue').Ref<string>|Function} slotCharParam - Character to display for empty slots (default: '_')
 * @returns {Object} Mask utility functions
 */
export function useInputMask(maskPattern, slotCharParam = '_') {
  // Convert to ref if not already reactive
  const mask = toRef(maskPattern)
  const slotCharRef = toRef(slotCharParam)
  // Computed to handle the slot char with fallback
  const slotChar = computed(() => slotCharRef.value || '_')

  const parseMask = (maskValue) => {
    if (!maskValue) return []

    const tokens = []

    for (let i = 0; i < maskValue.length; i++) {
      const char = maskValue[i]
      
      // If we encounter '?', mark the previous token as optional
      if (char === '?') {
        if (tokens.length > 0) {
          tokens[tokens.length - 1].optional = true
        }
        continue
      }

      tokens.push({
        char,
        regex: MASK_PATTERNS[char] || null,
        literal: !MASK_PATTERNS[char],
        optional: false
      })
    }
    return tokens
  }

  // Reactive computed properties
  const parsedMask = computed(() => parseMask(mask.value))

  const getMaskPlaceholder = computed(() => {
    if (!mask.value) return ''

    return parsedMask.value.map(token => {
      if (token.literal) return token.char
      if (token.optional) return ''
      return token.char
    }).join('')
  })

  /**
   * Formats a raw input value according to the mask pattern
   * Removes non-alphanumeric characters and applies mask formatting
   * @param {string} value - The raw input value
   * @returns {string} The formatted value with mask literals included
   */
  const formatValue = (value) => {
    if (!mask.value || !value) return value

    const tokens = parsedMask.value
    // Remove all non-alphanumeric characters (including slotChar)
    // This ensures we only process actual input characters
    const cleanValue = value.replace(/[^a-zA-Z0-9]/g, '')
    let formatted = ''
    let valueIndex = 0
    
    for (const token of tokens) {
      if (token.literal) {
        formatted += token.char
        continue
      }
      
      if (valueIndex >= cleanValue.length) {
        if (token.optional) break
        continue
      }
      
      if (token.regex && token.regex.test(cleanValue[valueIndex])) {
        formatted += cleanValue[valueIndex]
        valueIndex++
      } else if (!token.optional) {
        break
      }
    }
    
    return formatted
  }

  /**
   * Extracts only the alphanumeric characters from a masked value
   * Removes all mask literals and slot characters
   * @param {string} value - The masked value
   * @returns {string} The unmasked value (alphanumeric only)
   */
  const getUnmaskedValue = (value) => {
    if (!value) return value
    // Remove all non-alphanumeric characters (including mask literals and slotChar)
    return value.replace(/[^a-zA-Z0-9]/g, '')
  }

  const isComplete = (value) => {
    if (!mask.value) return true

    const tokens = parsedMask.value
    const requiredLength = tokens.filter(t => !t.literal && !t.optional).length
    const cleanValue = getUnmaskedValue(value)

    return cleanValue.length >= requiredLength
  }

  /**
   * Validates if the mask pattern is valid
   * @returns {boolean} True if mask is valid or empty
   */
  const isValidMask = computed(() => {
    if (!mask.value) return true
    return MASK_VALIDATION_REGEX.test(mask.value)
  })

  /**
   * Generates the display value with slot characters for empty positions
   * @param {string} value - The current value (may be masked or unmasked)
   * @returns {string} The display value with slot characters for empty positions
   */
  const getDisplayValue = (value) => {
    if (!mask.value) return value || ''

    const tokens = parsedMask.value
    // Extract only alphanumeric characters from the value
    const cleanValue = value ? value.replace(/[^a-zA-Z0-9]/g, '') : ''
    let display = ''
    let valueIndex = 0
    const slot = slotChar.value
    
    for (const token of tokens) {
      if (token.literal) {
        display += token.char
        continue
      }
      
      if (valueIndex >= cleanValue.length) {
        if (token.optional) {
          // For optional tokens, show slotChar only if we have some value
          if (cleanValue.length > 0) {
            display += slot
          }
        } else {
          // For required tokens, always show slotChar
          display += slot
        }
        continue
      }
      
      if (token.regex && token.regex.test(cleanValue[valueIndex])) {
        display += cleanValue[valueIndex]
        valueIndex++
      } else if (!token.optional) {
        display += slot
      }
    }
    
    return display
  }

  return {
    formatValue,
    getUnmaskedValue,
    isComplete,
    getMaskPlaceholder,
    parsedMask,
    isValidMask,
    getDisplayValue
  }
}
