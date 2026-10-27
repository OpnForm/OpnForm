export default defineNuxtRouteMiddleware(async () => {
  const { isAuthenticated } = useIsAuthenticated()
  if (!isAuthenticated.value) throw createError({ statusCode: 403, statusMessage: 'Forbidden' })
  const { user } = useAuth()
  const { data: userData, suspense } = user()
  await suspense()
  if (!userData.value?.admin) throw createError({ statusCode: 403, statusMessage: 'Forbidden' })
})
