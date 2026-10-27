import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { conditionsMet } from '~/lib/forms/FormLogicConditionChecker'

const cases = JSON.parse(readFileSync(new URL('../../../api/tests/fixtures/form-logic-parity.json', import.meta.url), 'utf8'))

describe('browser/API scalar and matrix parity', () => {
  it.each(cases)('$name', ({ group, children, type, operator, value, answer, expected }) => {
    const condition = group ? { operatorIdentifier: group, children } : { value: {
      property_meta: { id: 'field', type }, operator, value,
    } }
    expect(conditionsMet(condition, { field: answer })).toBe(expected)
  })
})
