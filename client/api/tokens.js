import { apiService } from './base'

const BASE_PATH = '/settings/tokens'

export const tokensApi = {
  // Token operations
  abilities: () => apiService.get(`${BASE_PATH}/abilities`),
  list: (options) => apiService.get(BASE_PATH, options),
  create: (data) => apiService.post(BASE_PATH, data),
  delete: (tokenId) => apiService.delete(`${BASE_PATH}/${tokenId}`)
}