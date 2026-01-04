/**
 * Formula Engine - Main Entry Point
 * 
 * Provides formula parsing, evaluation, and validation for computed variables
 */

export { TokenType, NodeType, FormulaError, ValidationResult } from './types.js'
export { Lexer } from './lexer.js'
export { Parser } from './parser.js'
export { Evaluator, evaluate } from './evaluator.js'
export { Validator, validateFormula } from './validator.js'
export { DependencyGraph, buildDependencyGraph } from './dependency-graph.js'
export { functions, functionMeta, getFunctionsByCategory, getFunctionNames, hasFunction, getFunction } from './functions/index.js'

/**
 * Parse and evaluate a formula in one step
 */
export function evaluateFormula(formula, context = {}) {
  return evaluate(formula, context)
}

/**
 * Parse a formula and return the AST
 */
export function parseFormula(formula) {
  return Parser.parse(formula)
}

/**
 * Validate a formula
 */
export function validate(formula, options = {}) {
  return validateFormula(formula, options)
}

/**
 * Extract field references from a formula
 */
export function extractFieldIds(formula) {
  return Validator.extractFieldReferences(formula)
}

/**
 * Convert a formula with field IDs to display format with field names
 * @param {string} formula - Formula with {field_id} references
 * @param {Array} fields - Array of { id, name } objects
 * @param {Array} variables - Array of { id, name } computed variables
 * @returns {string} Formula with display names
 */
export function formulaToDisplay(formula, fields = [], variables = []) {
  if (!formula) return ''
  
  const lookup = new Map()
  for (const field of fields) {
    lookup.set(field.id, field.name)
  }
  for (const variable of variables) {
    lookup.set(variable.id, variable.name)
  }

  return formula.replace(/\{([^}]+)\}/g, (match, id) => {
    const name = lookup.get(id.trim())
    return name ? `{${name}}` : match
  })
}

/**
 * Convert a formula with field names to storage format with field IDs
 * @param {string} formula - Formula with {Field Name} references
 * @param {Array} fields - Array of { id, name } objects
 * @param {Array} variables - Array of { id, name } computed variables
 * @returns {string} Formula with field IDs
 */
export function formulaToStorage(formula, fields = [], variables = []) {
  if (!formula) return ''
  
  const lookup = new Map()
  for (const field of fields) {
    lookup.set(field.name.toLowerCase(), field.id)
  }
  for (const variable of variables) {
    lookup.set(variable.name.toLowerCase(), variable.id)
  }

  return formula.replace(/\{([^}]+)\}/g, (match, name) => {
    const id = lookup.get(name.trim().toLowerCase())
    return id ? `{${id}}` : match
  })
}
