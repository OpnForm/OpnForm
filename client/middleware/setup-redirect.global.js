import { sendRedirect } from 'h3'
import { unref } from 'vue'

/**
 * Global middleware to redirect to setup page when setup is required
 * Only runs in self-hosted mode when no users exist in database
 */
export default defineNuxtRouteMiddleware(async (to) => {
  // Skip redirect for setup page itself and API routes
  if (to.path.startsWith('/setup') || to.path.startsWith('/api')) {
    return
  }

  try {
    // Use the feature flag shortcut (loaded by 00.feature-flags.global.js)
    const setupRequiredRef = useFeatureFlag('setup_required', false)
    const setupRequired = unref(setupRequiredRef) // Handle both ref and non-ref values
    
    if (!setupRequired) {
      return // Setup not required, continue normally
    }

    // Server-side redirect (SSR)
    if (import.meta.server) {
      const event = useRequestEvent()
      if (event) {
        await sendRedirect(event, '/setup', 302)
        return
      }
    }
    
    // Client-side redirect fallback
    if (import.meta.client) {
      await navigateTo('/setup')
    }
    
  } catch (error) {
    // On error, don't block navigation - log and continue
    console.warn('Setup redirect middleware failed:', error)
  }
}) 