import { apiService } from './base'

const BASE_PATH = '/open/workspaces'

export const oidcApi = {
  // Workspace-scoped OIDC connections
  list: (workspaceId, options) => apiService.get(`${BASE_PATH}/${workspaceId}/oidc-connections`, options),
  get: (workspaceId, connectionId) => apiService.get(`${BASE_PATH}/${workspaceId}/oidc-connections/${connectionId}`),
  create: (workspaceId, data) => apiService.post(`${BASE_PATH}/${workspaceId}/oidc-connections`, data),
  update: (workspaceId, connectionId, data) => apiService.patch(`${BASE_PATH}/${workspaceId}/oidc-connections/${connectionId}`, data),
  delete: (workspaceId, connectionId) => apiService.delete(`${BASE_PATH}/${workspaceId}/oidc-connections/${connectionId}`),

  // Global OIDC connections
  listGlobal: (options) => apiService.get('/oidc-connections', options),
  getGlobal: (connectionId) => apiService.get(`/oidc-connections/${connectionId}`),
  createGlobal: (data) => apiService.post('/oidc-connections', data),
  updateGlobal: (connectionId, data) => apiService.patch(`/oidc-connections/${connectionId}`, data),
  deleteGlobal: (connectionId) => apiService.delete(`/oidc-connections/${connectionId}`),

  // Email-based OIDC lookup (for login flow)
  getOptionsForEmail: (email) => apiService.post('/auth/oidc/options', { email }),
  
  // OIDC callback - processes authorization code and returns token/user
  callback: (slug, queryParams) => {
    // Build query string from params
    const queryString = new URLSearchParams(queryParams).toString()
    const url = `/auth/${slug}/callback${queryString ? '?' + queryString : ''}`
    // Use GET with Accept: application/json header to get JSON response
    return apiService.get(url, {
      headers: {
        'Accept': 'application/json'
      }
    })
  },

  link: (linkToken) => apiService.post('/auth/oidc/link', { link_token: linkToken }),
}

