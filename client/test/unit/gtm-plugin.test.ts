import { beforeEach, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({ createGtm: vi.fn(), enable: vi.fn() }))
vi.mock('@gtm-support/vue-gtm', () => ({
  createGtm: mocks.createGtm,
  useGtm: () => ({ enable: mocks.enable }),
}))

beforeEach(() => {
  vi.resetModules()
  vi.clearAllMocks()
  vi.stubGlobal('defineNuxtPlugin', plugin => plugin)
  vi.stubGlobal('useRuntimeConfig', () => ({ public: { gtm: { id: 'GTM-TEST', enabled: false } } }))
  vi.stubGlobal('useRoute', () => ({ name: 'home' }))
  vi.stubGlobal('useRouter', () => ({}))
  vi.stubGlobal('useIsIframe', () => false)
  vi.stubGlobal('window', { dataLayer: [] })
})

it('keeps GTM disabled during installation and enables it on application pages', async () => {
  const { default: plugin } = await import('../../plugins/gtm.client.js')
  plugin({ vueApp: { use: vi.fn() } })
  expect(mocks.createGtm).toHaveBeenCalledWith({ id: 'GTM-TEST', enabled: false, vueRouter: {} })
  expect(mocks.enable).toHaveBeenCalledWith(true)
})

it.each(['form', 'iframe', 'missing id'])('does not enable site tracking for %s', async (scenario) => {
  if (scenario === 'form') vi.stubGlobal('useRoute', () => ({ name: 'forms-slug' }))
  if (scenario === 'iframe') vi.stubGlobal('useIsIframe', () => true)
  if (scenario === 'missing id') vi.stubGlobal('useRuntimeConfig', () => ({ public: { gtm: { id: 'GTM-XXXXXX', enabled: false } } }))
  const { default: plugin } = await import('../../plugins/gtm.client.js')
  plugin({ vueApp: { use: vi.fn() } })
  expect(mocks.enable).not.toHaveBeenCalled()
})
