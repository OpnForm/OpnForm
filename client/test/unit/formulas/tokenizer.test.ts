import { describe, it, expect } from 'vitest'
import { tokenizeFormula, TokenTypes, getKnownFunctionNames } from '../../../lib/formulas/tokenizer.js'

describe('tokenizeFormula', () => {
  describe('basic tokens', () => {
    it('tokenizes numbers', () => {
      const tokens = tokenizeFormula('42')
      expect(tokens).toEqual([{ type: TokenTypes.NUMBER, value: '42' }])
    })

    it('tokenizes decimal numbers', () => {
      const tokens = tokenizeFormula('3.14')
      expect(tokens).toEqual([{ type: TokenTypes.NUMBER, value: '3.14' }])
    })

    it('tokenizes string literals with double quotes', () => {
      const tokens = tokenizeFormula('"hello world"')
      expect(tokens).toEqual([{ type: TokenTypes.STRING, value: '"hello world"' }])
    })

    it('tokenizes string literals with single quotes', () => {
      const tokens = tokenizeFormula("'hello'")
      expect(tokens).toEqual([{ type: TokenTypes.STRING, value: "'hello'" }])
    })

    it('tokenizes arithmetic operators', () => {
      const tokens = tokenizeFormula('+ - * /')
      expect(tokens).toHaveLength(7) // 4 operators + 3 spaces
      expect(tokens[0]).toEqual({ type: TokenTypes.OPERATOR, value: '+' })
      expect(tokens[2]).toEqual({ type: TokenTypes.OPERATOR, value: '-' })
      expect(tokens[4]).toEqual({ type: TokenTypes.OPERATOR, value: '*' })
      expect(tokens[6]).toEqual({ type: TokenTypes.OPERATOR, value: '/' })
    })

    it('tokenizes comparison operators', () => {
      const tokens = tokenizeFormula('<= >= <> != == < > =')
      const operators = tokens.filter(t => t.type === TokenTypes.OPERATOR)
      expect(operators.map(o => o.value)).toEqual(['<=', '>=', '<>', '!=', '==', '<', '>', '='])
    })

    it('tokenizes parentheses and commas', () => {
      const tokens = tokenizeFormula('(1,2)')
      expect(tokens[0]).toEqual({ type: TokenTypes.OPERATOR, value: '(' })
      expect(tokens[1]).toEqual({ type: TokenTypes.NUMBER, value: '1' })
      expect(tokens[2]).toEqual({ type: TokenTypes.OPERATOR, value: ',' })
      expect(tokens[3]).toEqual({ type: TokenTypes.NUMBER, value: '2' })
      expect(tokens[4]).toEqual({ type: TokenTypes.OPERATOR, value: ')' })
    })
  })

  describe('function recognition', () => {
    it('tokenizes function names followed by parenthesis', () => {
      const tokens = tokenizeFormula('SUM(1, 2)')
      expect(tokens[0]).toEqual({ type: TokenTypes.FUNCTION, value: 'SUM' })
    })

    it('tokenizes function names case-insensitively', () => {
      const tokens = tokenizeFormula('sum(1)')
      expect(tokens[0]).toEqual({ type: TokenTypes.FUNCTION, value: 'sum' })
    })

    it('does not tokenize function names without parenthesis as functions', () => {
      const tokens = tokenizeFormula('SUM + 1')
      // SUM without () should be text
      expect(tokens[0]).toEqual({ type: TokenTypes.TEXT, value: 'S' })
    })

    it('tokenizes nested functions', () => {
      const tokens = tokenizeFormula('IF(SUM(1, 2) > 0, "yes", "no")')
      const functions = tokens.filter(t => t.type === TokenTypes.FUNCTION)
      expect(functions).toHaveLength(2)
      expect(functions[0].value).toBe('IF')
      expect(functions[1].value).toBe('SUM')
    })

    it('accepts custom function names list', () => {
      const tokens = tokenizeFormula('CUSTOM(1)', new Map(), ['CUSTOM'])
      expect(tokens[0]).toEqual({ type: TokenTypes.FUNCTION, value: 'CUSTOM' })
    })
  })

  describe('field references', () => {
    it('tokenizes unresolved field references as text', () => {
      const tokens = tokenizeFormula('{field_id}')
      expect(tokens).toEqual([{ type: TokenTypes.TEXT, value: '{field_id}' }])
    })

    it('tokenizes resolved field references as pills', () => {
      const fieldMap = new Map([
        ['field_1', { id: 'field_1', name: 'My Field', type: 'text' }]
      ])
      const tokens = tokenizeFormula('{field_1}', fieldMap)
      expect(tokens).toEqual([{
        type: TokenTypes.PILL,
        id: 'field_1',
        name: 'My Field',
        fieldType: 'text'
      }])
    })

    it('tokenizes mixed resolved and unresolved references', () => {
      const fieldMap = new Map([
        ['known', { id: 'known', name: 'Known Field', type: 'number' }]
      ])
      const tokens = tokenizeFormula('{known} + {unknown}', fieldMap)
      
      expect(tokens[0]).toEqual({
        type: TokenTypes.PILL,
        id: 'known',
        name: 'Known Field',
        fieldType: 'number'
      })
      expect(tokens[4]).toEqual({ type: TokenTypes.TEXT, value: '{unknown}' })
    })
  })

  describe('complex formulas', () => {
    it('tokenizes a complete formula', () => {
      const fieldMap = new Map([
        ['price', { id: 'price', name: 'Price', type: 'number' }],
        ['qty', { id: 'qty', name: 'Quantity', type: 'number' }]
      ])
      const tokens = tokenizeFormula('{price} * {qty}', fieldMap)
      
      expect(tokens[0].type).toBe(TokenTypes.PILL)
      expect(tokens[0].name).toBe('Price')
      expect(tokens[2].type).toBe(TokenTypes.OPERATOR)
      expect(tokens[2].value).toBe('*')
      expect(tokens[4].type).toBe(TokenTypes.PILL)
      expect(tokens[4].name).toBe('Quantity')
    })

    it('tokenizes formula with function and field reference', () => {
      const fieldMap = new Map([
        ['name', { id: 'name', name: 'Name', type: 'text' }]
      ])
      const tokens = tokenizeFormula('UPPER({name})', fieldMap)
      
      expect(tokens[0]).toEqual({ type: TokenTypes.FUNCTION, value: 'UPPER' })
      expect(tokens[1]).toEqual({ type: TokenTypes.OPERATOR, value: '(' })
      expect(tokens[2].type).toBe(TokenTypes.PILL)
      expect(tokens[3]).toEqual({ type: TokenTypes.OPERATOR, value: ')' })
    })

    it('tokenizes IF formula with comparison', () => {
      const tokens = tokenizeFormula('IF({x} > 10, "big", "small")')
      const functions = tokens.filter(t => t.type === TokenTypes.FUNCTION)
      const strings = tokens.filter(t => t.type === TokenTypes.STRING)
      const operators = tokens.filter(t => t.type === TokenTypes.OPERATOR)
      
      expect(functions).toHaveLength(1)
      expect(strings).toHaveLength(2)
      expect(operators.some(o => o.value === '>')).toBe(true)
    })

    it('handles empty formula', () => {
      expect(tokenizeFormula('')).toEqual([])
      expect(tokenizeFormula(null)).toEqual([])
    })
  })

  describe('getKnownFunctionNames', () => {
    it('returns array of function names', () => {
      const names = getKnownFunctionNames()
      expect(Array.isArray(names)).toBe(true)
      expect(names.length).toBeGreaterThan(0)
      expect(names).toContain('SUM')
      expect(names).toContain('IF')
      expect(names).toContain('CONCAT')
    })
  })
})
