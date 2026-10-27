import { filterSelfHostedSitemapUrls } from '~/lib/self-hosted-public.js'

export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook('sitemap:resolved', (context) => {
    // Published Docker images discover the deployment mode at runtime. The API
    // path alone cannot distinguish cloud from a self-hosted installation.
    return $fetch('/api/feature-flags', { retry: 0 })
      .then((flags) => {
        if (flags.self_hosted) {
          context.urls = filterSelfHostedSitemapUrls(context.urls)
        }
      })
      // Keep the build-time policy if the installation API is unavailable.
      .catch(() => {})
  })
})
