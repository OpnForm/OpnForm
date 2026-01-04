/**
 * Function registry for the formula engine
 * Exports all available functions and metadata
 */

import { mathFunctions } from './math.js'
import { textFunctions } from './text.js'
import { logicFunctions } from './logic.js'

/**
 * All available functions
 */
export const functions = {
  ...mathFunctions,
  ...textFunctions,
  ...logicFunctions
}

/**
 * Function metadata for documentation and autocomplete
 */
export const functionMeta = {
  // Math functions
  SUM: {
    category: 'math',
    signature: 'SUM(value1, value2, ...)',
    description: 'Adds all numbers together',
    examples: ['SUM(1, 2, 3) → 6', 'SUM({Price}, {Tax}) → total']
  },
  AVERAGE: {
    category: 'math',
    signature: 'AVERAGE(value1, value2, ...)',
    description: 'Returns the arithmetic mean of the values',
    examples: ['AVERAGE(1, 2, 3) → 2', 'AVERAGE({Score1}, {Score2}) → mean']
  },
  MIN: {
    category: 'math',
    signature: 'MIN(value1, value2, ...)',
    description: 'Returns the smallest value',
    examples: ['MIN(5, 3, 8) → 3', 'MIN({Price1}, {Price2}) → lowest']
  },
  MAX: {
    category: 'math',
    signature: 'MAX(value1, value2, ...)',
    description: 'Returns the largest value',
    examples: ['MAX(5, 3, 8) → 8', 'MAX({Score1}, {Score2}) → highest']
  },
  ROUND: {
    category: 'math',
    signature: 'ROUND(number, decimals?)',
    description: 'Rounds a number to the specified decimal places',
    examples: ['ROUND(3.7) → 4', 'ROUND(3.14159, 2) → 3.14']
  },
  FLOOR: {
    category: 'math',
    signature: 'FLOOR(number)',
    description: 'Rounds down to the nearest integer',
    examples: ['FLOOR(3.7) → 3', 'FLOOR(-2.1) → -3']
  },
  CEIL: {
    category: 'math',
    signature: 'CEIL(number)',
    description: 'Rounds up to the nearest integer',
    examples: ['CEIL(3.2) → 4', 'CEIL(-2.9) → -2']
  },
  ABS: {
    category: 'math',
    signature: 'ABS(number)',
    description: 'Returns the absolute value',
    examples: ['ABS(-5) → 5', 'ABS(3) → 3']
  },
  MOD: {
    category: 'math',
    signature: 'MOD(number, divisor)',
    description: 'Returns the remainder after division',
    examples: ['MOD(10, 3) → 1', 'MOD(7, 2) → 1']
  },
  POWER: {
    category: 'math',
    signature: 'POWER(base, exponent)',
    description: 'Raises a number to a power',
    examples: ['POWER(2, 3) → 8', 'POWER(10, 2) → 100']
  },
  SQRT: {
    category: 'math',
    signature: 'SQRT(number)',
    description: 'Returns the square root',
    examples: ['SQRT(16) → 4', 'SQRT(2) → 1.414...']
  },

  // Text functions
  CONCAT: {
    category: 'text',
    signature: 'CONCAT(text1, text2, ...)',
    description: 'Joins text strings together',
    examples: ['CONCAT("Hello", " ", "World") → "Hello World"', 'CONCAT({First}, " ", {Last}) → full name']
  },
  UPPER: {
    category: 'text',
    signature: 'UPPER(text)',
    description: 'Converts text to uppercase',
    examples: ['UPPER("hello") → "HELLO"']
  },
  LOWER: {
    category: 'text',
    signature: 'LOWER(text)',
    description: 'Converts text to lowercase',
    examples: ['LOWER("HELLO") → "hello"']
  },
  TRIM: {
    category: 'text',
    signature: 'TRIM(text)',
    description: 'Removes leading and trailing spaces',
    examples: ['TRIM("  hello  ") → "hello"']
  },
  LEFT: {
    category: 'text',
    signature: 'LEFT(text, count)',
    description: 'Returns the first N characters',
    examples: ['LEFT("Hello", 2) → "He"']
  },
  RIGHT: {
    category: 'text',
    signature: 'RIGHT(text, count)',
    description: 'Returns the last N characters',
    examples: ['RIGHT("Hello", 2) → "lo"']
  },
  MID: {
    category: 'text',
    signature: 'MID(text, start, length)',
    description: 'Returns characters from the middle',
    examples: ['MID("Hello", 2, 3) → "ell"']
  },
  LEN: {
    category: 'text',
    signature: 'LEN(text)',
    description: 'Returns the length of text',
    examples: ['LEN("Hello") → 5']
  },
  SUBSTITUTE: {
    category: 'text',
    signature: 'SUBSTITUTE(text, old, new, instance?)',
    description: 'Replaces occurrences of text',
    examples: ['SUBSTITUTE("Hello", "l", "L") → "HeLLo"']
  },
  REPLACE: {
    category: 'text',
    signature: 'REPLACE(text, start, length, new)',
    description: 'Replaces characters at a position',
    examples: ['REPLACE("Hello", 2, 3, "i") → "Hio"']
  },
  FIND: {
    category: 'text',
    signature: 'FIND(find, within, start?)',
    description: 'Finds position of text (case-sensitive)',
    examples: ['FIND("l", "Hello") → 3']
  },
  SEARCH: {
    category: 'text',
    signature: 'SEARCH(find, within, start?)',
    description: 'Finds position of text (case-insensitive)',
    examples: ['SEARCH("L", "Hello") → 3']
  },
  REPT: {
    category: 'text',
    signature: 'REPT(text, times)',
    description: 'Repeats text N times',
    examples: ['REPT("ab", 3) → "ababab"']
  },
  TEXT: {
    category: 'text',
    signature: 'TEXT(value, format)',
    description: 'Formats a number as text',
    examples: ['TEXT(0.25, "0%") → "25%"']
  },

  // Logic functions
  IF: {
    category: 'logic',
    signature: 'IF(condition, then, else?)',
    description: 'Returns one value if true, another if false',
    examples: ['IF({Age} >= 18, "Adult", "Minor")', 'IF({Score} > 90, "A", "B")']
  },
  AND: {
    category: 'logic',
    signature: 'AND(condition1, condition2, ...)',
    description: 'Returns true if all conditions are true',
    examples: ['AND({Age} >= 18, {Consent} = true)']
  },
  OR: {
    category: 'logic',
    signature: 'OR(condition1, condition2, ...)',
    description: 'Returns true if any condition is true',
    examples: ['OR({Status} = "Active", {Status} = "Pending")']
  },
  NOT: {
    category: 'logic',
    signature: 'NOT(condition)',
    description: 'Reverses a boolean value',
    examples: ['NOT(true) → false', 'NOT({Subscribed})']
  },
  XOR: {
    category: 'logic',
    signature: 'XOR(condition1, condition2, ...)',
    description: 'Returns true if odd number of conditions are true',
    examples: ['XOR(true, false) → true']
  },
  ISBLANK: {
    category: 'logic',
    signature: 'ISBLANK(value)',
    description: 'Returns true if the value is empty',
    examples: ['ISBLANK({Email}) → true if empty']
  },
  ISNUMBER: {
    category: 'logic',
    signature: 'ISNUMBER(value)',
    description: 'Returns true if the value is a number',
    examples: ['ISNUMBER(123) → true']
  },
  ISTEXT: {
    category: 'logic',
    signature: 'ISTEXT(value)',
    description: 'Returns true if the value is text',
    examples: ['ISTEXT("hello") → true']
  },
  IFERROR: {
    category: 'logic',
    signature: 'IFERROR(value, fallback)',
    description: 'Returns fallback if value is an error',
    examples: ['IFERROR({Price} / {Qty}, 0)']
  },
  IFBLANK: {
    category: 'logic',
    signature: 'IFBLANK(value, fallback)',
    description: 'Returns fallback if value is blank',
    examples: ['IFBLANK({Name}, "Anonymous")']
  },
  COALESCE: {
    category: 'logic',
    signature: 'COALESCE(value1, value2, ...)',
    description: 'Returns the first non-empty value',
    examples: ['COALESCE({Nick}, {First}, "User")']
  },
  SWITCH: {
    category: 'logic',
    signature: 'SWITCH(value, case1, result1, ..., default?)',
    description: 'Matches value against cases',
    examples: ['SWITCH({Grade}, "A", 4, "B", 3, "C", 2, 0)']
  },
  IFS: {
    category: 'logic',
    signature: 'IFS(cond1, result1, cond2, result2, ...)',
    description: 'Returns result for first true condition',
    examples: ['IFS({Score} >= 90, "A", {Score} >= 80, "B", true, "C")']
  },
  CHOOSE: {
    category: 'logic',
    signature: 'CHOOSE(index, value1, value2, ...)',
    description: 'Returns value at index position',
    examples: ['CHOOSE(2, "a", "b", "c") → "b"']
  }
}

/**
 * Get functions by category
 */
export function getFunctionsByCategory(category) {
  return Object.entries(functionMeta)
    .filter(([_, meta]) => meta.category === category)
    .map(([name, meta]) => ({ name, ...meta }))
}

/**
 * Get all function names
 */
export function getFunctionNames() {
  return Object.keys(functions)
}

/**
 * Check if a function exists
 */
export function hasFunction(name) {
  return name.toUpperCase() in functions
}

/**
 * Get function by name
 */
export function getFunction(name) {
  return functions[name.toUpperCase()]
}
