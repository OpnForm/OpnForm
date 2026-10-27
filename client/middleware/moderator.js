export default defineNuxtRouteMiddleware(async () => {
  const { isAuthenticated } = useIsAuthenticated()
  if (!isAuthenticated.value) throw createError({ statusCode: 404, statusMessage: 'Not found' })
  const { user } = useAuth()
  const { data: userData, suspense } = user()
  await suspense()
  if (!userData.value?.moderator) throw createError({ statusCode: 404, statusMessage: 'Not found' })
})
