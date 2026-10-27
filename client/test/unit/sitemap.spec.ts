import { afterEach, describe, expect, it, vi } from 'vitest'

afterEach(() => {
  vi.unstubAllEnvs()
  vi.unstubAllGlobals()
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

describe('self-hosted sitemap', () => {
  it('excludes redirected marketing pages and keeps the local template catalog', async () => {
    vi.stubEnv('SELF_HOSTED', 'true')
    vi.resetModules()
    const { default: sitemap } = await import('../../sitemap.js')
    const { createFilter } = await import('nuxtseo-shared/utils')
    const include = createFilter({ exclude: sitemap.exclude })

    for (const path of ['/', '/enterprise', '/pricing', '/industry', '/mcp', '/integrations', '/integrations/slack', '/opnform-vs-typeform', '/privacy-policy', '/terms-conditions']) {
      expect(include(path), path).toBe(false)
    }
    expect(include('/templates')).toBe(true)
    expect(include('/templates/contact')).toBe(true)
    const urls = await sitemap.urls()
    expect(urls.some(({ url }) => url.startsWith('/integrations'))).toBe(false)
    expect(urls.some(({ url }) => url.startsWith('/templates'))).toBe(true)
  })

  it.each(['https://api.opnform.com', '/api'])('keeps cloud marketing pages with API base %s', async (apiBase) => {
    vi.stubEnv('SELF_HOSTED', 'false')
    vi.stubEnv('NUXT_PUBLIC_SELF_HOSTED', 'false')
    vi.stubEnv('NUXT_PUBLIC_API_BASE', apiBase)
    vi.resetModules()
    const { default: sitemap } = await import('../../sitemap.js')
    const { createFilter } = await import('nuxtseo-shared/utils')
    const include = createFilter({ exclude: sitemap.exclude })
    expect(include('/')).toBe(true)
    expect(include('/enterprise')).toBe(true)
    expect(include('/opnform-vs-typeform')).toBe(true)
    expect((await sitemap.urls()).some(({ url }) => url.startsWith('/integrations'))).toBe(true)
  })
})


describe('runtime sitemap policy for published images', () => {
  async function prepare(flags: unknown) {
    vi.stubGlobal('defineNitroPlugin', (plugin: unknown) => plugin)
    const fetch = vi.fn().mockResolvedValue(flags)
    vi.stubGlobal('$fetch', fetch)
    const hook = vi.fn()
    const { default: register } = await import('../../server/plugins/self-hosted-sitemap.js')
    register({ hooks: { hook } })
    expect(hook.mock.calls[0][0]).toBe('sitemap:resolved')
    return { resolve: hook.mock.calls[0][1], fetch }
  }

  const paths = ['/', '/enterprise', '/pricing', '/integrations/slack', '/opnform-vs-typeform', '/privacy-policy', '/report-abuse', '/templates', '/templates/contact', '/forms/contact']

  it('filters the rendered sitemap using the actual installation mode', async () => {
    const { resolve, fetch } = await prepare({ self_hosted: true })
    const context = { urls: paths.map(path => ({ loc: `https://forms.example.test${path}` })) }
    await resolve(context)
    expect(context.urls.map(url => new URL(url.loc).pathname)).toEqual(['/templates', '/templates/contact', '/forms/contact'])
    expect(fetch).toHaveBeenCalledWith('/api/feature-flags', { retry: 0 })
  })

  it('keeps marketing URLs for a cloud deployment', async () => {
    const { resolve } = await prepare({ self_hosted: false })
    const urls = paths.map(loc => ({ loc }))
    const context = { urls }
    await resolve(context)
    expect(context.urls).toBe(urls)
  })

  it('preserves the build-time fallback if the flags endpoint is unavailable', async () => {
    const { resolve, fetch } = await prepare(null)
    fetch.mockRejectedValue(new Error('API unavailable'))
    const urls = [{ loc: '/templates' }]
    const context = { urls }
    await resolve(context)
    expect(context.urls).toBe(urls)
  })
})
