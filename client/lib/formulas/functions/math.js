/**
 * Math functions for the formula engine
 */

/**
 * Convert value to number, returning null if not possible
 */
function toNumber(value) {
  if (value === null || value === undefined || value === '') {
    return null
  }
  const num = Number(value)
  return isNaN(num) ? null : num
}

/**
 * Filter out null values and convert to numbers
 */
function getNumbers(values) {
  return values
    .map(v => toNumber(v))
    .filter(v => v !== null)
}

/**
 * SUM - Add all numbers together
 */
export function SUM(...values) {
  const numbers = getNumbers(values.flat())
  if (numbers.length === 0) return 0
  return numbers.reduce((sum, n) => sum + n, 0)
}

/**
 * AVERAGE - Calculate arithmetic mean
 */
export function AVERAGE(...values) {
  const numbers = getNumbers(values.flat())
  if (numbers.length === 0) return null
  return SUM(...numbers) / numbers.length
}

/**
 * MIN - Return smallest value
 */
export function MIN(...values) {
  const numbers = getNumbers(values.flat())
  if (numbers.length === 0) return null
  return Math.min(...numbers)
}

/**
 * MAX - Return largest value
 */
export function MAX(...values) {
  const numbers = getNumbers(values.flat())
  if (numbers.length === 0) return null
  return Math.max(...numbers)
}

/**
 * ROUND - Round to specified decimal places
 */
export function ROUND(value, decimals = 0) {
  const num = toNumber(value)
  if (num === null) return null
  const dec = toNumber(decimals) ?? 0
  const factor = Math.pow(10, dec)
  return Math.round(num * factor) / factor
}

/**
 * FLOOR - Round down to integer
 */
export function FLOOR(value) {
  const num = toNumber(value)
  if (num === null) return null
  return Math.floor(num)
}

/**
 * CEIL - Round up to integer
 */
export function CEIL(value) {
  const num = toNumber(value)
  if (num === null) return null
  return Math.ceil(num)
}

/**
 * ABS - Absolute value
 */
export function ABS(value) {
  const num = toNumber(value)
  if (num === null) return null
  return Math.abs(num)
}

/**
 * MOD - Modulo (remainder)
 */
export function MOD(value, divisor) {
  const num = toNumber(value)
  const div = toNumber(divisor)
  if (num === null || div === null || div === 0) return null
  return num % div
}

/**
 * POWER - Raise to power
 */
export function POWER(base, exponent) {
  const b = toNumber(base)
  const e = toNumber(exponent)
  if (b === null || e === null) return null
  return Math.pow(b, e)
}

/**
 * SQRT - Square root
 */
export function SQRT(value) {
  const num = toNumber(value)
  if (num === null || num < 0) return null
  return Math.sqrt(num)
}

export const mathFunctions = {
  SUM,
  AVERAGE,
  MIN,
  MAX,
  ROUND,
  FLOOR,
  CEIL,
  ABS,
  MOD,
  POWER,
  SQRT
}
