import { afterEach, describe, expect, it, vi } from 'vitest'
import { getSelfHostedPublicRedirect, httpUrl, selfHostedMarketingPaths } from '../../lib/self-hosted-public.js'

describe('self-hosted public routes', () => {
  it.each([...selfHostedMarketingPaths, '/integrations', '/integrations/slack', '/opnform-vs-typeform'])('sends %s to the matching cloud page', (path) => {
    const result = new URL(getSelfHostedPublicRedirect(path)!)
    expect(result.origin).toBe('https://opnform.com')
    expect(result.pathname).toBe(path)
    expect(result.searchParams.get('utm_source')).toBe('self_hosted')
  })

  it.each(['/', '/login', '/password/email', '/register', '/forms/contact', '/forms/create', '/forms/contact/edit', '/home', '/admin', '/templates', '/templates/my-templates', '/templates/contact', '/mcp/authorize', '/api/feature-flags', '/oauth/google/callback', '/enterprise-settings'])('keeps %s on the instance', (path) => {
    expect(getSelfHostedPublicRedirect(path)).toBeNull()
  })

  it('handles trailing slashes', () => {
    expect(getSelfHostedPublicRedirect('/enterprise/')).toBe(getSelfHostedPublicRedirect('/enterprise'))
  })

  it('respects the existing root redirect for marketing pages', () => {
    expect(getSelfHostedPublicRedirect('/integrations/slack', { rootRedirectUrl: 'https://example.com/forms' })).toBe('https://example.com/forms')
  })

  it('uses organization legal links without falling back to cloud legal text', () => {
    expect(getSelfHostedPublicRedirect('/privacy-policy', { privacyPolicyUrl: 'https://example.com/privacy' })).toBe('https://example.com/privacy')
    expect(getSelfHostedPublicRedirect('/terms-conditions', { termsUrl: 'https://example.com/terms' })).toBe('https://example.com/terms')
    expect(getSelfHostedPublicRedirect('/privacy-policy')).toBe('/')
    expect(getSelfHostedPublicRedirect('/terms-conditions')).toBe('/')
  })

  it.each(['javascript:alert(1)', 'data:text/html,hello', '//example.com', '/relative', 'invalid'])('rejects invalid external URL %s', (url) => {
    expect(httpUrl(url)).toBeNull()
  })
})

describe('self-hosted public middleware', () => {
  afterEach(() => vi.unstubAllGlobals())

  function prepare({ selfHosted = true, setupRequired = false } = {}) {
    vi.stubGlobal('defineNuxtRouteMiddleware', (handler: unknown) => handler)
    vi.stubGlobal('useFeatureFlag', (key: string) => key === 'self_hosted' ? selfHosted : setupRequired)
    vi.stubGlobal('useRuntimeConfig', () => ({ public: { selfHosted: {} } }))
    const navigate = vi.fn()
    vi.stubGlobal('navigateTo', navigate)
    return navigate
  }

  it('does not redirect cloud requests', async () => {
    const navigate = prepare({ selfHosted: false })
    const { default: middleware } = await import('../../middleware/02.self-hosted-public.global.js')
    middleware({ path: '/enterprise' })
    expect(navigate).not.toHaveBeenCalled()
  })

  it('lets the setup middleware handle a new installation', async () => {
    const navigate = prepare({ setupRequired: true })
    const { default: middleware } = await import('../../middleware/02.self-hosted-public.global.js')
    middleware({ path: '/enterprise' })
    expect(navigate).not.toHaveBeenCalled()
  })

  it('redirects marketing without forwarding private query parameters', async () => {
    const navigate = prepare()
    const { default: middleware } = await import('../../middleware/02.self-hosted-public.global.js')
    middleware({ path: '/enterprise', fullPath: '/enterprise?email=private@example.test&token=secret' })
    expect(navigate).toHaveBeenCalledWith('https://opnform.com/enterprise?utm_source=self_hosted&utm_medium=instance&utm_campaign=public_pages', { external: true, redirectCode: 302 })
  })

  it('leaves invitations and MCP authorization local', async () => {
    const navigate = prepare()
    const { default: middleware } = await import('../../middleware/02.self-hosted-public.global.js')
    middleware({ path: '/register', fullPath: '/register?email=invite@example.test&invite_token=secret' })
    middleware({ path: '/mcp/authorize' })
    expect(navigate).not.toHaveBeenCalled()
  })
})

describe('self-hosted invitation access', () => {
  afterEach(() => vi.unstubAllGlobals())

  it('reads invitation parameters from the destination on a direct visit', async () => {
    vi.stubGlobal('defineNuxtRouteMiddleware', (handler: unknown) => handler)
    vi.stubGlobal('useFeatureFlag', () => true)
    const navigate = vi.fn()
    vi.stubGlobal('navigateTo', navigate)
    const { default: middleware } = await import('../../middleware/self-hosted.js')
    middleware({ name: 'register', query: { email: 'invite@example.test', invite_token: 'local-test' } })
    expect(navigate).not.toHaveBeenCalled()
    middleware({ name: 'register', query: {} })
    expect(navigate).toHaveBeenCalledWith({ name: 'index' })
  })
})

describe('self-hosted welcome navigation', () => {
  afterEach(() => vi.unstubAllGlobals())

  it.each([
    [true, true, false, true],
    [true, true, true, false],
    [true, false, false, false],
    [false, true, false, false],
  ])('handles selfHosted=%s, authenticated=%s, customRedirect=%s', async (selfHosted, authenticated, customRedirect, goesHome) => {
    vi.stubGlobal('defineNuxtRouteMiddleware', (handler: unknown) => handler)
    vi.stubGlobal('useFeatureFlag', () => selfHosted)
    vi.stubGlobal('useIsAuthenticated', () => ({ isAuthenticated: { value: authenticated } }))
    const performRedirect = vi.fn()
    vi.stubGlobal('useSubdomainRedirect', () => ({ performRedirect, shouldRedirect: () => customRedirect }))
    const navigate = vi.fn()
    vi.stubGlobal('navigateTo', navigate)
    const { default: middleware } = await import('../../middleware/root-redirect.js')
    await middleware({ path: '/' })
    if (goesHome) {
      expect(navigate).toHaveBeenCalledWith({ name: 'home' })
      expect(performRedirect).not.toHaveBeenCalled()
    } else {
      expect(navigate).not.toHaveBeenCalled()
      expect(performRedirect).toHaveBeenCalledWith({ skipIfIframe: false })
    }
  })
})
