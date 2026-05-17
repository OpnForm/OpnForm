import { describe, expect, it } from 'vitest'
import { parseImportedFormData, resolveCreateFormState } from '../../lib/forms/resolve-create-form-state.js'

describe('resolveCreateFormState', () => {
  const baseFormData = {
    title: 'Base Form',
    presentation_style: 'classic',
    properties: [],
  }

  it('prefers imported form data over template structure', () => {
    const resolved = resolveCreateFormState(baseFormData, {
      importedFormData: JSON.stringify({
        title: 'Imported Form',
        presentation_style: 'focused',
      }),
      templateStructure: {
        title: 'Template Form',
      },
    })

    expect(resolved).toEqual({
      formData: {
        ...baseFormData,
        title: 'Imported Form',
        presentation_style: 'focused',
      },
      showInitialModal: false,
    })
  })

  it('falls back to template structure when there is no imported form data', () => {
    const resolved = resolveCreateFormState(baseFormData, {
      templateStructure: {
        title: 'Template Form',
      },
    })

    expect(resolved).toEqual({
      formData: {
        ...baseFormData,
        title: 'Template Form',
      },
      showInitialModal: false,
    })
  })

  it('shows the initial modal when no imported or template data is available', () => {
    const resolved = resolveCreateFormState(baseFormData)

    expect(resolved).toEqual({
      formData: baseFormData,
      showInitialModal: true,
    })
  })
})

describe('parseImportedFormData', () => {
  it('returns null for malformed imported form data', () => {
    expect(parseImportedFormData('{bad json')).toBeNull()
  })
})
