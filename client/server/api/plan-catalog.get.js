export default defineCachedEventHandler(async () => {
  const config = useRuntimeConfig()
  const apiBase = config.privateApiBase || config.public.apiBase

  return $fetch('/content/plans', {
    baseURL: apiBase,
    headers: {
      accept: 'application/json',
      ...(config.apiSecret && { 'x-api-secret': config.apiSecret }),
    },
  })
}, {
  maxAge: 10 * 60,
  name: 'plan-catalog',
  getKey: (event) => {
    const url = new URL(event.node.req.url || '', 'http://localhost')
    const timestamp = url.searchParams.get('t')
    return timestamp ? `global:${timestamp}` : 'global'
  },
  swr: true,
})
