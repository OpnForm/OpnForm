// Global installation configuration: never forward cookies, tokens or custom domains.
export default defineCachedEventHandler(() => {
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
  swr: true
})
