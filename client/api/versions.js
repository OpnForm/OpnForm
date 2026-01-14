import { apiService as api } from './base'

export const versionsApi = {
  // Version operations
  list: (modelType, id, params = {}) => api.get(`/versions/${modelType}/${id}`, { params }),
  restore: (versionId) => api.post(`/versions/${versionId}/restore`),
}
