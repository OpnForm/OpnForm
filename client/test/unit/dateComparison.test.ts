import { describe, expect, it } from 'vitest'
import cases from '../fixtures/date-comparisons.json'
import { conditionsMet } from '../../lib/forms/FormLogicConditionChecker.js'

describe('strict date comparisons', () => {
  it.each(cases)('compares $left $operator $right', ({ left, right, operator, expected, with_time = true }) => {
    const condition = { value: { property_meta: { id: 'end', type: 'date', with_time }, operator,
      value: '<span mention="true" mention-field-id="start">Start</span>',
    } }
    expect(conditionsMet(condition, { end: left, start: right })).toBe(expected)
  })
})


it('uses owning field metadata over stale condition metadata', () => {
  const condition = { value: { property_meta: { id: 'end', type: 'date', with_time: true }, operator: 'equals', value: '2026-10-30T00:00:00-04:00' } }
  expect(conditionsMet(condition, { end: '2026-10-30T00:00:00+01:00' }, [{ id: 'end', type: 'date', with_time: false }])).toBe(true)
})
