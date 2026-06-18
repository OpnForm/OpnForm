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

describe('FormLogicConditionChecker unanswered negative value comparisons', () => {
  const condition = (type: string, operator: string, value: unknown, fieldId = 'field') => ({
    value: {
      operator,
      value,
      property_meta: {
        id: fieldId,
        type
      }
    }
  })

  it.each([
    ['text does_not_equal', condition('text', 'does_not_equal', 'blocked')],
    ['text does_not_contain', condition('text', 'does_not_contain', 'blocked')],
    ['text does_not_match_regex', condition('text', 'does_not_match_regex', '^blocked$')],
    ['text content_length_does_not_equal', condition('text', 'content_length_does_not_equal', 7)],
    ['number does_not_equal', condition('number', 'does_not_equal', 42)],
    ['select does_not_equal', condition('select', 'does_not_equal', 'blocked')],
    ['multi_select does_not_contain', condition('multi_select', 'does_not_contain', 'blocked')],
    ['matrix does_not_equal', condition('matrix', 'does_not_equal', { row1: 'blocked' })],
    ['matrix does_not_contain', condition('matrix', 'does_not_contain', { row1: 'blocked' })]
  ])('does not satisfy %s when the referenced field is unanswered', (_name, conditions) => {
    expect(conditionsMet(conditions, {})).toBe(false)
  })

  it('does not satisfy a mixed positive and negative AND group before every referenced field is answered', () => {
    const conditions = {
      operatorIdentifier: 'and',
      children: [
        condition('text', 'equals', 'yes', 'answered_field'),
        condition('text', 'does_not_equal', 'blocked', 'unanswered_field')
      ]
    }

    expect(conditionsMet(conditions, {
      answered_field: 'yes'
    })).toBe(false)
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
