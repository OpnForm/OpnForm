// The plan catalog is public and shared by every request to this installation.
export default defineCachedEventHandler(() => {
  const config = useRuntimeConfig()

  return $fetch('/content/plans', {
    baseURL: config.privateApiBase || config.public.apiBase,
    retry: 0,
    headers: {
      accept: 'application/json',
      ...(config.apiSecret && { 'x-api-secret': config.apiSecret })
    }
  }).then((plans) => {
    if (!plans?.tiers || typeof plans.tiers !== 'object' || Array.isArray(plans.tiers)) {
      throw createError({ statusCode: 502, statusMessage: 'Invalid plan catalog response' })
    }
    return plans
  })
}, {
  maxAge: 10 * 60,
  name: 'plan-catalog',
  getKey: () => 'global',
  swr: true
})
