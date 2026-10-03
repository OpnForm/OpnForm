import { apiService } from './base'

const BASE_PATH = '/settings/mcp'

export const mcpApi = {
  status: () => apiService.get(BASE_PATH),
  update: (enabled) => apiService.put(BASE_PATH, { enabled }),
}
