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

describe('FormLogicConditionChecker mention values', () => {
  const mentionHtml = (fieldId: string, fieldName: string, fallback = '') => {
    return `<span mention="true" mention-field-id="${fieldId}" mention-field-name="${fieldName}" mention-fallback="${fallback}">@${fieldName}</span>`
  }

  it('resolves a single bare mention without DOMParser', () => {
    const conditions = {
      value: {
        operator: 'equals',
        value: mentionHtml('other_field', 'Other Field'),
        property_meta: {
          id: 'text_field',
          type: 'text'
        }
      }
    }

    expect(conditionsMet(conditions, {
      text_field: 'hello',
      other_field: 'hello'
    })).toBe(true)
  })

  it('preserves raw numeric values for single bare mentions', () => {
    const conditions = {
      value: {
        operator: 'greater_than',
        value: mentionHtml('threshold_field', 'Threshold'),
        property_meta: {
          id: 'number_field',
          type: 'number'
        }
      }
    }

    expect(conditionsMet(conditions, {
      number_field: 50,
      threshold_field: 40
    })).toBe(true)

    expect(conditionsMet(conditions, {
      number_field: 30,
      threshold_field: 40
    })).toBe(false)
  })

  it('resolves mixed mention content to plain text without DOMParser', () => {
    const conditions = {
      value: {
        operator: 'equals',
        value: `Hello ${mentionHtml('name_field', 'Name')}`,
        property_meta: {
          id: 'text_field',
          type: 'text'
        }
      }
    }

    expect(conditionsMet(conditions, {
      text_field: 'Hello Alice',
      name_field: 'Alice'
    })).toBe(true)
  })
})
