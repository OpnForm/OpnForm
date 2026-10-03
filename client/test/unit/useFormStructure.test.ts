import { describe, expect, it } from 'vitest'
import { reactive, ref } from 'vue'
import { useFormStructure } from '../../lib/forms/composables/useFormStructure.js'

function createField(id, type = 'text') {
  return { id, type, name: id }
}

function createStructure(properties, hiddenFieldIds = []) {
  const managerState = reactive({ currentPage: 0 })
  const hiddenFields = new Set(hiddenFieldIds)
  const fieldState = {
    getState: (field) => ({ hidden: hiddenFields.has(field.id) })
  }

  const structure = useFormStructure(
    ref({ properties }),
    managerState,
    ref({}),
    fieldState
  )

  return { managerState, structure }
}

describe('useFormStructure', () => {
  it('maps fields to rendered pages when a leading page break is ignored', () => {
    const properties = [
      createField('leading-break', 'nf-page-break'),
      createField('first-page-field'),
      createField('page-break', 'nf-page-break'),
      createField('last-page-field')
    ]
    const { managerState, structure } = createStructure(properties)

    expect(structure.pageCount.value).toBe(2)
    expect(structure.pageBoundaries.value).toEqual([
      { start: 1, end: 2 },
      { start: 3, end: 3 }
    ])
    expect(structure.getPageForField(1)).toBe(0)
    expect(structure.getPageForField(3)).toBe(1)

    managerState.currentPage = 1
    structure.setPageForField(1)
    expect(managerState.currentPage).toBe(0)
  })

  it('does not create phantom boundaries for consecutive page breaks', () => {
    const properties = [
      createField('first-page-field'),
      createField('first-break', 'nf-page-break'),
      createField('consecutive-break', 'nf-page-break'),
      createField('last-page-field')
    ]
    const { structure } = createStructure(properties)

    expect(structure.pageCount.value).toBe(2)
    expect(structure.pageBoundaries.value).toEqual([
      { start: 0, end: 1 },
      { start: 3, end: 3 }
    ])
    expect(structure.getPageForField(0)).toBe(0)
    expect(structure.getPageForField(2)).toBe(1)
    expect(structure.getPageForField(3)).toBe(1)
  })

  it('keeps hidden page breaks within the surrounding rendered page', () => {
    const properties = [
      createField('first-field'),
      createField('hidden-break', 'nf-page-break'),
      createField('second-field'),
      createField('visible-break', 'nf-page-break'),
      createField('last-field')
    ]
    const { structure } = createStructure(properties, ['hidden-break'])

    expect(structure.pageCount.value).toBe(2)
    expect(structure.pageBoundaries.value).toEqual([
      { start: 0, end: 3 },
      { start: 4, end: 4 }
    ])
    expect(structure.getPageForField(2)).toBe(0)
    expect(structure.getPageForField(4)).toBe(1)
  })

  it('inserts new fields on the rendered page after a leading page break', () => {
    const properties = [
      createField('leading-break', 'nf-page-break'),
      createField('first-page-field'),
      createField('page-break', 'nf-page-break'),
      createField('last-page-field')
    ]
    const { structure } = createStructure(properties)

    expect(structure.determineInsertIndex(null, 0, null, true)).toBe(2)
  })

  it('uses a single page for a form containing only page breaks', () => {
    const properties = [
      createField('first-break', 'nf-page-break'),
      createField('second-break', 'nf-page-break')
    ]
    const { structure } = createStructure(properties)

    expect(structure.pageCount.value).toBe(1)
    expect(structure.pageBoundaries.value).toEqual([{ start: 0, end: 1 }])
    expect(structure.getPageForField(0)).toBe(0)
    expect(structure.getPageForField(1)).toBe(0)
  })
})
