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
  constructor(tokens, options = {}) {
    this.tokens = tokens
    this.current = 0
    // Field name lookup for better error messages
    this.fieldNames = options.fieldNames || {}
    // Track the last parsed primary for adjacent operand detection
    this.lastPrimaryToken = null
  }

  /**
   * Parse a formula string into an AST
   * @param {string} formula - The formula to parse
   * @param {Object} options - Parser options
   * @param {Object} options.fieldNames - Map of field IDs to display names
   */
  static parse(formula, options = {}) {
    const lexer = new Lexer(formula)
    const tokens = lexer.tokenize()
    const parser = new Parser(tokens, options)
    return parser.parse()
  }

  /**
   * Parse tokens into AST
   */
  parse() {
    const ast = this.expression()
    
    if (!this.isAtEnd()) {
      const currentToken = this.peek()
      const errorMessage = this.getUnexpectedTokenError(currentToken)
      throw new FormulaError(errorMessage, currentToken.position)
    }
    
    return ast
  }

  /**
   * Get a human-readable name for a field ID
   */
  getFieldDisplayName(fieldId) {
    return this.fieldNames[fieldId] || fieldId
  }

  /**
   * Generate a helpful error message for unexpected tokens
   */
  getUnexpectedTokenError(token) {
    // Check if this looks like an adjacent operand situation
    // (two values without an operator between them)
    if (this.lastPrimaryToken && this.isValueToken(token)) {
      const lastName = this.getTokenDisplayName(this.lastPrimaryToken)
      const currentName = this.getTokenDisplayName(token)
      return `Missing operator between ${lastName} and ${currentName}. Use +, -, *, / or a function like CONCAT().`
    }
    
    // For field refs that look like UUIDs, give a cleaner message
    if (token.type === TokenType.FIELD_REF) {
      const fieldName = this.getFieldDisplayName(token.value)
      return `Unexpected field reference '${fieldName}'`
    }
    
    // For identifiers (which might be function typos)
    if (token.type === TokenType.IDENTIFIER) {
      return `Unexpected identifier '${token.value}'. Did you mean to call a function? Use ${token.value}()`
    }
    
    return `Unexpected token '${token.value}'`
  }

  /**
   * Check if a token represents a value (operand)
   */
  isValueToken(token) {
    return [
      TokenType.NUMBER,
      TokenType.STRING,
      TokenType.BOOLEAN,
      TokenType.FIELD_REF,
      TokenType.IDENTIFIER
    ].includes(token.type)
  }

  /**
   * Get display name for a token
   */
  getTokenDisplayName(token) {
    switch (token.type) {
      case TokenType.FIELD_REF:
        return `'${this.getFieldDisplayName(token.value)}'`
      case TokenType.STRING:
        return `"${token.value}"`
      case TokenType.NUMBER:
        return token.value.toString()
      case TokenType.BOOLEAN:
        return token.value.toString().toUpperCase()
      default:
        return `'${token.value}'`
    }
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
    throw new FormulaError(message, this.peek().position)
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
      this.lastPrimaryToken = this.previous()
      return {
        type: NodeType.NUMBER,
        value: this.previous().value
      }
    }

    // String literal
    if (this.match(TokenType.STRING)) {
      this.lastPrimaryToken = this.previous()
      return {
        type: NodeType.STRING,
        value: this.previous().value
      }
    }

    // Boolean literal
    if (this.match(TokenType.BOOLEAN)) {
      this.lastPrimaryToken = this.previous()
      return {
        type: NodeType.BOOLEAN,
        value: this.previous().value
      }
    }

    // Field reference
    if (this.match(TokenType.FIELD_REF)) {
      this.lastPrimaryToken = this.previous()
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
        const result = this.functionCall(name)
        this.lastPrimaryToken = this.previous() // The closing paren
        return result
      }

      // Otherwise it's an unknown identifier
      throw new FormulaError(
        `Unknown identifier '${name}'. Did you mean to use a field? Use {${name}} or call a function with ${name}()`,
        this.previous().position
      )
    }

    // Parenthesized expression
    if (this.match(TokenType.LPAREN)) {
      const expr = this.expression()
      this.consume(TokenType.RPAREN, "Missing closing parenthesis ')'")
      this.lastPrimaryToken = this.previous()
      return expr
    }

    // Provide a more helpful error for unexpected tokens
    const token = this.peek()
    if (token.type === TokenType.RPAREN) {
      throw new FormulaError(
        "Unexpected ')'. You may have an extra closing parenthesis or missing content.",
        token.position
      )
    }
    
    if (token.type === TokenType.COMMA) {
      throw new FormulaError(
        "Unexpected ','. Commas should only be used to separate function arguments.",
        token.position
      )
    }
    
    if (token.type === TokenType.EOF) {
      throw new FormulaError(
        "Formula is incomplete. Expected a value or expression.",
        token.position
      )
    }

    throw new FormulaError(
      `Unexpected '${token.value}'. Expected a number, text, field, or function.`,
      token.position
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

    this.consume(TokenType.RPAREN, `Missing closing ')' for function ${name.toUpperCase()}()`)

    return {
      type: NodeType.FUNCTION,
      name,
      args
    }
  }
}
