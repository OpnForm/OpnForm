/**
 * Logic functions for the formula engine
 */

/**
 * Check if value is "truthy" for formula purposes
 */
function isTruthy(value) {
  if (value === null || value === undefined || value === '') {
    return false
  }
  if (typeof value === 'boolean') {
    return value
  }
  if (typeof value === 'number') {
    return value !== 0
  }
  if (typeof value === 'string') {
    const lower = value.toLowerCase()
    if (lower === 'false' || lower === 'no' || lower === '0') {
      return false
    }
    return true
  }
  return Boolean(value)
}

/**
 * Check if value is blank/empty
 */
function isBlankValue(value) {
  return value === null || value === undefined || value === ''
}

/**
 * IF - Conditional expression
 */
export function IF(condition, valueIfTrue, valueIfFalse = '') {
  return isTruthy(condition) ? valueIfTrue : valueIfFalse
}

/**
 * AND - All conditions must be true
 */
export function AND(...conditions) {
  if (conditions.length === 0) return true
  return conditions.every(c => isTruthy(c))
}

/**
 * OR - At least one condition must be true
 */
export function OR(...conditions) {
  if (conditions.length === 0) return false
  return conditions.some(c => isTruthy(c))
}

/**
 * NOT - Negate a condition
 */
export function NOT(condition) {
  return !isTruthy(condition)
}

/**
 * XOR - Exclusive or (odd number of true values)
 */
export function XOR(...conditions) {
  const trueCount = conditions.filter(c => isTruthy(c)).length
  return trueCount % 2 === 1
}

/**
 * ISBLANK - Check if value is empty
 */
export function ISBLANK(value) {
  return isBlankValue(value)
}

/**
 * ISNUMBER - Check if value is a number
 */
export function ISNUMBER(value) {
  if (value === null || value === undefined || value === '') {
    return false
  }
  return typeof value === 'number' || !isNaN(Number(value))
}

/**
 * ISTEXT - Check if value is text
 */
export function ISTEXT(value) {
  return typeof value === 'string' && value !== ''
}

/**
 * IFERROR - Return fallback if value is an error
 */
export function IFERROR(value, fallback = '') {
  // In our context, null/undefined represents errors
  if (value === null || value === undefined) {
    return fallback
  }
  return value
}

/**
 * IFBLANK - Return fallback if value is blank
 */
export function IFBLANK(value, fallback = '') {
  if (isBlankValue(value)) {
    return fallback
  }
  return value
}

/**
 * COALESCE - Return first non-empty value
 */
export function COALESCE(...values) {
  for (const value of values) {
    if (!isBlankValue(value)) {
      return value
    }
  }
  return null
}

/**
 * SWITCH - Match value against cases
 * SWITCH(value, case1, result1, case2, result2, ..., [default])
 */
export function SWITCH(value, ...caseResults) {
  for (let i = 0; i < caseResults.length - 1; i += 2) {
    if (value === caseResults[i]) {
      return caseResults[i + 1]
    }
  }
  // Return default if provided (odd number of arguments after value)
  if (caseResults.length % 2 === 1) {
    return caseResults[caseResults.length - 1]
  }
  return null
}

/**
 * IFS - Multiple conditions
 * IFS(condition1, result1, condition2, result2, ...)
 */
export function IFS(...conditionResults) {
  for (let i = 0; i < conditionResults.length; i += 2) {
    if (isTruthy(conditionResults[i])) {
      return conditionResults[i + 1]
    }
  }
  return null
}

/**
 * CHOOSE - Return value at index
 */
export function CHOOSE(index, ...values) {
  const idx = Number(index)
  if (isNaN(idx) || idx < 1 || idx > values.length) {
    return null
  }
  return values[idx - 1]
}

export const logicFunctions = {
  IF,
  AND,
  OR,
  NOT,
  XOR,
  ISBLANK,
  ISNUMBER,
  ISTEXT,
  IFERROR,
  IFBLANK,
  COALESCE,
  SWITCH,
  IFS,
  CHOOSE
}
