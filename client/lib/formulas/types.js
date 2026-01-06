/**
 * Token types for the formula lexer
 */
export const TokenType = {
  NUMBER: 'NUMBER',
  STRING: 'STRING',
  BOOLEAN: 'BOOLEAN',
  IDENTIFIER: 'IDENTIFIER',
  FIELD_REF: 'FIELD_REF',
  OPERATOR: 'OPERATOR',
  COMPARISON: 'COMPARISON',
  LPAREN: 'LPAREN',
  RPAREN: 'RPAREN',
  COMMA: 'COMMA',
  EOF: 'EOF'
}

/**
 * AST node types
 */
export const NodeType = {
  NUMBER: 'number',
  STRING: 'string',
  BOOLEAN: 'boolean',
  FIELD: 'field',
  BINARY: 'binary',
  UNARY: 'unary',
  FUNCTION: 'function'
}

/**
 * Formula error class
 */
export class FormulaError extends Error {
  constructor(message, position = null, type = 'error') {
    super(message)
    this.name = 'FormulaError'
    this.position = position
    this.type = type // 'error', 'warning'
  }
}

/**
 * Validation result
 */
export class ValidationResult {
  constructor() {
    this.valid = true
    this.errors = []
    this.warnings = []
  }

  addError(message, position = null) {
    this.valid = false
    this.errors.push({ message, position, type: 'error' })
  }

  addWarning(message, position = null) {
    this.warnings.push({ message, position, type: 'warning' })
  }
}
