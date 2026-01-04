import { NodeType, FormulaError, ValidationResult } from './types.js'
import { Parser } from './parser.js'
import { hasFunction, getFunctionNames } from './functions/index.js'

/**
 * Validator for formula expressions
 * Validates syntax, field references, and function calls
 */
export class Validator {
  constructor(options = {}) {
    this.availableFields = options.availableFields || [] // Array of { id, name, type }
    this.availableVariables = options.availableVariables || [] // Array of { id, name }
    this.currentVariableId = options.currentVariableId || null // ID of variable being edited (to detect self-reference)
  }

  /**
   * Validate a formula string
   */
  validate(formula) {
    const result = new ValidationResult()

    if (!formula || formula.trim() === '') {
      result.addError('Formula cannot be empty')
      return result
    }

    try {
      // Parse the formula
      const ast = Parser.parse(formula)
      
      // Validate the AST
      this.validateNode(ast, result)
      
    } catch (error) {
      if (error instanceof FormulaError) {
        // Don't include position in error message - it's confusing with field pills
        // The position counts raw characters including {field_id} which appears as a pill
        const message = this.cleanErrorMessage(error.message)
        result.addError(message)
      } else {
        const message = this.cleanErrorMessage(error.message)
        result.addError(`Syntax error: ${message}`)
      }
    }

    return result
  }

  /**
   * Validate an AST node recursively
   */
  validateNode(node, result) {
    if (!node) return

    switch (node.type) {
      case NodeType.FIELD:
        this.validateFieldReference(node, result)
        break

      case NodeType.FUNCTION:
        this.validateFunctionCall(node, result)
        break

      case NodeType.BINARY:
        this.validateNode(node.left, result)
        this.validateNode(node.right, result)
        break

      case NodeType.UNARY:
        this.validateNode(node.operand, result)
        break

      // Literals are always valid
      case NodeType.NUMBER:
      case NodeType.STRING:
      case NodeType.BOOLEAN:
        break
    }
  }

  /**
   * Validate field reference
   */
  validateFieldReference(node, result) {
    const fieldId = node.id

    // Check for self-reference
    if (fieldId === this.currentVariableId) {
      result.addError(`Variable cannot reference itself`)
      return
    }

    // Check if field exists
    const field = this.availableFields.find(f => f.id === fieldId)
    const variable = this.availableVariables.find(v => v.id === fieldId)

    if (!field && !variable) {
      // Try to suggest similar field names
      const suggestion = this.findSimilarField(fieldId)
      if (suggestion) {
        result.addError(`Unknown field "${fieldId}". Did you mean "${suggestion}"?`)
      } else {
        result.addError(`Unknown field "${fieldId}"`)
      }
    }
  }

  /**
   * Validate function call
   */
  validateFunctionCall(node, result) {
    const funcName = node.name.toUpperCase()

    // Check if function exists
    if (!hasFunction(funcName)) {
      const suggestion = this.findSimilarFunction(funcName)
      if (suggestion) {
        result.addError(`Unknown function "${funcName}". Did you mean "${suggestion}"?`)
      } else {
        result.addError(`Unknown function "${funcName}"`)
      }
      return
    }

    // Validate function arguments
    for (const arg of node.args) {
      this.validateNode(arg, result)
    }
  }

  /**
   * Find similar field name for suggestions
   */
  findSimilarField(fieldId) {
    const allIds = [
      ...this.availableFields.map(f => f.id),
      ...this.availableVariables.map(v => v.id)
    ]

    for (const id of allIds) {
      if (this.levenshteinDistance(fieldId.toLowerCase(), id.toLowerCase()) <= 2) {
        return id
      }
    }

    return null
  }

  /**
   * Find similar function name for suggestions
   */
  findSimilarFunction(funcName) {
    const functionNames = getFunctionNames()

    for (const name of functionNames) {
      if (this.levenshteinDistance(funcName.toLowerCase(), name.toLowerCase()) <= 2) {
        return name
      }
    }

    return null
  }

  /**
   * Clean error message by removing confusing position references
   */
  cleanErrorMessage(message) {
    // Remove "at position X" since positions are confusing with field pills
    return message.replace(/\s+at position \d+/gi, '')
  }

  /**
   * Calculate Levenshtein distance between two strings
   */
  levenshteinDistance(a, b) {
    if (a.length === 0) return b.length
    if (b.length === 0) return a.length

    const matrix = []

    for (let i = 0; i <= b.length; i++) {
      matrix[i] = [i]
    }

    for (let j = 0; j <= a.length; j++) {
      matrix[0][j] = j
    }

    for (let i = 1; i <= b.length; i++) {
      for (let j = 1; j <= a.length; j++) {
        if (b.charAt(i - 1) === a.charAt(j - 1)) {
          matrix[i][j] = matrix[i - 1][j - 1]
        } else {
          matrix[i][j] = Math.min(
            matrix[i - 1][j - 1] + 1,
            matrix[i][j - 1] + 1,
            matrix[i - 1][j] + 1
          )
        }
      }
    }

    return matrix[b.length][a.length]
  }

  /**
   * Extract all field references from a formula
   */
  static extractFieldReferences(formula) {
    const references = []
    const regex = /\{([^}]+)\}/g
    let match

    while ((match = regex.exec(formula)) !== null) {
      references.push(match[1].trim())
    }

    return references
  }
}

/**
 * Convenience function for validating formulas
 */
export function validateFormula(formula, options = {}) {
  const validator = new Validator(options)
  return validator.validate(formula)
}
