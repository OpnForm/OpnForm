import { describe, it, expect } from 'vitest'
import { mathFunctions } from '../../../lib/formulas/functions/math.js'
import { textFunctions } from '../../../lib/formulas/functions/text.js'
import { logicFunctions } from '../../../lib/formulas/functions/logic.js'

describe('Formula Functions', () => {
  describe('Math Functions', () => {
    describe('SUM', () => {
      it('adds numbers', () => {
        expect(mathFunctions.SUM(1, 2, 3)).toBe(6)
      })

      it('handles empty input', () => {
        expect(mathFunctions.SUM()).toBe(0)
      })

      it('ignores non-numeric values', () => {
        expect(mathFunctions.SUM(1, 'invalid', 2, null, 3)).toBe(6)
      })

      it('flattens arrays', () => {
        expect(mathFunctions.SUM([1, 2], [3, 4])).toBe(10)
      })
    })

    describe('AVERAGE', () => {
      it('calculates mean', () => {
        expect(mathFunctions.AVERAGE(1, 2, 3, 4, 5)).toBe(3)
      })

      it('returns null for empty input', () => {
        expect(mathFunctions.AVERAGE()).toBe(null)
      })
    })

    describe('MIN/MAX', () => {
      it('finds minimum', () => {
        expect(mathFunctions.MIN(5, 3, 8, 1)).toBe(1)
      })

      it('finds maximum', () => {
        expect(mathFunctions.MAX(5, 3, 8, 1)).toBe(8)
      })
    })

    describe('ROUND', () => {
      it('rounds to integer by default', () => {
        expect(mathFunctions.ROUND(3.7)).toBe(4)
        expect(mathFunctions.ROUND(3.2)).toBe(3)
      })

      it('rounds to specified decimals', () => {
        expect(mathFunctions.ROUND(3.14159, 2)).toBe(3.14)
        expect(mathFunctions.ROUND(3.14159, 4)).toBe(3.1416)
      })
    })

    describe('FLOOR/CEIL', () => {
      it('floors down', () => {
        expect(mathFunctions.FLOOR(3.7)).toBe(3)
        expect(mathFunctions.FLOOR(-2.1)).toBe(-3)
      })

      it('ceils up', () => {
        expect(mathFunctions.CEIL(3.2)).toBe(4)
        expect(mathFunctions.CEIL(-2.9)).toBe(-2)
      })
    })

    describe('ABS', () => {
      it('returns absolute value', () => {
        expect(mathFunctions.ABS(-5)).toBe(5)
        expect(mathFunctions.ABS(5)).toBe(5)
        expect(mathFunctions.ABS(0)).toBe(0)
      })
    })

    describe('MOD', () => {
      it('returns remainder', () => {
        expect(mathFunctions.MOD(10, 3)).toBe(1)
        expect(mathFunctions.MOD(7, 2)).toBe(1)
      })

      it('returns null for division by zero', () => {
        expect(mathFunctions.MOD(10, 0)).toBe(null)
      })
    })

    describe('POWER', () => {
      it('raises to power', () => {
        expect(mathFunctions.POWER(2, 3)).toBe(8)
        expect(mathFunctions.POWER(10, 2)).toBe(100)
      })
    })

    describe('SQRT', () => {
      it('returns square root', () => {
        expect(mathFunctions.SQRT(16)).toBe(4)
        expect(mathFunctions.SQRT(2)).toBeCloseTo(1.414, 2)
      })

      it('returns null for negative numbers', () => {
        expect(mathFunctions.SQRT(-1)).toBe(null)
      })
    })
  })

  describe('Text Functions', () => {
    describe('CONCAT', () => {
      it('joins strings', () => {
        expect(textFunctions.CONCAT('Hello', ' ', 'World')).toBe('Hello World')
      })

      it('converts non-strings', () => {
        expect(textFunctions.CONCAT('Value: ', 42)).toBe('Value: 42')
      })
    })

    describe('UPPER/LOWER', () => {
      it('converts to uppercase', () => {
        expect(textFunctions.UPPER('hello')).toBe('HELLO')
      })

      it('converts to lowercase', () => {
        expect(textFunctions.LOWER('HELLO')).toBe('hello')
      })
    })

    describe('TRIM', () => {
      it('removes leading/trailing whitespace', () => {
        expect(textFunctions.TRIM('  hello  ')).toBe('hello')
      })
    })

    describe('LEFT/RIGHT/MID', () => {
      it('gets left characters', () => {
        expect(textFunctions.LEFT('Hello', 2)).toBe('He')
      })

      it('gets right characters', () => {
        expect(textFunctions.RIGHT('Hello', 2)).toBe('lo')
      })

      it('gets middle characters (1-indexed)', () => {
        expect(textFunctions.MID('Hello', 2, 3)).toBe('ell')
      })
    })

    describe('LEN', () => {
      it('returns string length', () => {
        expect(textFunctions.LEN('Hello')).toBe(5)
        expect(textFunctions.LEN('')).toBe(0)
      })
    })

    describe('SUBSTITUTE', () => {
      it('replaces all occurrences', () => {
        expect(textFunctions.SUBSTITUTE('hello hello', 'hello', 'hi')).toBe('hi hi')
      })

      it('replaces specific instance', () => {
        expect(textFunctions.SUBSTITUTE('hello hello hello', 'hello', 'hi', 2)).toBe('hello hi hello')
      })
    })

    describe('REPT', () => {
      it('repeats text N times', () => {
        expect(textFunctions.REPT('ab', 3)).toBe('ababab')
      })

      it('returns empty string for negative count', () => {
        expect(textFunctions.REPT('ab', -1)).toBe('')
      })

      it('limits repetitions to 100 to prevent memory abuse', () => {
        const result = textFunctions.REPT('x', 200)
        expect(result.length).toBe(100)
      })
    })

    describe('FIND/SEARCH', () => {
      it('finds position (case-sensitive)', () => {
        expect(textFunctions.FIND('l', 'Hello')).toBe(3)
        expect(textFunctions.FIND('L', 'Hello')).toBe(null)
      })

      it('searches position (case-insensitive)', () => {
        expect(textFunctions.SEARCH('L', 'Hello')).toBe(3)
      })
    })

    describe('REPT', () => {
      it('repeats text', () => {
        expect(textFunctions.REPT('ab', 3)).toBe('ababab')
      })
    })
  })

  describe('Logic Functions', () => {
    describe('IF', () => {
      it('returns true value when condition is true', () => {
        expect(logicFunctions.IF(true, 'yes', 'no')).toBe('yes')
      })

      it('returns false value when condition is false', () => {
        expect(logicFunctions.IF(false, 'yes', 'no')).toBe('no')
      })

      it('treats non-empty strings as truthy', () => {
        expect(logicFunctions.IF('hello', 'yes', 'no')).toBe('yes')
      })

      it('treats empty string as falsy', () => {
        expect(logicFunctions.IF('', 'yes', 'no')).toBe('no')
      })

      it('treats 0 as falsy', () => {
        expect(logicFunctions.IF(0, 'yes', 'no')).toBe('no')
      })
    })

    describe('AND', () => {
      it('returns true when all conditions are true', () => {
        expect(logicFunctions.AND(true, true, true)).toBe(true)
      })

      it('returns false when any condition is false', () => {
        expect(logicFunctions.AND(true, false, true)).toBe(false)
      })
    })

    describe('OR', () => {
      it('returns true when any condition is true', () => {
        expect(logicFunctions.OR(false, true, false)).toBe(true)
      })

      it('returns false when all conditions are false', () => {
        expect(logicFunctions.OR(false, false, false)).toBe(false)
      })
    })

    describe('NOT', () => {
      it('negates boolean', () => {
        expect(logicFunctions.NOT(true)).toBe(false)
        expect(logicFunctions.NOT(false)).toBe(true)
      })
    })

    describe('XOR', () => {
      it('returns true for odd number of true values', () => {
        expect(logicFunctions.XOR(true, false)).toBe(true)
        expect(logicFunctions.XOR(true, true, true)).toBe(true)
      })

      it('returns false for even number of true values', () => {
        expect(logicFunctions.XOR(true, true)).toBe(false)
        expect(logicFunctions.XOR(false, false)).toBe(false)
      })
    })

    describe('ISBLANK', () => {
      it('returns true for blank values', () => {
        expect(logicFunctions.ISBLANK('')).toBe(true)
        expect(logicFunctions.ISBLANK(null)).toBe(true)
        expect(logicFunctions.ISBLANK(undefined)).toBe(true)
      })

      it('returns false for non-blank values', () => {
        expect(logicFunctions.ISBLANK('hello')).toBe(false)
        expect(logicFunctions.ISBLANK(0)).toBe(false)
      })
    })

    describe('ISNUMBER', () => {
      it('returns true for numbers', () => {
        expect(logicFunctions.ISNUMBER(42)).toBe(true)
        expect(logicFunctions.ISNUMBER('42')).toBe(true)
      })

      it('returns false for non-numbers', () => {
        expect(logicFunctions.ISNUMBER('hello')).toBe(false)
        expect(logicFunctions.ISNUMBER(null)).toBe(false)
      })
    })

    describe('ISTEXT', () => {
      it('returns true for non-empty strings', () => {
        expect(logicFunctions.ISTEXT('hello')).toBe(true)
      })

      it('returns false for empty string or non-strings', () => {
        expect(logicFunctions.ISTEXT('')).toBe(false)
        expect(logicFunctions.ISTEXT(42)).toBe(false)
      })
    })

    describe('IFBLANK', () => {
      it('returns fallback for blank values', () => {
        expect(logicFunctions.IFBLANK('', 'default')).toBe('default')
        expect(logicFunctions.IFBLANK(null, 'default')).toBe('default')
      })

      it('returns value for non-blank values', () => {
        expect(logicFunctions.IFBLANK('hello', 'default')).toBe('hello')
      })
    })

    describe('COALESCE', () => {
      it('returns first non-blank value', () => {
        expect(logicFunctions.COALESCE('', null, 'third', 'fourth')).toBe('third')
      })

      it('returns null if all blank', () => {
        expect(logicFunctions.COALESCE('', null, '')).toBe(null)
      })
    })

    describe('SWITCH', () => {
      it('returns matching case result', () => {
        expect(logicFunctions.SWITCH('A', 'A', 4, 'B', 3, 'C', 2, 0)).toBe(4)
        expect(logicFunctions.SWITCH('B', 'A', 4, 'B', 3, 'C', 2, 0)).toBe(3)
      })

      it('returns default when no match', () => {
        expect(logicFunctions.SWITCH('D', 'A', 4, 'B', 3, 'C', 2, 0)).toBe(0)
      })
    })

    describe('IFS', () => {
      it('returns result for first true condition', () => {
        expect(logicFunctions.IFS(false, 'A', true, 'B', true, 'C')).toBe('B')
      })

      it('returns null when no condition is true', () => {
        expect(logicFunctions.IFS(false, 'A', false, 'B')).toBe(null)
      })
    })

    describe('CHOOSE', () => {
      it('returns value at index (1-indexed)', () => {
        expect(logicFunctions.CHOOSE(2, 'a', 'b', 'c')).toBe('b')
      })

      it('returns null for invalid index', () => {
        expect(logicFunctions.CHOOSE(0, 'a', 'b', 'c')).toBe(null)
        expect(logicFunctions.CHOOSE(5, 'a', 'b', 'c')).toBe(null)
      })
    })
  })
})
