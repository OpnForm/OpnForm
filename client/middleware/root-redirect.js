export default defineNuxtRouteMiddleware(async (to) => {
  const { performRedirect, shouldRedirect } = useSubdomainRedirect()

  if (!shouldRedirect() && to.path === '/' && useFeatureFlag('self_hosted') && useIsAuthenticated().isAuthenticated.value) {
    return navigateTo({ name: 'home' })
  }
  
  // Perform redirect with iframe check disabled for root pages
  // (root pages should always redirect regardless of iframe context)
  await performRedirect({ skipIfIframe: false })
})
