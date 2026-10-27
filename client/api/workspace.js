import { apiService } from './base'

const BASE_PATH = '/open/workspaces'

export const workspaceApi = {
  // Workspace operations
  list: (options) => apiService.get(BASE_PATH, options),
  get: (workspaceId) => apiService.get(`${BASE_PATH}/${workspaceId}`),
  create: (data) => apiService.post(`${BASE_PATH}/create`, data),
  update: (workspaceId, data) => apiService.put(`${BASE_PATH}/${workspaceId}`, data),
  delete: (workspaceId) => apiService.delete(`${BASE_PATH}/${workspaceId}`),
  leave: (workspaceId) => apiService.post(`${BASE_PATH}/${workspaceId}/leave`),

  // User management
  users: {
    list: (workspaceId, options) => apiService.get(`${BASE_PATH}/${workspaceId}/users`, options),
    add: (workspaceId, data) => apiService.post(`${BASE_PATH}/${workspaceId}/users/add`, data),
    remove: (workspaceId, userId) => apiService.delete(`${BASE_PATH}/${workspaceId}/users/${userId}/remove`),
    updateRole: (workspaceId, userId, data) => apiService.put(`${BASE_PATH}/${workspaceId}/users/${userId}/update-role`, data)
  },

  // Invite management
  invites: {
    list: (workspaceId, options) => apiService.get(`${BASE_PATH}/${workspaceId}/invites`, options),
    resend: (workspaceId, inviteId) => apiService.post(`${BASE_PATH}/${workspaceId}/invites/${inviteId}/resend`),
    cancel: (workspaceId, inviteId) => apiService.delete(`${BASE_PATH}/${workspaceId}/invites/${inviteId}/cancel`)
  },

  // Custom domains
  customDomains: {
    verification: (workspaceId, domain) => apiService.get(`${BASE_PATH}/${workspaceId}/custom-domains/verification`, { query: { domain } }),
    update: (workspaceId, data) => apiService.put(`${BASE_PATH}/${workspaceId}/custom-domains`, data)
  }
}
