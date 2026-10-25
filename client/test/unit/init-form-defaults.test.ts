import { describe, expect, it } from 'vitest'
import { setFormDefaults } from '../../composables/forms/initForm.js'

describe('setFormDefaults', () => {
  it('does not invent respondent-facing field labels', () => {
    const result = setFormDefaults({
      properties: [
        { id: 'missing', type: 'text' },
        { id: 'blank', name: '', type: 'email' },
      ],
    })

    expect(result.properties[0]).not.toHaveProperty('name')
    expect(result.properties[1].name).toBe('')
  })

  it('discards entries that cannot represent form blocks', () => {
    const result = setFormDefaults({
      properties: [
        'malformed',
        null,
        ['also-malformed'],
        { id: 'kept', name: 'Kept', type: 'text' },
      ],
    })

    expect(result.properties).toEqual([
      { id: 'kept', name: 'Kept', type: 'text' },
    ])
    expect(setFormDefaults({ properties: 'malformed' }).properties).toEqual([])
  })
})
