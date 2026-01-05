import { NodeType, FormulaError, ValidationResult } from './types.js'
import { Parser } from './parser.js'
import { hasFunction, getFunctionNames, functionMeta } from './functions/index.js'

/**
 * Function argument requirements for validation
 */
const FUNCTION_ARGS = {
  // Math functions
  SUM: { min: 1 },
  AVERAGE: { min: 1 },
  MIN: { min: 1 },
  MAX: { min: 1 },
  ROUND: { min: 1, max: 2 },
  FLOOR: { min: 1, max: 1 },
  CEIL: { min: 1, max: 1 },
  ABS: { min: 1, max: 1 },
  MOD: { min: 2, max: 2 },
  POWER: { min: 2, max: 2 },
  SQRT: { min: 1, max: 1 },
  
  // Text functions
  CONCAT: { min: 1 },
  UPPER: { min: 1, max: 1 },
  LOWER: { min: 1, max: 1 },
  TRIM: { min: 1, max: 1 },
  LEFT: { min: 2, max: 2 },
  RIGHT: { min: 2, max: 2 },
  MID: { min: 3, max: 3 },
  LEN: { min: 1, max: 1 },
  SUBSTITUTE: { min: 3, max: 4 },
  REPLACE: { min: 4, max: 4 },
  FIND: { min: 2, max: 3 },
  SEARCH: { min: 2, max: 3 },
  REPT: { min: 2, max: 2 },
  TEXT: { min: 2, max: 2 },
  
  // Logic functions
  IF: { min: 2, max: 3 },
  AND: { min: 1 },
  OR: { min: 1 },
  NOT: { min: 1, max: 1 },
  XOR: { min: 2 },
  ISBLANK: { min: 1, max: 1 },
  ISNUMBER: { min: 1, max: 1 },
  ISTEXT: { min: 1, max: 1 },
  IFERROR: { min: 2, max: 2 },
  IFBLANK: { min: 2, max: 2 },
  COALESCE: { min: 1 },
  SWITCH: { min: 3 },
  IFS: { min: 2 },
  CHOOSE: { min: 2 },
  
  // Array functions
  COUNT: { min: 1, max: 1 },
  ISEMPTY: { min: 1, max: 1 },
  CONTAINS: { min: 2, max: 2 },
  JOIN: { min: 1, max: 2 }
}

/**
 * Validator for formula expressions
 * Validates syntax, field references, and function calls
 */
export class Validator {
  constructor(options = {}) {
    this.availableFields = options.availableFields || [] // Array of { id, name, type }
    this.availableVariables = options.availableVariables || [] // Array of { id, name }
    this.currentVariableId = options.currentVariableId || null // ID of variable being edited (to detect self-reference)
    
    // Build field name lookup map for better error messages
    this.fieldNameMap = {}
    for (const f of this.availableFields) {
      this.fieldNameMap[f.id] = f.name
    }
    for (const v of this.availableVariables) {
      this.fieldNameMap[v.id] = v.name
    }
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
      // Parse the formula with field names for better error messages
      const ast = Parser.parse(formula, { fieldNames: this.fieldNameMap })
      
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
   * Get display name for a field ID
   */
  getFieldDisplayName(fieldId) {
    return this.fieldNameMap[fieldId] || fieldId
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
      const varName = this.getFieldDisplayName(fieldId)
      result.addError(`Variable '${varName}' cannot reference itself. This would create a circular dependency.`)
      return
    }

    // Check if field exists
    const field = this.availableFields.find(f => f.id === fieldId)
    const variable = this.availableVariables.find(v => v.id === fieldId)

    if (!field && !variable) {
      // Check if it looks like a UUID (deleted field)
      const isUuid = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i.test(fieldId)
      if (isUuid) {
        result.addError(`This field was deleted or renamed. Please select a different field.`)
      } else {
        // Try to suggest similar field names
        const suggestion = this.findSimilarFieldByName(fieldId)
        if (suggestion) {
          result.addError(`Unknown field '${fieldId}'. Did you mean '${suggestion}'?`)
        } else {
          result.addError(`Unknown field '${fieldId}'`)
        }
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
        result.addError(`Unknown function '${funcName}'. Did you mean '${suggestion}'?`)
      } else {
        result.addError(`Unknown function '${funcName}'. Check the function reference for available functions.`)
      }
      return
    }

    // Validate argument count
    const argReqs = FUNCTION_ARGS[funcName]
    if (argReqs) {
      const argCount = node.args.length
      if (argReqs.min !== undefined && argCount < argReqs.min) {
        if (argReqs.max === argReqs.min) {
          result.addError(`Function ${funcName}() requires exactly ${argReqs.min} argument${argReqs.min === 1 ? '' : 's'}, but got ${argCount}.`)
        } else {
          result.addError(`Function ${funcName}() requires at least ${argReqs.min} argument${argReqs.min === 1 ? '' : 's'}, but got ${argCount}.`)
        }
        return
      }
      if (argReqs.max !== undefined && argCount > argReqs.max) {
        result.addError(`Function ${funcName}() accepts at most ${argReqs.max} argument${argReqs.max === 1 ? '' : 's'}, but got ${argCount}.`)
        return
      }
    }

    // Validate function arguments recursively
    for (const arg of node.args) {
      this.validateNode(arg, result)
    }
  }

  /**
   * Find similar field name for suggestions (by ID)
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
   * Find similar field by name for suggestions
   */
  findSimilarFieldByName(searchName) {
    const allNames = [
      ...this.availableFields.map(f => f.name),
      ...this.availableVariables.map(v => v.name)
    ]

    for (const name of allNames) {
      if (name && this.levenshteinDistance(searchName.toLowerCase(), name.toLowerCase()) <= 2) {
        return name
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

