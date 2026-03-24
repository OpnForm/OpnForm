import { apiService } from './base'

const OAUTH_BASE_PATH = '/oauth'

export const oauthApi = {
  // Provider operations
  list: (options) => apiService.get('/open/providers', options),
  connect: (service, data) => apiService.post(`${OAUTH_BASE_PATH}/connect/${service}`, data),
  callback: (service, data) => apiService.post(`${OAUTH_BASE_PATH}/${service}/callback`, data),
  widgetCallback: (service, data) => apiService.post(`${OAUTH_BASE_PATH}/widget-callback/${service}`, data),
  delete: (providerId) => apiService.delete(`/settings/providers/${providerId}`),

  // OAuth flow (for authentication)
  redirect: (provider, data) => apiService.post(`${OAUTH_BASE_PATH}/connect/${provider}`, { ...data, intent: 'auth' })
}