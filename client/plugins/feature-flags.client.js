import { contentApi } from '~/api/content'

export default defineNuxtPlugin((nuxtApp) => {
  // Access the same state that was set on server
  const featureFlagsState = useState('featureFlags', () => ({}))
    
  // Provide refresh capability
  nuxtApp.provide('refreshFeatureFlags', async () => {
    try {
      // Force fresh fetch by adding cache-busting timestamp
      const flags = await contentApi.featureFlags.list({
        query: { t: Date.now() }
      })
      featureFlagsState.value = flags
      return flags
    } catch (error) {
      console.error('Failed to refresh feature flags:', error)
      throw error
    }
  })
}) 