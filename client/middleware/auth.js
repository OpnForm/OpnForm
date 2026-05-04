export default defineNuxtRouteMiddleware((to) => {
  const { isAuthenticated } = useIsAuthenticated()

  if (!isAuthenticated.value) {
    useCookie("intended_url").value = to.fullPath
    return navigateTo({ name: "login" })
  }
})
