import { TokenType, FormulaError } from './types.js'

/**
 * Token class representing a lexical token
 */
class Token {
  constructor(type, value, position) {
    this.type = type
    this.value = value
    this.position = position
  }
}

/**
 * Lexer for formula expressions
 * Converts a formula string into a stream of tokens
 */
export class Lexer {
  constructor(input) {
    this.input = input
    this.position = 0
    this.tokens = []
  }

  /**
   * Check if we've reached the end of input
   */
  isAtEnd() {
    return this.position >= this.input.length
  }

  /**
   * Get current character without advancing
   */
  peek() {
    return this.input[this.position]
  }

  /**
   * Get next character without advancing
   */
  peekNext() {
    return this.input[this.position + 1]
  }

  /**
   * Advance and return current character
   */
  advance() {
    return this.input[this.position++]
  }

  /**
   * Check if character is a digit
   */
  isDigit(char) {
    return char >= '0' && char <= '9'
  }

  /**
   * Check if character is a letter or underscore
   */
  isAlpha(char) {
    return (char >= 'a' && char <= 'z') ||
           (char >= 'A' && char <= 'Z') ||
           char === '_'
  }

  /**
   * Check if character is alphanumeric or underscore
   */
  isAlphaNumeric(char) {
    return this.isAlpha(char) || this.isDigit(char)
  }

  /**
   * Check if character is whitespace
   */
  isWhitespace(char) {
    return char === ' ' || char === '\t' || char === '\n' || char === '\r'
  }

  /**
   * Skip whitespace characters
   */
  skipWhitespace() {
    while (!this.isAtEnd() && this.isWhitespace(this.peek())) {
      this.advance()
    }
  }

  /**
   * Read a number token (integer or decimal)
   */
  readNumber() {
    const start = this.position
    
    while (!this.isAtEnd() && this.isDigit(this.peek())) {
      this.advance()
    }

    // Check for decimal part
    if (!this.isAtEnd() && this.peek() === '.' && this.isDigit(this.peekNext())) {
      this.advance() // consume the '.'
      while (!this.isAtEnd() && this.isDigit(this.peek())) {
        this.advance()
      }
    }

    const value = parseFloat(this.input.substring(start, this.position))
    return new Token(TokenType.NUMBER, value, start)
  }

  /**
   * Read a string token (double or single quoted)
   */
  readString(quote) {
    const start = this.position
    this.advance() // consume opening quote
    
    let value = ''
    while (!this.isAtEnd() && this.peek() !== quote) {
      if (this.peek() === '\\' && this.peekNext() === quote) {
        this.advance() // skip escape character
      }
      value += this.advance()
    }

    if (this.isAtEnd()) {
      throw new FormulaError(`Unterminated string starting at position ${start}`, start)
    }

    this.advance() // consume closing quote
    return new Token(TokenType.STRING, value, start)
  }

  /**
   * Read an identifier (function name or boolean)
   */
  readIdentifier() {
    const start = this.position
    
    while (!this.isAtEnd() && this.isAlphaNumeric(this.peek())) {
      this.advance()
    }

    const value = this.input.substring(start, this.position)
    const upperValue = value.toUpperCase()

    // Check for boolean literals
    if (upperValue === 'TRUE') {
      return new Token(TokenType.BOOLEAN, true, start)
    }
    if (upperValue === 'FALSE') {
      return new Token(TokenType.BOOLEAN, false, start)
    }

    return new Token(TokenType.IDENTIFIER, upperValue, start)
  }

  /**
   * Read a field reference {field_id}
   */
  readFieldRef() {
    const start = this.position
    this.advance() // consume '{'
    
    let fieldId = ''
    while (!this.isAtEnd() && this.peek() !== '}') {
      fieldId += this.advance()
    }

    if (this.isAtEnd()) {
      throw new FormulaError(`Unterminated field reference starting at position ${start}`, start)
    }

    this.advance() // consume '}'
    return new Token(TokenType.FIELD_REF, fieldId.trim(), start)
  }

  /**
   * Tokenize the entire input
   */
  tokenize() {
    this.tokens = []
    this.position = 0

    while (!this.isAtEnd()) {
      this.skipWhitespace()
      
      if (this.isAtEnd()) break

      const char = this.peek()
      const start = this.position

      // Numbers
      if (this.isDigit(char)) {
        this.tokens.push(this.readNumber())
        continue
      }

      // Strings
      if (char === '"' || char === "'") {
        this.tokens.push(this.readString(char))
        continue
      }

      // Identifiers and booleans
      if (this.isAlpha(char)) {
        this.tokens.push(this.readIdentifier())
        continue
      }

      // Field references
      if (char === '{') {
        this.tokens.push(this.readFieldRef())
        continue
      }

      // Two-character operators
      if (char === '<' && this.peekNext() === '>') {
        this.advance()
        this.advance()
        this.tokens.push(new Token(TokenType.COMPARISON, '<>', start))
        continue
      }
      if (char === '<' && this.peekNext() === '=') {
        this.advance()
        this.advance()
        this.tokens.push(new Token(TokenType.COMPARISON, '<=', start))
        continue
      }
      if (char === '>' && this.peekNext() === '=') {
        this.advance()
        this.advance()
        this.tokens.push(new Token(TokenType.COMPARISON, '>=', start))
        continue
      }

      // Single-character tokens
      switch (char) {
        case '(':
          this.tokens.push(new Token(TokenType.LPAREN, '(', start))
          this.advance()
          break
        case ')':
          this.tokens.push(new Token(TokenType.RPAREN, ')', start))
          this.advance()
          break
        case ',':
          this.tokens.push(new Token(TokenType.COMMA, ',', start))
          this.advance()
          break
        case '+':
        case '-':
        case '*':
        case '/':
          this.tokens.push(new Token(TokenType.OPERATOR, char, start))
          this.advance()
          break
        case '=':
        case '<':
        case '>':
          this.tokens.push(new Token(TokenType.COMPARISON, char, start))
          this.advance()
          break
        default:
          throw new FormulaError(`Unexpected character '${char}' at position ${start}`, start)
      }
    }

    this.tokens.push(new Token(TokenType.EOF, null, this.position))
    return this.tokens
  }
}
