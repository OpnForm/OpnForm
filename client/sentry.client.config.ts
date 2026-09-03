import * as Sentry from "@sentry/nuxt";
import { CHUNK_ERROR_RECOVERY_TAG } from './lib/chunk-error-recovery.js'

Sentry.init({
  // If set up, you can use your runtime config here
  // dsn: useRuntimeConfig().public.sentry.dsn,
  dsn: useRuntimeConfig().public.SENTRY_DSN_PUBLIC ?? null,

  // If you don't want to use Session Replay, just remove the line below:
  integrations: [],
  
  // Setting this option to true will print useful information to the console while you're setting up Sentry.
  debug: false,

  // Ensure that source maps are properly handled
  attachStacktrace: true,

  beforeSend (event) {
    if (event.exception?.values?.length) {
      const errorType = event.exception.values[0]?.type || '';
      const errorValue = event.exception.values[0]?.value || '';
      
      // Don't send validation exceptions to Sentry
      if (
        errorType === 'FetchError' &&
        (errorValue.includes('422') || errorValue.includes('401'))
      ) {
        return null
      }
      
      // The recovery plugin reports exact Nuxt chunk errors before reloading.
      // Keep filtering noisy browser errors that merely resemble chunk failures.
      const isRecoveredChunkError = event.tags?.handled_by === CHUNK_ERROR_RECOVERY_TAG
      const resemblesChunkError =
        errorValue.includes('Failed to fetch dynamically imported module') ||
        errorValue.includes('Loading chunk') ||
        errorValue.includes('Failed to load resource')

      if (!isRecoveredChunkError && resemblesChunkError) {
        return null
      }
    }

    // Avoid useAuth()/vue-query here: beforeSend runs outside Vue injection context.
    const authStore = useAuthStore()
    if (authStore.token && authStore.user) {
      Sentry.setUser({
        id: authStore.user?.id,
        email: authStore.user?.email
      })
    }
    return event
  }
});
