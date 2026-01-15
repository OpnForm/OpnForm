import { describe, it, expect } from 'vitest'
import { Validator, validateFormula } from '../../../lib/formulas/validator.js'

describe('Formula Validator', () => {
  describe('syntax validation', () => {
    it('validates correct syntax', () => {
      const result = validateFormula('1 + 2')
      expect(result.valid).toBe(true)
      expect(result.errors).toHaveLength(0)
    })

    it('rejects empty formulas', () => {
      const result = validateFormula('')
      expect(result.valid).toBe(false)
      expect(result.errors[0].message).toContain('empty')
    })

    it('rejects invalid syntax', () => {
      const result = validateFormula('1 + + 2')
      expect(result.valid).toBe(false)
    })

    it('rejects unterminated strings', () => {
      const result = validateFormula('"unterminated')
      expect(result.valid).toBe(false)
    })
  })

  describe('field reference validation', () => {
    const fields = [
      { id: 'field1', name: 'Field 1' },
      { id: 'field2', name: 'Field 2' }
    ]

    it('validates known field references', () => {
      const result = validateFormula('{field1} + {field2}', { availableFields: fields })
      expect(result.valid).toBe(true)
    })

    it('rejects unknown field references', () => {
      const result = validateFormula('{unknown_field}', { availableFields: fields })
      expect(result.valid).toBe(false)
      expect(result.errors[0].message).toContain('Unknown field')
    })

    it('suggests similar field names', () => {
      const result = validateFormula('{field}', { availableFields: fields })
      expect(result.valid).toBe(false)
      expect(result.errors[0].message).toContain('Did you mean')
    })
  })

  describe('function validation', () => {
    it('validates known functions', () => {
      const result = validateFormula('SUM(1, 2, 3)')
      expect(result.valid).toBe(true)
    })

    it('rejects unknown functions', () => {
      const result = validateFormula('UNKNOWN_FUNC()')
      expect(result.valid).toBe(false)
      expect(result.errors[0].message).toContain('Unknown function')
    })

    it('suggests similar function names', () => {
      const result = validateFormula('SUMM(1, 2)')
      expect(result.valid).toBe(false)
      expect(result.errors[0].message).toContain('Did you mean')
    })
  })

  describe('computed variable validation', () => {
    const fields = [{ id: 'field1', name: 'Field 1' }]
    const variables = [
      { id: 'cv_var1', name: 'Variable 1' },
      { id: 'cv_var2', name: 'Variable 2' }
    ]

    it('validates references to computed variables', () => {
      const result = validateFormula('{cv_var1} + {field1}', {
        availableFields: fields,
        availableVariables: variables
      })
      expect(result.valid).toBe(true)
    })

    it('detects self-reference', () => {
      const result = validateFormula('{cv_var1} + 1', {
        availableFields: fields,
        availableVariables: variables,
        currentVariableId: 'cv_var1'
      })
      expect(result.valid).toBe(false)
      expect(result.errors[0].message).toContain('reference itself')
    })
  })

  describe('extractFieldReferences', () => {
    it('extracts field IDs from formula', () => {
      const refs = Validator.extractFieldReferences('{field1} + {field2} * {field3}')
      expect(refs).toEqual(['field1', 'field2', 'field3'])
    })

    it('handles formulas without field references', () => {
      const refs = Validator.extractFieldReferences('1 + 2 + 3')
      expect(refs).toEqual([])
    })

    it('handles duplicate field references', () => {
      const refs = Validator.extractFieldReferences('{field1} + {field1}')
      expect(refs).toEqual(['field1', 'field1'])
    })
  })
})
