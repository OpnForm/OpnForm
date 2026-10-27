import { getSelfHostedPublicRedirect } from '~/lib/self-hosted-public.js'

export default defineNuxtRouteMiddleware((to) => {
  if (!useFeatureFlag('self_hosted') || useFeatureFlag('setup_required', false)) return

  const config = useRuntimeConfig().public
  const redirect = getSelfHostedPublicRedirect(to.path, {
    ...config.selfHosted,
    rootRedirectUrl: config.rootRedirectUrl,
  })

  if (redirect) {
    return navigateTo(redirect, { external: redirect !== '/', redirectCode: 302 })
  }
})
