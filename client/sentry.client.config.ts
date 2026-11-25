import * as Sentry from "@sentry/nuxt";
import { hasChunkReload } from "./lib/chunk-error-recovery.js";
import { isAnonymousFormMessageCloneError } from "./lib/telemetry/custom-message-event-error.js";
import { SENTRY_APPLICATION_KEY, shouldDropThirdPartyError } from "./lib/telemetry/third-party-errors.js";

const runtimeConfig = useRuntimeConfig()

Sentry.init({
  // If set up, you can use your runtime config here
  // dsn: useRuntimeConfig().public.sentry.dsn,
  dsn: runtimeConfig.public.SENTRY_DSN_PUBLIC ?? null,

  integrations: [
    Sentry.thirdPartyErrorFilterIntegration({
      filterKeys: [SENTRY_APPLICATION_KEY],
      behaviour: 'apply-tag-if-exclusively-contains-third-party-frames',
    }),
  ],
  
  // Setting this option to true will print useful information to the console while you're setting up Sentry.
  debug: false,

  // Ensure that source maps are properly handled
  attachStacktrace: true,

  beforeSend (event, hint) {
    if (hasChunkReload(hint.originalException) || isAnonymousFormMessageCloneError(event)) {
      return null
    }
    if (shouldDropThirdPartyError(event, [
      window.location.origin, runtimeConfig.public.appUrl, runtimeConfig.app.cdnURL,
    ])) {
      return null
    }

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
      
      if (errorValue.includes('Failed to load resource')) {
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
