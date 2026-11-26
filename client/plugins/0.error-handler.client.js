import { captureException } from '@sentry/nuxt'
import { createChunkErrorRecovery } from '~/lib/chunk-error-recovery.js'

export default defineNuxtPlugin((nuxtApp) => {
  const recover = createChunkErrorRecovery({
    reload: reloadNuxtApp,
    reportBlockedCss: captureException,
    isReloadBlocked () {
      // Match the persisted loop guard used by reloadNuxtApp.
      try {
        const previousReload = JSON.parse(sessionStorage.getItem('nuxt:reload'))
        return previousReload?.path === window.location.pathname &&
          previousReload.expires >= Date.now()
      } catch {
        return false
      }
    },
  })

  nuxtApp.hook('app:chunkError', recover)
})
