/**
 * Text functions for the formula engine
 */

/**
 * Convert value to string
 */
function toString(value) {
  if (value === null || value === undefined) {
    return ''
  }
  return String(value)
}

/**
 * CONCAT - Join strings together
 */
export function CONCAT(...values) {
  return values.map(v => toString(v)).join('')
}

/**
 * UPPER - Convert to uppercase
 */
export function UPPER(value) {
  return toString(value).toUpperCase()
}

/**
 * LOWER - Convert to lowercase
 */
export function LOWER(value) {
  return toString(value).toLowerCase()
}

/**
 * TRIM - Remove leading and trailing whitespace
 */
export function TRIM(value) {
  return toString(value).trim()
}

/**
 * LEFT - Get first N characters
 */
export function LEFT(value, count) {
  const str = toString(value)
  const n = Number(count)
  if (isNaN(n) || n < 0) return ''
  return str.substring(0, n)
}

/**
 * RIGHT - Get last N characters
 */
export function RIGHT(value, count) {
  const str = toString(value)
  const n = Number(count)
  if (isNaN(n) || n < 0) return ''
  return str.substring(Math.max(0, str.length - n))
}

/**
 * MID - Get substring from position
 */
export function MID(value, start, length) {
  const str = toString(value)
  const s = Number(start)
  const l = Number(length)
  if (isNaN(s) || isNaN(l) || s < 1 || l < 0) return ''
  // MID uses 1-based indexing (Excel-style)
  return str.substring(s - 1, s - 1 + l)
}

/**
 * LEN - Get string length
 */
export function LEN(value) {
  return toString(value).length
}

/**
 * SUBSTITUTE - Replace occurrences of text
 */
export function SUBSTITUTE(text, oldText, newText, instance = null) {
  const str = toString(text)
  const old = toString(oldText)
  const replacement = toString(newText)
  
  if (old === '') return str
  
  if (instance === null) {
    // Replace all occurrences
    return str.split(old).join(replacement)
  }
  
  // Replace specific instance
  const n = Number(instance)
  if (isNaN(n) || n < 1) return str
  
  let count = 0
  let result = ''
  let lastIndex = 0
  let index = str.indexOf(old)
  
  while (index !== -1) {
    count++
    if (count === n) {
      result += str.substring(lastIndex, index) + replacement
      lastIndex = index + old.length
      break
    }
    result += str.substring(lastIndex, index + old.length)
    lastIndex = index + old.length
    index = str.indexOf(old, lastIndex)
  }
  
  result += str.substring(lastIndex)
  return result
}

/**
 * REPLACE - Replace characters at position
 */
export function REPLACE(text, start, length, newText) {
  const str = toString(text)
  const s = Number(start)
  const l = Number(length)
  const replacement = toString(newText)
  
  if (isNaN(s) || isNaN(l) || s < 1 || l < 0) return str
  
  // REPLACE uses 1-based indexing
  return str.substring(0, s - 1) + replacement + str.substring(s - 1 + l)
}

/**
 * FIND - Find position of text (case-sensitive)
 */
export function FIND(findText, withinText, startPos = 1) {
  const find = toString(findText)
  const within = toString(withinText)
  const start = Number(startPos)
  
  if (isNaN(start) || start < 1) return null
  
  // FIND uses 1-based indexing
  const index = within.indexOf(find, start - 1)
  return index === -1 ? null : index + 1
}

/**
 * SEARCH - Find position of text (case-insensitive)
 */
export function SEARCH(findText, withinText, startPos = 1) {
  const find = toString(findText).toLowerCase()
  const within = toString(withinText).toLowerCase()
  const start = Number(startPos)
  
  if (isNaN(start) || start < 1) return null
  
  const index = within.indexOf(find, start - 1)
  return index === -1 ? null : index + 1
}

/**
 * REPT - Repeat text N times
 */
export function REPT(text, times) {
  const str = toString(text)
  const n = Number(times)
  if (isNaN(n) || n < 0) return ''
  return str.repeat(Math.floor(n))
}

/**
 * TEXT - Format number as text (simplified)
 */
export function TEXT(value, format = '') {
  if (value === null || value === undefined) return ''
  
  const num = Number(value)
  if (isNaN(num)) return toString(value)
  
  // Simple format support
  const fmt = toString(format).toLowerCase()
  
  if (fmt.includes('%')) {
    // Percentage format
    const decimals = (fmt.match(/0/g) || []).length - 1
    return (num * 100).toFixed(Math.max(0, decimals)) + '%'
  }
  
  if (fmt.includes('.')) {
    // Decimal format
    const decimals = fmt.split('.')[1]?.length || 0
    return num.toFixed(decimals)
  }
  
  return toString(value)
}

export const textFunctions = {
  CONCAT,
  UPPER,
  LOWER,
  TRIM,
  LEFT,
  RIGHT,
  MID,
  LEN,
  SUBSTITUTE,
  REPLACE,
  FIND,
  SEARCH,
  REPT,
  TEXT
}
