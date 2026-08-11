import { describe, expect, it } from 'vitest'
import { normalizeFormSaveErrors } from '../../lib/forms/form-save-errors.js'

const properties = [
  { id: 'title', name: 'Title', type: 'nf-text' },
  { id: 'email', name: 'Email', type: 'email' },
  { id: 'pride-list', name: 'siehe Pride-Liste', type: 'select' },
]

const computedVariables = [
  { id: 'cv_total', name: 'Order total', formula: '1 + 1' },
  { id: 'cv_tax', name: 'Tax', formula: '2' },
]

describe('normalizeFormSaveErrors', () => {
  it('maps a property validation error to its form field', () => {
    const summary = normalizeFormSaveErrors({
      message: 'At least one option is required. (and 1 more error)',
      errors: {
        'properties.2.select.options': ['At least one option is required.'],
        properties: ['One or more properties have validation errors.'],
      },
    }, properties)

    expect(summary).toMatchObject({
      fieldCount: 1,
      issueCount: 1,
      generalErrors: [],
      fallbackMessage: null,
    })
    expect(summary.fieldGroups[0]).toEqual({
      key: 'property:2',
      fieldIndex: 2,
      fieldId: 'pride-list',
      fieldName: 'siehe Pride-Liste',
      fieldType: 'select',
      messages: ['At least one option is required.'],
      targetTab: 'options',
      canNavigate: true,
    })
  })

  it('groups and deduplicates multiple messages for the same field', () => {
    const summary = normalizeFormSaveErrors({
      errors: {
        'properties.2.select.options': [
          'At least one option is required.',
          'At least one option is required.',
        ],
        'properties.2.option_display_mode': 'The option display mode is invalid.',
      },
    }, properties)

    expect(summary.fieldGroups).toHaveLength(1)
    expect(summary.fieldGroups[0].messages).toEqual([
      'At least one option is required.',
      'The option display mode is invalid.',
    ])
  })

  it('keeps errors from different fields separate', () => {
    const summary = normalizeFormSaveErrors({
      errors: {
        'properties.1.name': ['The field name is required.'],
        'properties.2.select.options': ['At least one option is required.'],
      },
    }, properties)

    expect(summary.fieldGroups.map(group => group.fieldName)).toEqual([
      'Email',
      'siehe Pride-Liste',
    ])
    expect(summary.issueCount).toBe(2)
  })

  it('targets the logic tab when a field logic error is returned', () => {
    const summary = normalizeFormSaveErrors({
      errors: {
        'properties.1.logic': ['The logic actions for Email are not valid.'],
      },
    }, properties)

    expect(summary.fieldGroups[0]).toMatchObject({
      fieldId: 'email',
      targetTab: 'logic',
    })
  })

  it('keeps useful general errors alongside field errors', () => {
    const summary = normalizeFormSaveErrors({
      errors: {
        title: ['The title field is required.'],
        'properties.2.select.options': ['At least one option is required.'],
      },
    }, properties)

    expect(summary.generalErrors).toEqual([{
      key: 'title',
      label: 'Title',
      messages: ['The title field is required.'],
    }])
    expect(summary.issueCount).toBe(2)
  })

  it('falls back safely when the property index cannot be resolved', () => {
    const summary = normalizeFormSaveErrors({
      errors: {
        'properties.9.type': ['The field type is required.'],
      },
    }, properties)

    expect(summary.fieldGroups[0]).toMatchObject({
      fieldIndex: 9,
      fieldId: null,
      fieldName: 'Field 10',
      fieldType: null,
      canNavigate: false,
    })
  })

  it('preserves substantive computed variable errors while removing the aggregate message', () => {
    const summary = normalizeFormSaveErrors({
      errors: {
        computed_variables: [
          'Circular dependency detected between Total and Tax.',
          'One or more computed variables have validation errors.',
        ],
      },
    }, properties)

    expect(summary.generalErrors).toEqual([{
      key: 'computed_variables',
      label: 'Computed variables',
      messages: ['Circular dependency detected between Total and Tax.'],
    }])
  })

  it('groups indexed computed variable errors with the variable identity', () => {
    const summary = normalizeFormSaveErrors({
      errors: {
        'computed_variables.1.id': ['Duplicate computed variable ID.'],
        'computed_variables.1.formula': ['The formula is required.'],
        computed_variables: ['One or more computed variables have validation errors.'],
      },
    }, properties, computedVariables)

    expect(summary.computedVariableGroups).toEqual([{
      key: 'computed-variable:1',
      variableIndex: 1,
      variableId: 'cv_tax',
      variableName: 'Tax',
      messages: ['Duplicate computed variable ID.', 'The formula is required.'],
      canNavigate: true,
    }])
    expect(summary).toMatchObject({
      computedVariableCount: 1,
      issueCount: 1,
      generalErrors: [],
    })
  })

  it('uses the response message only when no structured error remains', () => {
    const summary = normalizeFormSaveErrors({
      message: 'Unable to validate the form.',
      errors: {
        properties: ['One or more properties have validation errors.'],
      },
    }, properties)

    expect(summary).toMatchObject({
      fieldGroups: [],
      generalErrors: [],
      issueCount: 0,
      fallbackMessage: 'Unable to validate the form.',
    })
  })

  it('provides a safe fallback for a malformed response', () => {
    expect(normalizeFormSaveErrors(null, properties).fallbackMessage)
      .toBe('An unknown validation error occurred.')
  })
})
