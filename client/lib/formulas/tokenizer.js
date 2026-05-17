/**
 * Formula Tokenizer
 * 
 * Tokenizes a formula string into typed tokens for syntax highlighting
 * and other processing.
 */

import { getFunctionNames } from './functions/index.js'

// Default function names as fallback
const DEFAULT_FUNCTION_NAMES = [
  'SUM', 'AVERAGE', 'MIN', 'MAX', 'ROUND', 'FLOOR', 'CEIL', 'ABS', 'MOD', 'POWER', 'SQRT',
  'CONCAT', 'UPPER', 'LOWER', 'TRIM', 'LEFT', 'RIGHT', 'MID', 'LEN', 'SUBSTITUTE', 'REPLACE', 'FIND', 'SEARCH', 'REPT', 'TEXT',
  'IF', 'AND', 'OR', 'NOT', 'XOR', 'ISBLANK', 'ISNUMBER', 'ISTEXT', 'IFERROR', 'IFBLANK', 'COALESCE', 'SWITCH', 'IFS', 'CHOOSE',
  'COUNT', 'ISEMPTY', 'CONTAINS', 'JOIN'
]

/**
 * Token types produced by the tokenizer
 */
export const TokenTypes = {
  PILL: 'pill',        // Field/variable reference that was resolved
  FIELD_REF: 'field',  // Unresolved {fieldId} reference
  FUNCTION: 'function',
  STRING: 'string',
  NUMBER: 'number',
  OPERATOR: 'operator',
  TEXT: 'text'
}

/**
 * Get list of known function names
 * @returns {string[]}
 */
export function getKnownFunctionNames() {
  try {
    const names = getFunctionNames()
    return names.length > 0 ? names : DEFAULT_FUNCTION_NAMES
  } catch {
    return DEFAULT_FUNCTION_NAMES
  }
}

/**
 * Tokenize a formula into parts for syntax highlighting
 * 
 * @param {string} formula - The formula string to tokenize
 * @param {Map<string, {id: string, name: string, type: string}>} fieldMap - Map of field IDs to field info
 * @param {string[]} [functionNames] - Optional list of function names (defaults to known functions)
 * @returns {Array<{type: string, value?: string, id?: string, name?: string, fieldType?: string}>}
 */
export function tokenizeFormula(formula, fieldMap = new Map(), functionNames = null) {
  if (!formula) return []
  
  const tokens = []
  let remaining = formula
  
  // Use provided function names or get defaults
  const funcNames = functionNames ?? getKnownFunctionNames()
  
  // Build function pattern with fallback
  const funcPatternStr = funcNames.length > 0 
    ? `^(${funcNames.join('|')})(?=\\s*\\()`
    : '^$' // Never match if no functions
  
  while (remaining.length > 0) {
    // Try to match field reference {fieldId}
    const fieldMatch = remaining.match(/^\{([^}]+)\}/)
    if (fieldMatch) {
      const fieldId = fieldMatch[1]
      const field = fieldMap.get(fieldId)
      if (field) {
        tokens.push({ 
          type: TokenTypes.PILL, 
          id: fieldId, 
          name: field.name, 
          fieldType: field.type 
        })
      } else {
        // Unresolved field reference - keep as text
        tokens.push({ type: TokenTypes.TEXT, value: fieldMatch[0] })
      }
      remaining = remaining.slice(fieldMatch[0].length)
      continue
    }
    
    // Try to match function name (followed by parenthesis)
    if (funcNames.length > 0) {
      const funcPattern = new RegExp(funcPatternStr, 'i')
      const funcMatch = remaining.match(funcPattern)
      if (funcMatch) {
        tokens.push({ type: TokenTypes.FUNCTION, value: funcMatch[1] })
        remaining = remaining.slice(funcMatch[1].length)
        continue
      }
    }
    
    // Try to match string literals
    const stringMatch = remaining.match(/^("[^"]*"|'[^']*')/)
    if (stringMatch) {
      tokens.push({ type: TokenTypes.STRING, value: stringMatch[0] })
      remaining = remaining.slice(stringMatch[0].length)
      continue
    }
    
    // Try to match numbers (including decimals)
    const numberMatch = remaining.match(/^\d+\.?\d*/)
    if (numberMatch) {
      tokens.push({ type: TokenTypes.NUMBER, value: numberMatch[0] })
      remaining = remaining.slice(numberMatch[0].length)
      continue
    }
    
    // Try to match comparison operators (multi-char first)
    const compMatch = remaining.match(/^(<=|>=|<>|!=|==|<|>|=)/)
    if (compMatch) {
      tokens.push({ type: TokenTypes.OPERATOR, value: compMatch[0] })
      remaining = remaining.slice(compMatch[0].length)
      continue
    }
    
    // Try to match arithmetic operators and parentheses
    const opMatch = remaining.match(/^[+\-*/(),]/)
    if (opMatch) {
      tokens.push({ type: TokenTypes.OPERATOR, value: opMatch[0] })
      remaining = remaining.slice(opMatch[0].length)
      continue
    }
    
    // Take one character as plain text
    tokens.push({ type: TokenTypes.TEXT, value: remaining[0] })
    remaining = remaining.slice(1)
  }
  
  return tokens
}
