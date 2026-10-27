import { describe, expect, it } from 'vitest'
import { applyEditorFieldDefaults, setFormDefaults } from '../../composables/forms/initForm.js'

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

describe('editor field initialization', () => {
  it('does not change a clean snapshot when fields mount', () => {
    const fields = applyEditorFieldDefaults([{ id: 'answer', name: 'Answer', type: 'text' }])
    const snapshot = JSON.stringify(fields)

    expect(fields[0]).toMatchObject({ placeholder: null, prefill: null, help: null, help_position: 'below_input' })
    expect(JSON.stringify(applyEditorFieldDefaults(fields))).toBe(snapshot)
  })

  it('preserves configured field options', () => {
    const fields = [{ id: 'answer', placeholder: 'Type here', prefill: 'Example', help: 'Required details', help_position: 'above_input' }]
    expect(applyEditorFieldDefaults(structuredClone(fields))).toEqual(fields)
  })
})
