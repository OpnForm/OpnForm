import { describe, expect, it } from 'vitest'
import { normalizePublicPageAssetUrl } from '../../lib/forms/public-page-meta.js'

describe('normalizePublicPageAssetUrl', () => {
  it('upgrades same-host http assets when the page is https', () => {
    expect(
      normalizePublicPageAssetUrl(
        'http://forms.example.com/forms/assets/favicon.png',
        'https://forms.example.com/forms/demo'
      )
    ).toBe('https://forms.example.com/forms/assets/favicon.png')
  })

  it('keeps same-host http assets on http pages', () => {
    expect(
      normalizePublicPageAssetUrl(
        'http://localhost/forms/assets/favicon.png',
        'http://localhost/forms/demo'
      )
    ).toBe('http://localhost/forms/assets/favicon.png')
  })

  it('does not rewrite external http assets', () => {
    expect(
      normalizePublicPageAssetUrl(
        'http://cdn.example.com/favicon.png',
        'https://forms.example.com/forms/demo'
      )
    ).toBe('http://cdn.example.com/favicon.png')
  })

  it('leaves relative asset paths unchanged', () => {
    expect(
      normalizePublicPageAssetUrl(
        '/forms/assets/favicon.png',
        'https://forms.example.com/forms/demo'
      )
    ).toBe('/forms/assets/favicon.png')
  })
})
