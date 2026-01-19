import { apiService } from './base'

const BASE_PATH = '/moderator'

export const adminApi = {
  // User management
  fetchUser: (identifier, options) => apiService.get(`${BASE_PATH}/fetch-user/${encodeURI(identifier)}`, options),
  impersonate: (userId, options) => apiService.get(`${BASE_PATH}/impersonate/${userId}`, options),
  blockUser: (data) => apiService.post(`${BASE_PATH}/block-user`, data),
  unblockUser: (data) => apiService.post(`${BASE_PATH}/unblock-user`, data),

  // Template management
  createTemplate: (data) => apiService.post(`${BASE_PATH}/create-template`, data),

  // Subscription management
  applyDiscount: (data) => apiService.patch(`${BASE_PATH}/apply-discount`, data),
  extendTrial: (data) => apiService.patch(`${BASE_PATH}/extend-trial`, data),
  cancelSubscription: (data) => apiService.patch(`${BASE_PATH}/cancellation-subscription`, data),
  sendPasswordResetEmail: (data) => apiService.patch(`${BASE_PATH}/send-password-reset-email`, data),

  // Billing management
  billing: {
    getCustomer: (userId, options) => apiService.get(`${BASE_PATH}/billing/${userId}/customer`, options),
    updateCustomer: (data) => apiService.patch(`${BASE_PATH}/billing/customer`, data),
    getSubscriptions: (userId, options) => apiService.get(`${BASE_PATH}/billing/${userId}/subscriptions`, options),
    getPayments: (userId, options) => apiService.get(`${BASE_PATH}/billing/${userId}/payments`, options)
  },

  // Form management
  forms: {
    getDeleted: (userId, options) => apiService.get(`${BASE_PATH}/forms/${userId}/deleted-forms`, options),
    restore: (slug) => apiService.patch(`${BASE_PATH}/forms/${slug}/restore`)
  }
}