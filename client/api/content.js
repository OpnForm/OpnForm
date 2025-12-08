import { apiService } from './base'

export const contentApi = {
  // Fonts
  fonts: {
    list: (options) => apiService.get('/fonts', options)
  },

  // Feature flags
  featureFlags: {
    list: (options) => apiService.get('/content/feature-flags', options)
  },

  // Unsplash
  unsplash: {
    list: (options) => apiService.get('/unsplash', options),
    download: (downloadLocation) => apiService.post('/unsplash/download', { download_location: downloadLocation })
  }
}