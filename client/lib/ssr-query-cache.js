export function registerSsrQueryCleanup(nuxt, queryClient, state, dehydrate) {
  nuxt.hooks.hook('app:rendered', () => {
    state.value = dehydrate(queryClient)
    queryClient.clear()
  })
  nuxt.hooks.hook('app:error', () => queryClient.clear())
}
