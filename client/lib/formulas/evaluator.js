import { NodeType, FormulaError } from './types.js'
import { Parser } from './parser.js'
import { functions } from './functions/index.js'

/**
 * Evaluator for formula AST
 * Evaluates an AST with a given data context
 */
export class Evaluator {
  constructor(context = {}) {
    this.context = context // { fieldId: value, ... }
  }

  /**
   * Evaluate a formula string with context
   */
  static evaluate(formula, context = {}) {
    try {
      const ast = Parser.parse(formula)
      const evaluator = new Evaluator(context)
      return evaluator.evaluate(ast)
    } catch (error) {
      if (error instanceof FormulaError) {
        return null
      }
      throw error
    }
  }

  /**
   * Evaluate an AST node
   */
  evaluate(node) {
    if (!node) return null

    switch (node.type) {
      case NodeType.NUMBER:
        return node.value

      case NodeType.STRING:
        return node.value

      case NodeType.BOOLEAN:
        return node.value

      case NodeType.FIELD:
        return this.evaluateField(node)

      case NodeType.BINARY:
        return this.evaluateBinary(node)

      case NodeType.UNARY:
        return this.evaluateUnary(node)

      case NodeType.FUNCTION:
        return this.evaluateFunction(node)

      default:
        throw new FormulaError(`Unknown node type: ${node.type}`)
    }
  }

  /**
   * Evaluate field reference
   */
  evaluateField(node) {
    const value = this.context[node.id]
    
    // Return null for missing fields
    if (value === undefined) {
      return null
    }
    
    return value
  }

  /**
   * Evaluate binary operation
   */
  evaluateBinary(node) {
    const left = this.evaluate(node.left)
    const right = this.evaluate(node.right)

    // Handle comparison operators
    switch (node.operator) {
      case '=':
        return this.compareEqual(left, right)
      case '<>':
        return !this.compareEqual(left, right)
      case '<':
        return this.compareLessThan(left, right)
      case '>':
        return this.compareGreaterThan(left, right)
      case '<=':
        return this.compareLessThanOrEqual(left, right)
      case '>=':
        return this.compareGreaterThanOrEqual(left, right)
    }

    // Handle arithmetic operators
    const leftNum = this.toNumber(left)
    const rightNum = this.toNumber(right)

    // If either operand is null for arithmetic, return null
    if (leftNum === null || rightNum === null) {
      // Special case: string concatenation with +
      if (node.operator === '+' && (typeof left === 'string' || typeof right === 'string')) {
        return String(left ?? '') + String(right ?? '')
      }
      return null
    }

    switch (node.operator) {
      case '+':
        return leftNum + rightNum
      case '-':
        return leftNum - rightNum
      case '*':
        return leftNum * rightNum
      case '/':
        if (rightNum === 0) {
          return null // Division by zero returns null
        }
        return leftNum / rightNum
      default:
        throw new FormulaError(`Unknown operator: ${node.operator}`)
    }
  }

  /**
   * Evaluate unary operation
   */
  evaluateUnary(node) {
    const operand = this.evaluate(node.operand)

    switch (node.operator) {
      case '-': {
        const num = this.toNumber(operand)
        return num === null ? null : -num
      }
      case 'NOT':
        return !this.toBoolean(operand)
      default:
        throw new FormulaError(`Unknown unary operator: ${node.operator}`)
    }
  }

  /**
   * Evaluate function call
   */
  evaluateFunction(node) {
    const funcName = node.name.toUpperCase()
    const func = functions[funcName]

    if (!func) {
      throw new FormulaError(`Unknown function: ${funcName}`)
    }

    // Evaluate all arguments
    const args = node.args.map(arg => this.evaluate(arg))

    try {
      return func(...args)
    } catch {
      // Function errors return null
      return null
    }
  }

  /**
   * Convert value to number
   */
  toNumber(value) {
    if (value === null || value === undefined || value === '') {
      return null
    }
    if (typeof value === 'number') {
      return value
    }
    if (typeof value === 'boolean') {
      return value ? 1 : 0
    }
    const num = Number(value)
    return isNaN(num) ? null : num
  }

  /**
   * Convert value to boolean
   */
  toBoolean(value) {
    if (value === null || value === undefined || value === '') {
      return false
    }
    if (typeof value === 'boolean') {
      return value
    }
    if (typeof value === 'number') {
      return value !== 0
    }
    if (typeof value === 'string') {
      const lower = value.toLowerCase()
      if (lower === 'false' || lower === 'no' || lower === '0') {
        return false
      }
      return true
    }
    return Boolean(value)
  }

  /**
   * Compare two values for equality
   */
  compareEqual(left, right) {
    // Handle null comparisons
    if (left === null && right === null) return true
    if (left === null || right === null) return false

    // Try numeric comparison first
    const leftNum = this.toNumber(left)
    const rightNum = this.toNumber(right)
    if (leftNum !== null && rightNum !== null) {
      return leftNum === rightNum
    }

    // String comparison (case-insensitive)
    if (typeof left === 'string' || typeof right === 'string') {
      return String(left).toLowerCase() === String(right).toLowerCase()
    }

    // Boolean comparison
    return left === right
  }

  /**
   * Compare less than
   */
  compareLessThan(left, right) {
    const leftNum = this.toNumber(left)
    const rightNum = this.toNumber(right)
    if (leftNum === null || rightNum === null) return false
    return leftNum < rightNum
  }

  /**
   * Compare greater than
   */
  compareGreaterThan(left, right) {
    const leftNum = this.toNumber(left)
    const rightNum = this.toNumber(right)
    if (leftNum === null || rightNum === null) return false
    return leftNum > rightNum
  }

  /**
   * Compare less than or equal
   */
  compareLessThanOrEqual(left, right) {
    const leftNum = this.toNumber(left)
    const rightNum = this.toNumber(right)
    if (leftNum === null || rightNum === null) return false
    return leftNum <= rightNum
  }

  /**
   * Compare greater than or equal
   */
  compareGreaterThanOrEqual(left, right) {
    const leftNum = this.toNumber(left)
    const rightNum = this.toNumber(right)
    if (leftNum === null || rightNum === null) return false
    return leftNum >= rightNum
  }
}

/**
 * Convenience function for evaluating formulas
 */
export function evaluate(formula, context = {}) {
  return Evaluator.evaluate(formula, context)
}
