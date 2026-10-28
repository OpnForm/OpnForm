import { it, expect, vi } from 'vitest'
import { getOpnRequestsOptions } from '~/composables/useOpnApi.js'

const state = vi.hoisted(() => {
  let resolve
  const ready = new Promise(done => { resolve = done })
  return { ready, resolve, loading: false, store: { token: 'old-session' }, expire: vi.fn() }
})
vi.mock('~/composables/useAuthFlow', async () => {
  state.loading = true
  await state.ready
  return { useAuthFlow: () => ({ handleTokenExpiry: state.expire }) }
})
vi.mock('~/lib/utils.js', () => ({ customDomainUsed: () => false, getHost: () => '', getDomain: () => '' }))

it('keeps a fresh session when login finishes during the auth module download', async () => {
  vi.stubGlobal('useAuthStore', () => state.store)
  const options = useNuxtApp().runWithContext(() => getOpnRequestsOptions('/open/user', {}))
  const pending = options.onResponseError({ response: { status: 401 } })
  await vi.waitFor(() => expect(state.loading).toBe(true))
  state.store.token = 'new-session'
  state.resolve()
  await pending
  expect(state.expire).not.toHaveBeenCalled()
})
