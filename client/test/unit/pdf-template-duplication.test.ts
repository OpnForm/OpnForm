import { describe, expect, it } from 'vitest'
import { getPdfDestinationFields, getPdfTemplateFields, suggestPdfFieldMappings } from '../../lib/pdf-template-duplication'

describe('PDF template field mappings', () => {
  it('lists each mapped field once, preserving removed field labels and excluding static and special zones', () => {
    const template = { zone_mappings: [
      { field_id: 'name' }, { field_id: 'name' },
      { field_id: 'removed', field_name: 'Old label' },
      { field_id: 'submission_id' }, { static_text: 'Text' }, { static_image: 'image.png' },
      { field_id: 'total' },
    ] }
    const form = { properties: [{ id: 'name', name: 'Name', type: 'text' }], computed_variables: [{ id: 'total', name: 'Total' }] }
    expect(getPdfTemplateFields(template, form)).toEqual([
      { id: 'name', name: 'Name', type: 'text' },
      { id: 'removed', name: 'Old label', type: undefined },
      { id: 'total', name: 'Total', type: 'computed' },
    ])
  })

  it('suggests only unique name and type matches or identical field ids', () => {
    const source = [
      { id: 'same', name: 'Renamed', type: 'text' },
      { id: 'new', name: ' EMAIL ', type: 'email' },
      { id: 'ambiguous', name: 'Name', type: 'text' },
      { id: 'missing', name: 'Age', type: 'number' },
    ]
    const target = getPdfDestinationFields({ properties: [
      { id: 'same', name: 'Original', type: 'text' },
      { id: 'email', name: 'Email', type: 'email' },
      { id: 'one', name: 'Name', type: 'text' },
      { id: 'two', name: 'Name', type: 'text' },
      { id: 'section', name: 'Heading', type: 'nf-text' },
    ] })
    expect(target).toHaveLength(4)
    expect(suggestPdfFieldMappings(source, target)).toEqual({ same: 'same', new: 'email', ambiguous: undefined, missing: undefined })
  })
})
