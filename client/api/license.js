import { apiService } from './base'

const BASE_PATH = '/settings/license'

export const licenseApi = {
  activate: (licenseKey) => apiService.post(`${BASE_PATH}/activate`, { license_key: licenseKey })
}
