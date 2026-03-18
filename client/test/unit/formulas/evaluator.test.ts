import { describe, it, expect } from 'vitest'
import { evaluate, Evaluator } from '../../../lib/formulas/evaluator.js'

describe('Formula Evaluator', () => {
  describe('literals', () => {
    it('evaluates number literals', () => {
      expect(evaluate('42')).toBe(42)
      expect(evaluate('3.14')).toBe(3.14)
    })

    it('evaluates string literals', () => {
      expect(evaluate('"hello"')).toBe('hello')
      expect(evaluate("'world'")).toBe('world')
    })

    it('evaluates boolean literals', () => {
      expect(evaluate('TRUE')).toBe(true)
      expect(evaluate('FALSE')).toBe(false)
    })
  })

  describe('field references', () => {
    it('resolves field values from context', () => {
      const context = { name: 'John', age: 30 }
      
      expect(evaluate('{name}', context)).toBe('John')
      expect(evaluate('{age}', context)).toBe(30)
    })

    it('returns null for missing fields', () => {
      expect(evaluate('{missing}', {})).toBe(null)
    })
  })

  describe('arithmetic operations', () => {
    it('evaluates addition', () => {
      expect(evaluate('1 + 2')).toBe(3)
      expect(evaluate('10 + 20 + 30')).toBe(60)
    })

    it('evaluates subtraction', () => {
      expect(evaluate('10 - 3')).toBe(7)
    })

    it('evaluates multiplication', () => {
      expect(evaluate('4 * 5')).toBe(20)
    })

    it('evaluates division', () => {
      expect(evaluate('20 / 4')).toBe(5)
    })

    it('returns null for division by zero', () => {
      expect(evaluate('10 / 0')).toBe(null)
    })

    it('respects operator precedence', () => {
      expect(evaluate('2 + 3 * 4')).toBe(14)
      expect(evaluate('(2 + 3) * 4')).toBe(20)
    })

    it('handles string concatenation with +', () => {
      expect(evaluate('"Hello" + " " + "World"')).toBe('Hello World')
    })

    it('handles mixed string/number with +', () => {
      expect(evaluate('"Value: " + 42')).toBe('Value: 42')
    })
  })

  describe('unary operations', () => {
    it('evaluates unary minus', () => {
      expect(evaluate('-5')).toBe(-5)
      expect(evaluate('--5')).toBe(5)
    })

    it('evaluates NOT', () => {
      expect(evaluate('NOT TRUE')).toBe(false)
      expect(evaluate('NOT FALSE')).toBe(true)
    })
  })

  describe('comparison operations', () => {
    it('evaluates equals', () => {
      expect(evaluate('1 = 1')).toBe(true)
      expect(evaluate('1 = 2')).toBe(false)
      expect(evaluate('"a" = "a"')).toBe(true)
    })

    it('evaluates equals case-insensitive for strings', () => {
      expect(evaluate('"Hello" = "hello"')).toBe(true)
    })

    it('evaluates not equals', () => {
      expect(evaluate('1 <> 2')).toBe(true)
      expect(evaluate('1 <> 1')).toBe(false)
    })

    it('evaluates less than', () => {
      expect(evaluate('1 < 2')).toBe(true)
      expect(evaluate('2 < 1')).toBe(false)
    })

    it('evaluates greater than', () => {
      expect(evaluate('2 > 1')).toBe(true)
      expect(evaluate('1 > 2')).toBe(false)
    })

    it('evaluates less than or equal', () => {
      expect(evaluate('1 <= 2')).toBe(true)
      expect(evaluate('2 <= 2')).toBe(true)
      expect(evaluate('3 <= 2')).toBe(false)
    })

    it('evaluates greater than or equal', () => {
      expect(evaluate('2 >= 1')).toBe(true)
      expect(evaluate('2 >= 2')).toBe(true)
      expect(evaluate('1 >= 2')).toBe(false)
    })
  })

  describe('function calls', () => {
    describe('math functions', () => {
      it('evaluates SUM', () => {
        expect(evaluate('SUM(1, 2, 3)')).toBe(6)
        expect(evaluate('SUM(10)')).toBe(10)
      })

      it('evaluates AVERAGE', () => {
        expect(evaluate('AVERAGE(1, 2, 3)')).toBe(2)
      })

      it('evaluates MIN', () => {
        expect(evaluate('MIN(5, 3, 8, 1)')).toBe(1)
      })

      it('evaluates MAX', () => {
        expect(evaluate('MAX(5, 3, 8, 1)')).toBe(8)
      })

      it('evaluates ROUND', () => {
        expect(evaluate('ROUND(3.7)')).toBe(4)
        expect(evaluate('ROUND(3.14159, 2)')).toBe(3.14)
      })

      it('evaluates FLOOR', () => {
        expect(evaluate('FLOOR(3.7)')).toBe(3)
      })

      it('evaluates CEIL', () => {
        expect(evaluate('CEIL(3.2)')).toBe(4)
      })

      it('evaluates ABS', () => {
        expect(evaluate('ABS(-5)')).toBe(5)
        expect(evaluate('ABS(5)')).toBe(5)
      })

      it('evaluates MOD', () => {
        expect(evaluate('MOD(10, 3)')).toBe(1)
      })

      it('evaluates POWER', () => {
        expect(evaluate('POWER(2, 3)')).toBe(8)
      })

      it('evaluates SQRT', () => {
        expect(evaluate('SQRT(16)')).toBe(4)
      })
    })

    describe('text functions', () => {
      it('evaluates CONCAT', () => {
        expect(evaluate('CONCAT("Hello", " ", "World")')).toBe('Hello World')
      })

      it('evaluates UPPER', () => {
        expect(evaluate('UPPER("hello")')).toBe('HELLO')
      })

      it('evaluates LOWER', () => {
        expect(evaluate('LOWER("HELLO")')).toBe('hello')
      })

      it('evaluates TRIM', () => {
        expect(evaluate('TRIM("  hello  ")')).toBe('hello')
      })

      it('evaluates LEFT', () => {
        expect(evaluate('LEFT("Hello", 2)')).toBe('He')
      })

      it('evaluates RIGHT', () => {
        expect(evaluate('RIGHT("Hello", 2)')).toBe('lo')
      })

      it('evaluates MID', () => {
        expect(evaluate('MID("Hello", 2, 3)')).toBe('ell')
      })

      it('evaluates LEN', () => {
        expect(evaluate('LEN("Hello")')).toBe(5)
      })
    })

    describe('logic functions', () => {
      it('evaluates IF', () => {
        expect(evaluate('IF(TRUE, "yes", "no")')).toBe('yes')
        expect(evaluate('IF(FALSE, "yes", "no")')).toBe('no')
      })

      it('evaluates AND', () => {
        expect(evaluate('AND(TRUE, TRUE)')).toBe(true)
        expect(evaluate('AND(TRUE, FALSE)')).toBe(false)
      })

      it('evaluates OR', () => {
        expect(evaluate('OR(TRUE, FALSE)')).toBe(true)
        expect(evaluate('OR(FALSE, FALSE)')).toBe(false)
      })

      it('evaluates ISBLANK', () => {
        expect(evaluate('ISBLANK("")')).toBe(true)
        expect(evaluate('ISBLANK("hello")')).toBe(false)
      })

      it('evaluates ISNUMBER', () => {
        expect(evaluate('ISNUMBER(42)')).toBe(true)
        expect(evaluate('ISNUMBER("hello")')).toBe(false)
      })

      it('evaluates IFBLANK', () => {
        expect(evaluate('IFBLANK("", "default")')).toBe('default')
        expect(evaluate('IFBLANK("value", "default")')).toBe('value')
      })

      it('evaluates COALESCE', () => {
        expect(evaluate('COALESCE("", "", "third")')).toBe('third')
      })
    })
  })

  describe('complex expressions', () => {
    it('evaluates nested function calls', () => {
      expect(evaluate('ROUND(AVERAGE(1, 2, 3, 4), 1)')).toBe(2.5)
    })

    it('evaluates expressions with field references', () => {
      const context = { price: 100, quantity: 5, tax_rate: 0.1 }
      expect(evaluate('{price} * {quantity} * (1 + {tax_rate})', context)).toBe(550)
    })

    it('evaluates IF with comparison on fields', () => {
      expect(evaluate('IF({score} >= 90, "A", "B")', { score: 95 })).toBe('A')
      expect(evaluate('IF({score} >= 90, "A", "B")', { score: 85 })).toBe('B')
    })
  })

  describe('error handling', () => {
    it('returns null for invalid formulas', () => {
      expect(evaluate('INVALID_FORMULA(')).toBe(null)
    })

    it('returns null when field values cause errors', () => {
      // Division by a field that is 0
      expect(evaluate('10 / {value}', { value: 0 })).toBe(null)
    })
  })

  describe('depth limiting', () => {
    it('returns null when nesting exceeds max depth of 10', () => {
      // 11 levels of nesting should fail
      const formula = 'ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(1)))))))))))'
      expect(evaluate(formula)).toBe(null)
    })

    it('evaluates formulas within depth limit', () => {
      // 10 levels deep should work
      const formula = 'ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(ABS(1))))))))))'
      expect(evaluate(formula)).toBe(1)
    })
  })
})
