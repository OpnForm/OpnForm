import { TokenType, NodeType, FormulaError } from './types.js'
import { Lexer } from './lexer.js'

/**
 * Parser for formula expressions
 * Converts tokens into an Abstract Syntax Tree (AST)
 * 
 * Grammar (precedence from lowest to highest):
 * expression  = comparison
 * comparison  = addition (("=" | "<>" | "<" | ">" | "<=" | ">=") addition)?
 * addition    = multiplication (('+' | '-') multiplication)*
 * multiplication = unary (('*' | '/') unary)*
 * unary       = ('-' | 'NOT') unary | primary
 * primary     = NUMBER | STRING | BOOLEAN | field_ref | function_call | '(' expression ')'
 */
export class Parser {
  constructor(tokens) {
    this.tokens = tokens
    this.current = 0
  }

  /**
   * Parse a formula string into an AST
   */
  static parse(formula) {
    const lexer = new Lexer(formula)
    const tokens = lexer.tokenize()
    const parser = new Parser(tokens)
    return parser.parse()
  }

  /**
   * Parse tokens into AST
   */
  parse() {
    const ast = this.expression()
    
    if (!this.isAtEnd()) {
      throw new FormulaError(
        `Unexpected token '${this.peek().value}' at position ${this.peek().position}`,
        this.peek().position
      )
    }
    
    return ast
  }

  /**
   * Check if we've reached the end of tokens
   */
  isAtEnd() {
    return this.peek().type === TokenType.EOF
  }

  /**
   * Get current token without advancing
   */
  peek() {
    return this.tokens[this.current]
  }

  /**
   * Get previous token
   */
  previous() {
    return this.tokens[this.current - 1]
  }

  /**
   * Advance and return current token
   */
  advance() {
    if (!this.isAtEnd()) {
      this.current++
    }
    return this.previous()
  }

  /**
   * Check if current token matches any of the given types
   */
  check(type) {
    if (this.isAtEnd()) return false
    return this.peek().type === type
  }

  /**
   * Check if current token matches type and value
   */
  checkValue(type, value) {
    if (this.isAtEnd()) return false
    const token = this.peek()
    return token.type === type && token.value === value
  }

  /**
   * Consume token if it matches, otherwise throw error
   */
  consume(type, message) {
    if (this.check(type)) return this.advance()
    throw new FormulaError(
      `${message} at position ${this.peek().position}`,
      this.peek().position
    )
  }

  /**
   * Match and consume if current token matches any of the given types/values
   */
  match(type, ...values) {
    if (this.check(type)) {
      if (values.length === 0 || values.includes(this.peek().value)) {
        this.advance()
        return true
      }
    }
    return false
  }

  /**
   * Parse expression (entry point)
   */
  expression() {
    return this.comparison()
  }

  /**
   * Parse comparison operators
   */
  comparison() {
    let left = this.addition()

    if (this.match(TokenType.COMPARISON)) {
      const operator = this.previous().value
      const right = this.addition()
      return {
        type: NodeType.BINARY,
        operator,
        left,
        right
      }
    }

    return left
  }

  /**
   * Parse addition and subtraction
   */
  addition() {
    let left = this.multiplication()

    while (this.match(TokenType.OPERATOR, '+', '-')) {
      const operator = this.previous().value
      const right = this.multiplication()
      left = {
        type: NodeType.BINARY,
        operator,
        left,
        right
      }
    }

    return left
  }

  /**
   * Parse multiplication and division
   */
  multiplication() {
    let left = this.unary()

    while (this.match(TokenType.OPERATOR, '*', '/')) {
      const operator = this.previous().value
      const right = this.unary()
      left = {
        type: NodeType.BINARY,
        operator,
        left,
        right
      }
    }

    return left
  }

  /**
   * Parse unary operators (- and NOT)
   */
  unary() {
    // Unary minus
    if (this.match(TokenType.OPERATOR, '-')) {
      const operand = this.unary()
      return {
        type: NodeType.UNARY,
        operator: '-',
        operand
      }
    }

    // NOT operator
    if (this.check(TokenType.IDENTIFIER) && this.peek().value === 'NOT') {
      this.advance()
      const operand = this.unary()
      return {
        type: NodeType.UNARY,
        operator: 'NOT',
        operand
      }
    }

    return this.primary()
  }

  /**
   * Parse primary expressions
   */
  primary() {
    // Number literal
    if (this.match(TokenType.NUMBER)) {
      return {
        type: NodeType.NUMBER,
        value: this.previous().value
      }
    }

    // String literal
    if (this.match(TokenType.STRING)) {
      return {
        type: NodeType.STRING,
        value: this.previous().value
      }
    }

    // Boolean literal
    if (this.match(TokenType.BOOLEAN)) {
      return {
        type: NodeType.BOOLEAN,
        value: this.previous().value
      }
    }

    // Field reference
    if (this.match(TokenType.FIELD_REF)) {
      return {
        type: NodeType.FIELD,
        id: this.previous().value
      }
    }

    // Function call or identifier
    if (this.match(TokenType.IDENTIFIER)) {
      const name = this.previous().value
      
      // Check if it's a function call
      if (this.check(TokenType.LPAREN)) {
        return this.functionCall(name)
      }

      // Otherwise it's an unknown identifier
      throw new FormulaError(
        `Unknown identifier '${name}' at position ${this.previous().position}`,
        this.previous().position
      )
    }

    // Parenthesized expression
    if (this.match(TokenType.LPAREN)) {
      const expr = this.expression()
      this.consume(TokenType.RPAREN, "Expected ')' after expression")
      return expr
    }

    throw new FormulaError(
      `Unexpected token at position ${this.peek().position}`,
      this.peek().position
    )
  }

  /**
   * Parse function call
   */
  functionCall(name) {
    this.consume(TokenType.LPAREN, `Expected '(' after function name '${name}'`)
    
    const args = []
    
    // Parse arguments
    if (!this.check(TokenType.RPAREN)) {
      do {
        args.push(this.expression())
      } while (this.match(TokenType.COMMA))
    }

    this.consume(TokenType.RPAREN, `Expected ')' after function arguments`)

    return {
      type: NodeType.FUNCTION,
      name,
      args
    }
  }
}
