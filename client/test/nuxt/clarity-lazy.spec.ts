import { it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import Clarity from '~/components/vendor/Clarity.vue'

const state = vi.hoisted(() => {
  let resolve
  const promise = new Promise(done => { resolve = done })
  return { promise, resolve, requested: false, init: vi.fn() }
})
vi.mock('@microsoft/clarity', async () => {
  state.requested = true
  await state.promise
  return { default: { init: state.init, identify: vi.fn() } }
})

it('does not initialize an SDK that finishes loading after leaving the page', async () => {
  useRuntimeConfig().public.clarityProjectId = 'local-test'
  vi.stubGlobal('useAuthStore', () => ({ token: 'local-session', user: { id: 1 } }))
  const wrapper = mount(Clarity)
  await vi.waitFor(() => expect(state.requested).toBe(true))
  wrapper.unmount()
  state.resolve()
  await flushPromises()
  expect(state.init).not.toHaveBeenCalled()
})
