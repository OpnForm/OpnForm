import { apiService } from './base'

const BASE_PATH = '/settings/license'

export const licenseApi = {
  status: () => apiService.get(`${BASE_PATH}/status`),
  activate: (licenseKey) => apiService.post(`${BASE_PATH}/activate`, { license_key: licenseKey }),
  deactivate: () => apiService.post(`${BASE_PATH}/deactivate`),
  portal: () => apiService.post(`${BASE_PATH}/portal`),
}
