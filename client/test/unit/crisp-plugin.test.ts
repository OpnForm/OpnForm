import { afterEach, beforeEach, expect, it, vi } from 'vitest'
import { effectScope, nextTick, ref, watch } from 'vue'

const sdk = vi.hoisted(() => ({
  ready: Promise.resolve(),
  configure: vi.fn(),
  chat: {
    onChatOpened: vi.fn(),
    onChatClosed: vi.fn(),
    hide: vi.fn(),
    show: vi.fn(),
  },
}))

vi.mock('crisp-sdk-web', async () => {
  await sdk.ready
  return { Crisp: sdk }
})

const fullscreen = ref(false)
const route = ref({ name: 'forms-slug-edit' })
const appStore = { crisp: { hidden: false, chatOpened: false } }
let scope: ReturnType<typeof effectScope>

beforeEach(() => {
  vi.resetModules()
  vi.clearAllMocks()
  sdk.ready = Promise.resolve()
  fullscreen.value = false
  route.value = { name: 'forms-slug-edit' }
  appStore.crisp.hidden = false
  scope = effectScope()
  vi.stubGlobal('defineNuxtPlugin', plugin => plugin)
  vi.stubGlobal('useIsIframe', () => false)
  vi.stubGlobal('useRouter', () => ({ currentRoute: route }))
  vi.stubGlobal('useRuntimeConfig', () => ({ public: { crispWebsiteId: 'test' } }))
  vi.stubGlobal('useState', () => fullscreen)
  vi.stubGlobal('useAppStore', () => appStore)
  vi.stubGlobal('window', {})
  vi.stubGlobal('watch', (...args: Parameters<typeof watch>) => scope.run(() => watch(...args)))
})

afterEach(() => {
  scope.stop()
  vi.unstubAllGlobals()
})

async function startPlugin() {
  const { default: plugin } = await import('../../plugins/crisp.client.js')
  const provide = vi.fn()
  plugin({ provide })
  return { ready: provide.mock.calls[0][1]() }
}

it('initializes hidden if the preview goes fullscreen before the SDK loads', async () => {
  let releaseSdk!: () => void
  sdk.ready = new Promise<void>(resolve => { releaseSdk = resolve })

  const { ready } = await startPlugin()
  fullscreen.value = true
  releaseSdk()
  await ready

  expect(sdk.chat.hide).toHaveBeenCalledOnce()
  expect(sdk.chat.show).not.toHaveBeenCalled()
  expect(appStore.crisp.hidden).toBe(true)

  fullscreen.value = false
  await nextTick()
  expect(sdk.chat.show).toHaveBeenCalledOnce()
  expect(appStore.crisp.hidden).toBe(false)
})

it('keeps Crisp hidden when fullscreen is cleared on the public form route', async () => {
  const { ready } = await startPlugin()
  await ready
  expect(sdk.chat.show).toHaveBeenCalledOnce()

  fullscreen.value = true
  await nextTick()
  expect(sdk.chat.hide).toHaveBeenCalledOnce()

  route.value = { name: 'forms-slug' }
  fullscreen.value = false
  await nextTick()
  expect(sdk.chat.show).toHaveBeenCalledOnce()
  expect(appStore.crisp.hidden).toBe(true)
})
