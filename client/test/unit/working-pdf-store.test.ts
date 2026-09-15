import { beforeEach, describe, expect, it } from 'vitest'
import { createPinia, setActivePinia } from 'pinia'
import { useWorkingPdfStore } from '../../stores/working_pdf.js'

function createTemplateFixture(overrides = {}) {
  return {
    id: 999,
    name: 'Template',
    original_filename: 'template.pdf',
    filename_pattern: '',
    remove_branding: false,
    page_count: 2,
    zone_mappings: [],
    ...overrides,
  }
}

describe('working_pdf store - page_manifest model', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('creates default source page manifest when missing', () => {
    const store = useWorkingPdfStore()
    store.set(createTemplateFixture({ page_count: 3 }))

    expect(store.pageManifest).toHaveLength(3)
    expect(store.pageManifest.every((p) => p.type === 'source')).toBe(true)
    expect(store.getSourcePageNumber(1)).toBe(1)
    expect(store.getSourcePageNumber(2)).toBe(2)
    expect(store.getSourcePageNumber(3)).toBe(3)
  })

  it('duplicates page and clones zones to the new page_id', () => {
    const store = useWorkingPdfStore()
    store.set(createTemplateFixture({
      page_manifest: [
        { id: 'p1', type: 'source', source_page: 1 },
        { id: 'p2', type: 'source', source_page: 2 },
      ],
      zone_mappings: [
        {
          id: 'z1',
          page: 1,
          page_id: 'p1',
          x: 10,
          y: 10,
          width: 20,
          height: 10,
          field_id: 'submission_id',
          font_size: 12,
          font_color: '#000000',
        },
      ],
    }))

    store.duplicatePage(1)

    expect(store.pageManifest).toHaveLength(3)
    const duplicatedPage = store.pageManifest[1]
    expect(duplicatedPage.type).toBe('source')
    expect(duplicatedPage.source_page).toBe(1)
    expect(duplicatedPage.id).not.toBe('p1')

    const zonesOnDuplicated = store.zonesForPage(2)
    expect(zonesOnDuplicated).toHaveLength(1)
    expect(zonesOnDuplicated[0].id).not.toBe('z1')
    expect(zonesOnDuplicated[0].page_id).toBe(duplicatedPage.id)
  })

  it('reorders pages while preserving zone page_id identity', () => {
    const store = useWorkingPdfStore()
    store.set(createTemplateFixture({
      page_manifest: [
        { id: 'p1', type: 'source', source_page: 1 },
        { id: 'p2', type: 'source', source_page: 2 },
      ],
      zone_mappings: [
        {
          id: 'z2',
          page: 2,
          page_id: 'p2',
          x: 10,
          y: 10,
          width: 20,
          height: 10,
          field_id: 'submission_id',
          font_size: 12,
          font_color: '#000000',
        },
      ],
    }))

    store.reorderPages(2, 1)

    expect(store.pageManifest[0].id).toBe('p2')
    expect(store.zonesForPage(1)).toHaveLength(1)
    expect(store.zonesForPage(1)[0].page_id).toBe('p2')
    expect(store.zonesForPage(1)[0].page).toBe(1)
  })

  it('adds blank page in the middle and marks it as new page', () => {
    const store = useWorkingPdfStore()
    store.set(createTemplateFixture({ page_count: 2 }))

    store.addPageAfter(1)

    expect(store.pageList).toHaveLength(3)
    expect(store.isNewPage(2)).toBe(true)
    expect(store.getSourcePageNumber(2)).toBeNull()
  })
})

describe('working_pdf store - computed variables', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('exposes computed variables in field options and zone labels', () => {
    const store = useWorkingPdfStore()
    store.set(createTemplateFixture({
      page_manifest: [{ id: 'p1', type: 'source', source_page: 1 }],
      page_count: 1,
    }))
    store.setForm({
      properties: [
        { id: 'agree', name: 'Agree', type: 'checkbox' },
      ],
      computed_variables: [
        { id: 'cv_yes_no', name: 'Yes No', formula: 'IF({agree}, "yes", "no")' },
      ],
    })

    expect(store.computedVariables).toEqual([
      { id: 'cv_yes_no', name: 'Yes No', type: 'computed' },
    ])
    expect(store.fieldOptions).toContainEqual({
      name: 'Yes No (Variable)',
      value: 'cv_yes_no',
    })

    store.addZoneWithField({ id: 'cv_yes_no', name: 'Yes No' })

    const zone = store.content.zone_mappings[0]
    expect(zone.field_id).toBe('cv_yes_no')
    expect(store.getZoneLabel(zone)).toBe('Yes No')

    const savedTemplate = {
      ...createTemplateFixture(),
      ...store.getSaveData(),
    }

    store.reset()
    store.set(savedTemplate)
    store.setForm({
      properties: [],
      computed_variables: [
        { id: 'cv_yes_no', name: 'Yes No', formula: 'IF({agree}, "yes", "no")' },
      ],
    })

    expect(store.content.zone_mappings[0].field_id).toBe('cv_yes_no')
    expect(store.getZoneLabel(store.content.zone_mappings[0])).toBe('Yes No')
  })
})

describe('working_pdf store - obsolete field mappings', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('identifies only field zones that no longer map to an active field, variable, or special field', () => {
    const store = useWorkingPdfStore()
    store.set(createTemplateFixture({
      page_count: 2,
      zone_mappings: [
        { id: 'active-field', page: 1, field_id: 'email' },
        { id: 'computed-variable', page: 1, field_id: 'total' },
        { id: 'special-field', page: 1, field_id: 'submission_id' },
        { id: 'obsolete-field', page: 2, field_id: 'deleted_account_number', field_name: 'Account Number' },
        { id: 'blank-field', page: 2, field_id: '  ' },
        { id: 'static-text', page: 2, field_id: 'deleted_text', static_text: 'Text' },
        { id: 'static-image', page: 2, field_id: 'deleted_image', static_image: 'image.png' },
      ],
    }))
    store.setForm({
      properties: [{ id: 'email', name: 'Email', type: 'email' }],
      computed_variables: [{ id: 'total', name: 'Total', formula: '1 + 1' }],
    })

    expect(store.obsoleteFieldZones).toEqual([
      expect.objectContaining({ id: 'obsolete-field', field_id: 'deleted_account_number' }),
    ])
    expect(store.getObsoleteZoneLabel(store.obsoleteFieldZones[0])).toBe('Account Number')
    expect(store.getObsoleteZoneLabel({ field_id: 'deleted_email' })).toBe('deleted_email')
  })

  it('removes obsolete field zones and clears a removed selection without marking the template saved', () => {
    const store = useWorkingPdfStore()
    store.set(createTemplateFixture({
      page_count: 1,
      zone_mappings: [
        { id: 'obsolete', page: 1, field_id: 'deleted_field' },
        { id: 'active', page: 1, field_id: 'name' },
      ],
    }))
    store.setForm({
      properties: [{ id: 'name', name: 'Name', type: 'text' }],
    })
    store.setSelectedZone('obsolete')

    store.removeObsoleteFieldZones()

    expect(store.content.zone_mappings).toEqual([
      expect.objectContaining({ id: 'active', field_id: 'name' }),
    ])
    expect(store.selectedZoneId).toBeNull()
    expect(store.hasUnsavedChanges).toBe(true)
  })
})
