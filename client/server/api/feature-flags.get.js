// Global installation configuration: never forward cookies, tokens or custom domains.
const getFeatureFlags = defineCachedFunction(() => {
  const config = useRuntimeConfig()

  return $fetch('/content/feature-flags', {
    baseURL: config.privateApiBase || config.public.apiBase,
    retry: 0,
    headers: {
      accept: 'application/json',
      ...(config.apiSecret && { 'x-api-secret': config.apiSecret })
    }
  }).then((flags) => {
    if (!flags || typeof flags.self_hosted !== 'boolean') {
      throw createError({ statusCode: 502, statusMessage: 'Invalid feature flags response' })
    }
    return flags
  })
}, {
  maxAge: 10 * 60,
  name: 'feature-flags',
  // Arbitrary public query parameters must not create a new upstream fetch.
  getKey: () => 'global',
  // Registration clears Laravel's cache, but cannot invalidate this Nitro cache.
  // Keep checking the API until setup finishes, then cache normally.
  validate: (entry) => entry.value !== undefined && entry.value.setup_required !== true,
  swr: true
})

export default defineEventHandler((event) => {
  // Browsers and reverse proxies must not retain the pre-registration flag.
  setHeader(event, 'cache-control', 'no-store')
  return getFeatureFlags()
})
