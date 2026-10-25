import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  vi.unstubAllEnvs()
  vi.resetModules()
})

describe('sitemap API source', () => {
  it.each([
    ['https://api.opnform.com', 'https://api.opnform.com/sitemap-urls'],
    ['https://api.opnform.com/', 'https://api.opnform.com/sitemap-urls'],
    ['https://forms.example.com/api', 'https://forms.example.com/api/sitemap-urls'],
    ['https://forms.example.com/api/', 'https://forms.example.com/api/sitemap-urls'],
    ['/api', '/api/sitemap-urls'],
    ['/api/', '/api/sitemap-urls'],
    ['', '/sitemap-urls'],
    [undefined, '/sitemap-urls'],
  ])('resolves the template source with API base %s', (base, expected) => {
    vi.stubEnv('NUXT_PUBLIC_API_BASE', base)
    vi.resetModules()

    return import('../../sitemap.js').then(({ default: sitemap }) => {
      expect(sitemap.sources).toEqual([expected])
    })
  })
})
