import { contentApi } from '~/api/content'

export default defineNuxtPlugin(async (nuxtApp) => {
  // Reuse the Nitro cache across SSR requests, without forwarding user headers.
  const featureFlagsState = useState('featureFlags', () => ({}))
  
  try {
    const flags = await $fetch('/api/feature-flags', { retry: 0 })
    featureFlagsState.value = flags
  } catch (error) {
    console.error('Failed to load feature flags on server:', error)
    // Keep empty object as fallback
  }

  // Provide simple refresh capability
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