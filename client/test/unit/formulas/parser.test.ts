import { describe, it, expect } from 'vitest'
import { Parser } from '../../../lib/formulas/parser.js'
import { NodeType } from '../../../lib/formulas/types.js'

describe('Formula Parser', () => {
  describe('literals', () => {
    it('parses number literals', () => {
      const ast = Parser.parse('42')
      
      expect(ast).toEqual({
        type: NodeType.NUMBER,
        value: 42
      })
    })

    it('parses string literals', () => {
      const ast = Parser.parse('"hello"')
      
      expect(ast).toEqual({
        type: NodeType.STRING,
        value: 'hello'
      })
    })

    it('parses boolean literals', () => {
      expect(Parser.parse('TRUE')).toEqual({ type: NodeType.BOOLEAN, value: true })
      expect(Parser.parse('FALSE')).toEqual({ type: NodeType.BOOLEAN, value: false })
    })
  })

  describe('field references', () => {
    it('parses field references', () => {
      const ast = Parser.parse('{field_id}')
      
      expect(ast).toEqual({
        type: NodeType.FIELD,
        id: 'field_id'
      })
    })
  })

  describe('arithmetic operations', () => {
    it('parses addition', () => {
      const ast = Parser.parse('1 + 2')
      
      expect(ast).toEqual({
        type: NodeType.BINARY,
        operator: '+',
        left: { type: NodeType.NUMBER, value: 1 },
        right: { type: NodeType.NUMBER, value: 2 }
      })
    })

    it('parses subtraction', () => {
      const ast = Parser.parse('5 - 3')
      
      expect(ast).toEqual({
        type: NodeType.BINARY,
        operator: '-',
        left: { type: NodeType.NUMBER, value: 5 },
        right: { type: NodeType.NUMBER, value: 3 }
      })
    })

    it('parses multiplication', () => {
      const ast = Parser.parse('4 * 3')
      
      expect(ast).toEqual({
        type: NodeType.BINARY,
        operator: '*',
        left: { type: NodeType.NUMBER, value: 4 },
        right: { type: NodeType.NUMBER, value: 3 }
      })
    })

    it('parses division', () => {
      const ast = Parser.parse('10 / 2')
      
      expect(ast).toEqual({
        type: NodeType.BINARY,
        operator: '/',
        left: { type: NodeType.NUMBER, value: 10 },
        right: { type: NodeType.NUMBER, value: 2 }
      })
    })

    it('respects operator precedence (multiplication before addition)', () => {
      const ast = Parser.parse('1 + 2 * 3')
      
      expect(ast).toEqual({
        type: NodeType.BINARY,
        operator: '+',
        left: { type: NodeType.NUMBER, value: 1 },
        right: {
          type: NodeType.BINARY,
          operator: '*',
          left: { type: NodeType.NUMBER, value: 2 },
          right: { type: NodeType.NUMBER, value: 3 }
        }
      })
    })

    it('respects parentheses', () => {
      const ast = Parser.parse('(1 + 2) * 3')
      
      expect(ast).toEqual({
        type: NodeType.BINARY,
        operator: '*',
        left: {
          type: NodeType.BINARY,
          operator: '+',
          left: { type: NodeType.NUMBER, value: 1 },
          right: { type: NodeType.NUMBER, value: 2 }
        },
        right: { type: NodeType.NUMBER, value: 3 }
      })
    })
  })

  describe('unary operations', () => {
    it('parses unary minus', () => {
      const ast = Parser.parse('-5')
      
      expect(ast).toEqual({
        type: NodeType.UNARY,
        operator: '-',
        operand: { type: NodeType.NUMBER, value: 5 }
      })
    })

    it('parses NOT operator', () => {
      const ast = Parser.parse('NOT TRUE')
      
      expect(ast).toEqual({
        type: NodeType.UNARY,
        operator: 'NOT',
        operand: { type: NodeType.BOOLEAN, value: true }
      })
    })
  })

  describe('comparison operations', () => {
    it('parses equals', () => {
      const ast = Parser.parse('1 = 1')
      
      expect(ast.type).toBe(NodeType.BINARY)
      expect(ast.operator).toBe('=')
    })

    it('parses not equals', () => {
      const ast = Parser.parse('1 <> 2')
      
      expect(ast.operator).toBe('<>')
    })

    it('parses less than', () => {
      const ast = Parser.parse('1 < 2')
      expect(ast.operator).toBe('<')
    })

    it('parses greater than', () => {
      const ast = Parser.parse('2 > 1')
      expect(ast.operator).toBe('>')
    })

    it('parses less than or equal', () => {
      const ast = Parser.parse('1 <= 2')
      expect(ast.operator).toBe('<=')
    })

    it('parses greater than or equal', () => {
      const ast = Parser.parse('2 >= 1')
      expect(ast.operator).toBe('>=')
    })
  })

  describe('function calls', () => {
    it('parses function with no arguments', () => {
      // Most functions require arguments, but parser should handle empty args
      const ast = Parser.parse('SUM()')
      
      expect(ast).toEqual({
        type: NodeType.FUNCTION,
        name: 'SUM',
        args: []
      })
    })

    it('parses function with one argument', () => {
      const ast = Parser.parse('ABS(-5)')
      
      expect(ast).toEqual({
        type: NodeType.FUNCTION,
        name: 'ABS',
        args: [{
          type: NodeType.UNARY,
          operator: '-',
          operand: { type: NodeType.NUMBER, value: 5 }
        }]
      })
    })

    it('parses function with multiple arguments', () => {
      const ast = Parser.parse('SUM(1, 2, 3)')
      
      expect(ast.type).toBe(NodeType.FUNCTION)
      expect(ast.name).toBe('SUM')
      expect(ast.args).toHaveLength(3)
    })

    it('parses nested function calls', () => {
      const ast = Parser.parse('ROUND(SUM(1, 2), 2)')
      
      expect(ast.type).toBe(NodeType.FUNCTION)
      expect(ast.name).toBe('ROUND')
      expect(ast.args[0].type).toBe(NodeType.FUNCTION)
      expect(ast.args[0].name).toBe('SUM')
    })
  })

  describe('complex expressions', () => {
    it('parses IF with comparison', () => {
      const ast = Parser.parse('IF({score} > 90, "A", "B")')
      
      expect(ast.type).toBe(NodeType.FUNCTION)
      expect(ast.name).toBe('IF')
      expect(ast.args).toHaveLength(3)
      expect(ast.args[0].type).toBe(NodeType.BINARY)
      expect(ast.args[0].operator).toBe('>')
    })

    it('parses arithmetic with field references', () => {
      const ast = Parser.parse('{price} * {quantity} + {tax}')
      
      expect(ast.type).toBe(NodeType.BINARY)
      expect(ast.operator).toBe('+')
    })
  })

  describe('error handling', () => {
    it('throws on unclosed parenthesis', () => {
      expect(() => Parser.parse('SUM(1, 2')).toThrow()
    })

    it('throws on unknown identifier (not followed by parenthesis)', () => {
      expect(() => Parser.parse('UNKNOWN')).toThrow('Unknown identifier')
    })

    it('throws on unexpected token', () => {
      expect(() => Parser.parse('1 + + 2')).toThrow()
    })
  })
})
