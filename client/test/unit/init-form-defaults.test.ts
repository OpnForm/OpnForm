import { describe, expect, it } from 'vitest'
import { applyEditorFieldDefaults, applyEditorSettingsDefaults, setFormDefaults } from '../../composables/forms/initForm.js'

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


describe('editor settings initialization', () => {
  it.each([undefined, null, [], {}])('normalizes empty settings before taking a clean snapshot (%j)', (empty) => {
    const form = applyEditorSettingsDefaults({ seo_meta: empty, analytics: empty, translations: empty })
    expect(form.seo_meta).toEqual({ page_title: null, page_description: null, page_thumbnail: null, page_favicon: null })
    expect(form.analytics).toEqual({ provider: null, tracking_id: null })
    expect(form.translations).toEqual({})
  })

  it('preserves configured settings and is stable on repeated initialization', () => {
    const configured = { seo_meta: { page_title: 'My form', extra: true }, analytics: { provider: 'gtm', tracking_id: 'GTM-TEST' }, translations: { focused_next_button_text: 'Continue' } }
    const form = applyEditorSettingsDefaults(structuredClone(configured))
    expect(form.seo_meta).toMatchObject(configured.seo_meta)
    expect(form.analytics).toEqual(configured.analytics)
    expect(form.translations).toEqual(configured.translations)
    const snapshot = JSON.stringify(form)
    expect(JSON.stringify(applyEditorSettingsDefaults(form))).toBe(snapshot)
  })
})
