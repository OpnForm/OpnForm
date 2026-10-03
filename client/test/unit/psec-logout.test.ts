import { beforeEach, it, expect, vi } from 'vitest'
import { setActivePinia, createPinia } from 'pinia'
vi.mock('~/api', () => ({ authApi: { logout: vi.fn(() => Promise.reject(new Error('offline'))) } }))
import { useAuthStore } from '../../stores/auth'
beforeEach(() => setActivePinia(createPinia()))
it('clears both identities and all cookie names even if logout fails', () => {
  const auth = useAuthStore()
  const cookie = vi.spyOn(auth, 'setCookie').mockImplementation(() => {})
  auth.token = 'user'; auth.admin_token = 'admin'; auth.user = { id: 1 }
  auth.logout()
  expect(auth.token).toBeNull(); expect(auth.admin_token).toBeNull(); expect(auth.user).toBeNull()
  for (const name of ['opnform_token', 'token', 'opnform_admin_token', 'admin_token']) {
    expect(cookie).toHaveBeenCalledWith(name, null, { maxAge: 0 })
  }
})
