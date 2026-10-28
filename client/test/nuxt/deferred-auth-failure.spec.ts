import { beforeEach, it, expect, vi } from 'vitest'
import { getOpnRequestsOptions } from '~/composables/useOpnApi.js'

const state = vi.hoisted(() => ({ store: { token: 'old-session' }, expire: vi.fn() }))
vi.mock('~/composables/useAuthFlow', () => ({ useAuthFlow: () => ({ handleTokenExpiry: state.expire }) }))
vi.mock('~/lib/utils.js', () => ({ customDomainUsed: () => false, getHost: () => '', getDomain: () => '' }))
beforeEach(() => { state.store.token = 'old-session'; state.expire.mockReset(); vi.stubGlobal('useAuthStore', () => state.store) })

it('does not expire the new session when an older deferred request returns 401', async () => {
  const options = useNuxtApp().runWithContext(() => getOpnRequestsOptions('/open/user', {}))
  state.store.token = 'new-session'
  await options.onResponseError({ response: { status: 401 } })
  expect(state.expire).not.toHaveBeenCalled()
})
it('still handles expiration of the current session', async () => {
  const options = useNuxtApp().runWithContext(() => getOpnRequestsOptions('/open/user', {}))
  await options.onResponseError({ response: { status: 401 } })
  expect(state.expire).toHaveBeenCalledOnce()
})
