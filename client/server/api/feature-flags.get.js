// Cached server API route for feature flags
export default defineCachedEventHandler(async () => {
  const config = useRuntimeConfig()
  
  // Call Laravel API directly with proper headers
  const apiBase = config.privateApiBase || config.public.apiBase
  
  try {
    const response = await $fetch('/content/feature-flags', {
      baseURL: apiBase,
      headers: {
        'accept': 'application/json',
        ...(config.apiSecret && { 'x-api-secret': config.apiSecret })
      }
    })
    
    return response
  } catch (error) {
    console.error('Failed to fetch feature flags from Laravel API:', error)
    // Return empty object as fallback to prevent breaking the app
    return {}
  }
}, {
  maxAge: 10 * 60, // 10 minutes cache
  name: 'feature-flags',
  getKey: (event) => {
    // Include timestamp in cache key for cache-busting when refreshing
    const url = new URL(event.node.req.url || '', 'http://localhost')
    const timestamp = url.searchParams.get('t')
    return timestamp ? `global:${timestamp}` : 'global'
  },
  swr: true // Serve stale while revalidating in background
})