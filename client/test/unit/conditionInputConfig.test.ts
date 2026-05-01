import { describe, expect, it } from 'vitest'
import {
  getConditionInputComponent,
  getMentionComputedVariables,
  getMentionFields,
} from '../../lib/forms/conditionInputConfig.js'

describe('condition input config', () => {
  it('uses MentionInput for computed conditions', () => {
    expect(getConditionInputComponent({ type: 'computed' })).toBe('MentionInput')
  })

  it('keeps select-like fields on select inputs', () => {
    expect(getConditionInputComponent({ type: 'select' })).toBe('SelectInput')
    expect(getConditionInputComponent({ type: 'multi_select' })).toBe('SelectInput')
  })

  it('excludes the current form field from mention fields', () => {
    const budget = { id: 'budget', name: 'Budget', type: 'number' }
    const email = { id: 'email', name: 'Email', type: 'email' }

    expect(getMentionFields([budget, email], budget)).toEqual([email])
  })

  it('excludes the current computed variable from mention variables', () => {
    const total = { id: 'cv_total', name: 'Total' }
    const tax = { id: 'cv_tax', name: 'Tax' }

    expect(getMentionComputedVariables([total, tax], total)).toEqual([tax])
  })
})
