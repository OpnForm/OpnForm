import { afterEach, describe, expect, it, vi } from 'vitest'
import { applyEnv } from 'nitropack/runtime/internal/utils.env'
import { useSelfHostedPresentation } from '../../composables/useSelfHostedPresentation.js'

afterEach(() => {
  vi.unstubAllEnvs()
  vi.unstubAllGlobals()
  vi.resetModules()
})

describe('self-hosted presentation runtime configuration', () => {
  it.each(['true', 'false'])('retains deployment overrides with NUXT_PUBLIC_SELF_HOSTED=%s', async (mode) => {
    // Model a prebuilt Docker image receiving its configuration at startup.
    const { default: defaults } = await import('../../runtimeConfig.js')
    const config = structuredClone(defaults)
    vi.stubEnv('NUXT_PUBLIC_SELF_HOSTED', mode)
    vi.stubEnv('NUXT_PUBLIC_SELF_HOSTED_NAME', 'Acme forms')
    vi.stubEnv('NUXT_PUBLIC_SELF_HOSTED_LOGO_URL', 'https://example.test/logo.svg')
    vi.stubEnv('NUXT_PUBLIC_SELF_HOSTED_PRIVACY_POLICY_URL', 'https://example.test/privacy')
    vi.stubEnv('NUXT_PUBLIC_SELF_HOSTED_TERMS_URL', 'https://example.test/terms')
    applyEnv(config, { prefix: 'NUXT_', altPrefix: 'NITRO_' })
    vi.stubGlobal('useRuntimeConfig', () => config)

    expect(useSelfHostedPresentation()).toEqual({
      name: 'Acme forms',
      logoUrl: 'https://example.test/logo.svg',
      privacyPolicyUrl: 'https://example.test/privacy',
      termsUrl: 'https://example.test/terms',
    })

    vi.stubGlobal('defineNuxtRouteMiddleware', (handler: unknown) => handler)
    vi.stubGlobal('useFeatureFlag', (key: string) => key === 'self_hosted')
    const navigate = vi.fn()
    vi.stubGlobal('navigateTo', navigate)
    const { default: middleware } = await import('../../middleware/02.self-hosted-public.global.js')
    middleware({ path: '/privacy-policy' })
    expect(navigate).toHaveBeenLastCalledWith('https://example.test/privacy', { external: true, redirectCode: 302 })
    middleware({ path: '/terms-conditions' })
    expect(navigate).toHaveBeenLastCalledWith('https://example.test/terms', { external: true, redirectCode: 302 })
  })
})
