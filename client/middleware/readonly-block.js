export default defineNuxtRouteMiddleware(() => {
  const { isAuthenticated } = useIsAuthenticated()
  if (!isAuthenticated.value) return

  const { current } = useCurrentWorkspace()
  if (current?.value?.is_readonly) {
    throw createError({ statusCode: 403, statusMessage: 'Forbidden' })
  }
})


