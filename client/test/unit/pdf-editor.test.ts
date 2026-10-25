import { describe, expect, it } from 'vitest'
import { getPdfRenderPages, resolvePdfDownloadUrl } from '../../lib/pdf-editor'

describe('PDF editor helpers', () => {
  describe('resolvePdfDownloadUrl', () => {
    const endpoint = '/open/forms/4/pdf-templates/3/download'
    const origin = 'https://forms.example.com'

    it.each([
      ['/api', 'https://forms.example.com/api/open/forms/4/pdf-templates/3/download'],
      ['/api/', 'https://forms.example.com/api/open/forms/4/pdf-templates/3/download'],
      ['', 'https://forms.example.com/open/forms/4/pdf-templates/3/download'],
      ['https://api.opnform.com', 'https://api.opnform.com/open/forms/4/pdf-templates/3/download'],
    ])('resolves the %s API base', (baseUrl, expectedUrl) => {
      expect(resolvePdfDownloadUrl(endpoint, baseUrl, origin)).toBe(expectedUrl)
    })
  })

  describe('getPdfRenderPages', () => {
    const blankPages = new Set([2, 3, 4])
    const isNewPage = (pageNum: number) => blankPages.has(pageNum)

    it('keeps physical and visible render pages distinct', () => {
      expect(getPdfRenderPages([1, 2, 3, 4, 5], 3, isNewPage, true)).toEqual({
        physicalPages: [1, 5],
        targetPages: [],
      })
    })

    it('selects adjacent physical pages during a visible-only render', () => {
      expect(getPdfRenderPages([1, 2, 3, 4, 5], 2, isNewPage, true)).toEqual({
        physicalPages: [1, 5],
        targetPages: [1],
      })
    })

    it('returns no physical pages for an all-blank template', () => {
      expect(getPdfRenderPages([2, 3, 4], 3, isNewPage)).toEqual({
        physicalPages: [],
        targetPages: [],
      })
    })
  })
})
