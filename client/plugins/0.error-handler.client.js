import * as Sentry from '@sentry/nuxt'
import { createChunkErrorRecovery } from '~/lib/chunk-error-recovery.js'

export default defineNuxtPlugin((nuxtApp) => {
  const recover = createChunkErrorRecovery({
    captureException: Sentry.captureException,
    flush: Sentry.flush,
    reload: reloadNuxtApp,
  })

  nuxtApp.hook('app:chunkError', recover)
})
