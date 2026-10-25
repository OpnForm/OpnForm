export default defineNuxtRouteMiddleware(() => {
  return navigateTo(
    { name: "self-hosted-form-builder-license" },
    { redirectCode: 301, replace: true },
  )
})
