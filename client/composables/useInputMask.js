import { computed, toRef } from 'vue'

export function useInputMask(maskPattern, slotChar = '_') {
  // Convert to ref if not already reactive
  const mask = toRef(maskPattern)

  const maskTokens = {
    '9': /[0-9]/,
    'a': /[a-zA-Z]/,
    '*': /[a-zA-Z0-9]/
  }

  const parseMask = (maskValue) => {
    if (!maskValue) return []

    const tokens = []
    let optional = false

    for (let i = 0; i < maskValue.length; i++) {
      const char = maskValue[i]
      if (char === '?') {
        optional = true
        continue
      }

      tokens.push({
        char,
        regex: maskTokens[char] || null,
        literal: !maskTokens[char],
        optional
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

  const formatValue = (value) => {
    if (!mask.value || !value) return value

    const tokens = parsedMask.value
    const cleanValue = value.replace(/[^\w]/g, '')
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

  const getUnmaskedValue = (value) => {
    if (!value) return value
    return value.replace(/[^\w]/g, '')
  }

  const isComplete = (value) => {
    if (!mask.value) return true

    const tokens = parsedMask.value
    const requiredLength = tokens.filter(t => !t.literal && !t.optional).length
    const cleanValue = getUnmaskedValue(value)

    return cleanValue.length >= requiredLength
  }

  const isValidMask = computed(() => {
    if (!mask.value) return true
    return /^[9a*().?\s-]*$/.test(mask.value)
  })

  const getDisplayValue = (value) => {
    if (!mask.value) return value || ''

    const tokens = parsedMask.value
    const cleanValue = value ? value.replace(/[^\w]/g, '') : ''
    let display = ''
    let valueIndex = 0
    
    for (const token of tokens) {
      if (token.literal) {
        display += token.char
        continue
      }
      
      if (valueIndex >= cleanValue.length) {
        if (token.optional) {
          // For optional tokens, show underscore if we have a value but not enough characters
          if (cleanValue.length > 0) {
            display += slotChar
          }
        } else {
          // For required tokens, always show underscore
          display += slotChar
        }
        continue
      }
      
      if (token.regex && token.regex.test(cleanValue[valueIndex])) {
        display += cleanValue[valueIndex]
        valueIndex++
      } else if (!token.optional) {
        display += slotChar
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
