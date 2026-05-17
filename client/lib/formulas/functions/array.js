/**
 * Array/Collection functions for the formula engine
 * Handles multi_select, files, and other array-type fields
 */

/**
 * COUNT - Returns number of elements in array
 * For non-arrays, returns 1 for non-empty values, 0 for null/undefined
 */
export function COUNT(value) {
  if (value === null || value === undefined) return 0
  if (Array.isArray(value)) return value.length
  if (typeof value === 'string') return value.length > 0 ? 1 : 0
  return 1
}

/**
 * ISEMPTY - Returns true if value is empty
 * Handles null, undefined, empty strings, and empty arrays
 */
export function ISEMPTY(value) {
  if (value === null || value === undefined) return true
  if (Array.isArray(value)) return value.length === 0
  if (typeof value === 'string') return value.trim() === ''
  return false
}

/**
 * CONTAINS - Returns true if array contains the search value
 * Case-sensitive comparison
 * For non-arrays, checks equality
 */
export function CONTAINS(array, searchValue) {
  if (array === null || array === undefined) return false
  if (!Array.isArray(array)) {
    // For non-arrays, check direct equality
    return array === searchValue
  }
  return array.includes(searchValue)
}

/**
 * JOIN - Joins array elements into a string with separator
 * For non-arrays, returns the value as string
 */
export function JOIN(array, separator = ', ') {
  if (array === null || array === undefined) return ''
  if (!Array.isArray(array)) return String(array)
  return array.join(separator)
}

export const arrayFunctions = {
  COUNT,
  ISEMPTY,
  CONTAINS,
  JOIN
}

