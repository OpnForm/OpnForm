import { apiService } from './base'

const BASE_PATH = '/subscription'

export const billingApi = {
  // Subscription operations
  getUsersCount: (options) => apiService.get(`${BASE_PATH}/users-count`, options),
  getSubscription: (options) => apiService.get(BASE_PATH, options),
  cancelSubscription: (subscriptionId) => apiService.post(`${BASE_PATH}/${subscriptionId}/cancel`),
  updateCustomerDetails: (data) => apiService.put(`${BASE_PATH}/update-customer-details`, data),
  getCheckoutUrl: (subscription, plan, trial, options) => {
    const trialParam = trial ? `/${trial}` : ''
    return apiService.get(`${BASE_PATH}/new/${subscription}/${plan}/checkout${trialParam}`, options)
  },
  getBillingPortal: (options) => apiService.get(`${BASE_PATH}/billing-portal`, options),
  changePlan: (data) => apiService.post(`${BASE_PATH}/change-plan`, data)
}