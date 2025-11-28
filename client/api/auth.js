import { apiService } from './base'


export const authApi = {
  // User operations
  user: {
    get: (options) => apiService.get('/user', options),
    delete: () => apiService.delete('/user'),

    updateProfile: (data) => apiService.patch('/settings/profile', data)
  },

  // Authentication
  login: (data) => apiService.post('/login', data),
  register: (data) => apiService.post('/register', data),
  logout: () => apiService.post('/logout'),

  // OAuth
  oauth: {
    callback: (provider, data) => apiService.post(`/oauth/${provider}/callback`, data)
  },

  // Two-factor authentication
  twoFactor: {
    enable: () => apiService.post('/settings/two-factor/enable'),
    confirm: (data) => apiService.post('/settings/two-factor/confirm', data),
    disable: (data) => apiService.post('/settings/two-factor/disable', data),
    recoveryCodes: (data) => apiService.post('/settings/two-factor/recovery-codes', data),
    regenerateRecoveryCodes: (data) => apiService.post('/settings/two-factor/recovery-codes/regenerate', data),
    verify: (data) => apiService.post('/auth/two-factor/verify', data),
  }
}