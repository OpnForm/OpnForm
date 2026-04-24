import { describe, expect, it } from 'vitest'
import { conditionsMet } from '../../lib/forms/FormLogicConditionChecker.js'

describe('FormLogicConditionChecker computed variables', () => {
  it('evaluates numeric computed variable equality', () => {
    const conditions = {
      operatorIdentifier: undefined,
      value: {
        operator: 'equals',
        value: 10,
        property_meta: {
          id: 'cv_total',
          type: 'computed',
          result_type: 'number'
        }
      }
    }

    expect(conditionsMet(conditions, { cv_total: 10 })).toBe(true)
    expect(conditionsMet(conditions, { cv_total: 5 })).toBe(false)
  })

  it('evaluates numeric computed variable comparisons', () => {
    const conditions = {
      operatorIdentifier: undefined,
      value: {
        operator: 'greater_than',
        value: 10,
        property_meta: {
          id: 'cv_total',
          type: 'computed',
          result_type: 'number'
        }
      }
    }

    expect(conditionsMet(conditions, { cv_total: 12 })).toBe(true)
    expect(conditionsMet(conditions, { cv_total: 10 })).toBe(false)
  })

  it('evaluates string computed variable conditions', () => {
    const conditions = {
      operatorIdentifier: undefined,
      value: {
        operator: 'contains',
        value: 'VIP',
        property_meta: {
          id: 'cv_label',
          type: 'computed',
          result_type: 'string'
        }
      }
    }

    expect(conditionsMet(conditions, { cv_label: 'VIP customer' })).toBe(true)
    expect(conditionsMet(conditions, { cv_label: 'Standard customer' })).toBe(false)
  })
})
