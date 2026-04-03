import { describe, it, expect } from 'vitest'
import { Lexer } from '../../../lib/formulas/lexer.js'
import { TokenType } from '../../../lib/formulas/types.js'

describe('Formula Lexer', () => {
  describe('number tokens', () => {
    it('tokenizes integers', () => {
      const lexer = new Lexer('42')
      const tokens = lexer.tokenize()
      
      expect(tokens).toHaveLength(2) // NUMBER + EOF
      expect(tokens[0].type).toBe(TokenType.NUMBER)
      expect(tokens[0].value).toBe(42)
    })

    it('tokenizes decimals', () => {
      const lexer = new Lexer('3.14159')
      const tokens = lexer.tokenize()
      
      expect(tokens[0].type).toBe(TokenType.NUMBER)
      expect(tokens[0].value).toBe(3.14159)
    })

    it('tokenizes negative numbers with unary minus', () => {
      const lexer = new Lexer('-5')
      const tokens = lexer.tokenize()
      
      expect(tokens).toHaveLength(3) // OPERATOR(-) + NUMBER + EOF
      expect(tokens[0].type).toBe(TokenType.OPERATOR)
      expect(tokens[0].value).toBe('-')
      expect(tokens[1].type).toBe(TokenType.NUMBER)
      expect(tokens[1].value).toBe(5)
    })
  })

  describe('string tokens', () => {
    it('tokenizes double-quoted strings', () => {
      const lexer = new Lexer('"hello world"')
      const tokens = lexer.tokenize()
      
      expect(tokens[0].type).toBe(TokenType.STRING)
      expect(tokens[0].value).toBe('hello world')
    })

    it('tokenizes single-quoted strings', () => {
      const lexer = new Lexer("'hello'")
      const tokens = lexer.tokenize()
      
      expect(tokens[0].type).toBe(TokenType.STRING)
      expect(tokens[0].value).toBe('hello')
    })

    it('handles escaped quotes', () => {
      const lexer = new Lexer('"say \\"hello\\""')
      const tokens = lexer.tokenize()
      
      expect(tokens[0].type).toBe(TokenType.STRING)
      expect(tokens[0].value).toBe('say "hello"')
    })

    it('throws on unterminated strings', () => {
      const lexer = new Lexer('"unterminated')
      expect(() => lexer.tokenize()).toThrow('Unterminated string')
    })
  })

  describe('boolean tokens', () => {
    it('tokenizes TRUE', () => {
      const lexer = new Lexer('TRUE')
      const tokens = lexer.tokenize()
      
      expect(tokens[0].type).toBe(TokenType.BOOLEAN)
      expect(tokens[0].value).toBe(true)
    })

    it('tokenizes FALSE', () => {
      const lexer = new Lexer('FALSE')
      const tokens = lexer.tokenize()
      
      expect(tokens[0].type).toBe(TokenType.BOOLEAN)
      expect(tokens[0].value).toBe(false)
    })

    it('is case-insensitive for booleans', () => {
      const lexer1 = new Lexer('true')
      const lexer2 = new Lexer('True')
      
      expect(lexer1.tokenize()[0].value).toBe(true)
      expect(lexer2.tokenize()[0].value).toBe(true)
    })
  })

  describe('identifier tokens', () => {
    it('tokenizes function names', () => {
      const lexer = new Lexer('SUM')
      const tokens = lexer.tokenize()
      
      expect(tokens[0].type).toBe(TokenType.IDENTIFIER)
      expect(tokens[0].value).toBe('SUM')
    })

    it('uppercases identifiers', () => {
      const lexer = new Lexer('sum')
      const tokens = lexer.tokenize()
      
      expect(tokens[0].value).toBe('SUM')
    })
  })

  describe('field reference tokens', () => {
    it('tokenizes field references', () => {
      const lexer = new Lexer('{field_id}')
      const tokens = lexer.tokenize()
      
      expect(tokens[0].type).toBe(TokenType.FIELD_REF)
      expect(tokens[0].value).toBe('field_id')
    })

    it('trims whitespace in field references', () => {
      const lexer = new Lexer('{ field_id }')
      const tokens = lexer.tokenize()
      
      expect(tokens[0].value).toBe('field_id')
    })

    it('throws on unterminated field references', () => {
      const lexer = new Lexer('{field_id')
      expect(() => lexer.tokenize()).toThrow('Unterminated field reference')
    })
  })

  describe('operator tokens', () => {
    it('tokenizes arithmetic operators', () => {
      const lexer = new Lexer('+ - * /')
      const tokens = lexer.tokenize()
      
      expect(tokens[0]).toMatchObject({ type: TokenType.OPERATOR, value: '+' })
      expect(tokens[1]).toMatchObject({ type: TokenType.OPERATOR, value: '-' })
      expect(tokens[2]).toMatchObject({ type: TokenType.OPERATOR, value: '*' })
      expect(tokens[3]).toMatchObject({ type: TokenType.OPERATOR, value: '/' })
    })

    it('tokenizes comparison operators', () => {
      const lexer = new Lexer('= < > <= >= <>')
      const tokens = lexer.tokenize()
      
      expect(tokens[0]).toMatchObject({ type: TokenType.COMPARISON, value: '=' })
      expect(tokens[1]).toMatchObject({ type: TokenType.COMPARISON, value: '<' })
      expect(tokens[2]).toMatchObject({ type: TokenType.COMPARISON, value: '>' })
      expect(tokens[3]).toMatchObject({ type: TokenType.COMPARISON, value: '<=' })
      expect(tokens[4]).toMatchObject({ type: TokenType.COMPARISON, value: '>=' })
      expect(tokens[5]).toMatchObject({ type: TokenType.COMPARISON, value: '<>' })
    })
  })

  describe('complex expressions', () => {
    it('tokenizes a function call', () => {
      const lexer = new Lexer('SUM(1, 2, 3)')
      const tokens = lexer.tokenize()
      
      expect(tokens[0]).toMatchObject({ type: TokenType.IDENTIFIER, value: 'SUM' })
      expect(tokens[1]).toMatchObject({ type: TokenType.LPAREN })
      expect(tokens[2]).toMatchObject({ type: TokenType.NUMBER, value: 1 })
      expect(tokens[3]).toMatchObject({ type: TokenType.COMMA })
      expect(tokens[4]).toMatchObject({ type: TokenType.NUMBER, value: 2 })
      expect(tokens[5]).toMatchObject({ type: TokenType.COMMA })
      expect(tokens[6]).toMatchObject({ type: TokenType.NUMBER, value: 3 })
      expect(tokens[7]).toMatchObject({ type: TokenType.RPAREN })
    })

    it('tokenizes arithmetic expression with fields', () => {
      const lexer = new Lexer('{price} * {quantity}')
      const tokens = lexer.tokenize()
      
      expect(tokens[0]).toMatchObject({ type: TokenType.FIELD_REF, value: 'price' })
      expect(tokens[1]).toMatchObject({ type: TokenType.OPERATOR, value: '*' })
      expect(tokens[2]).toMatchObject({ type: TokenType.FIELD_REF, value: 'quantity' })
    })

    it('tokenizes nested function calls', () => {
      const lexer = new Lexer('IF({score} > 90, "A", "B")')
      const tokens = lexer.tokenize()
      
      // IF, (, {score}, >, 90, ,, "A", ,, "B", ), EOF = 11 tokens
      expect(tokens).toHaveLength(11)
    })
  })

  describe('whitespace handling', () => {
    it('skips whitespace', () => {
      const lexer = new Lexer('  1  +  2  ')
      const tokens = lexer.tokenize()
      
      expect(tokens).toHaveLength(4) // NUMBER + OPERATOR + NUMBER + EOF
    })

    it('handles tabs and newlines', () => {
      const lexer = new Lexer('1\t+\n2')
      const tokens = lexer.tokenize()
      
      expect(tokens).toHaveLength(4)
    })
  })

  describe('error handling', () => {
    it('throws on unexpected characters', () => {
      const lexer = new Lexer('1 @ 2')
      expect(() => lexer.tokenize()).toThrow('Unexpected character')
    })
  })
})
