import { useQueryClient } from '@tanstack/vue-query'
import { initServiceClients } from '~/composables/useAuthFlow'

const AUTH_COOKIE_NAME = 'opnform_token'
const LEGACY_AUTH_COOKIE_NAME = 'token'
const ADMIN_AUTH_COOKIE_NAME = 'opnform_admin_token'
const LEGACY_ADMIN_AUTH_COOKIE_NAME = 'admin_token'

export default defineNuxtRouteMiddleware(async (_to, _from) => {
  const authStore = useAuthStore()
  const queryClient = useQueryClient()
  const tokenCookie = useCookie(AUTH_COOKIE_NAME)
  const legacyTokenCookie = useCookie(LEGACY_AUTH_COOKIE_NAME)
  const adminTokenCookie = useCookie(ADMIN_AUTH_COOKIE_NAME)
  const legacyAdminTokenCookie = useCookie(LEGACY_ADMIN_AUTH_COOKIE_NAME)
  const resolvedToken = tokenCookie.value ?? legacyTokenCookie.value
  const resolvedAdminToken = adminTokenCookie.value ?? legacyAdminTokenCookie.value

  // Hydrate missing tokens from cookies without overwriting a fresh in-memory
  // token during the same client-side navigation cycle.
  authStore.initStore(
    resolvedToken,
    resolvedAdminToken,
  )

  // If no token, nothing to do
  if (!authStore.token) {
    return
  }

  // Check for already cached user data (from SSR or previous fetch)
  let userData = queryClient.getQueryData(['user'])

  // Fetch user & workspaces only if not cached yet
  if (!userData) {
    try {
      const userQuery = useAuth().user()
      const workspacesQuery = useWorkspaces().list()
      await Promise.all([userQuery.suspense(), workspacesQuery.suspense()])

      userData = queryClient.getQueryData(['user'])
    } catch (error) {
      // A server-side bootstrap request can 401 even when the browser still has a
      // valid token (for example if SSR auth validation differs from the browser
      // request context). Do not destroy auth state during SSR; let the client
      // retry before treating it as a real logout.
      if (error?.status === 401) {
        if (import.meta.client) {
          authStore.clearToken()
          authStore.user = null
          queryClient.clear()
        }
      }
      return
    }
  }

  // Initialize service clients on client side (no-op on server)
  if (userData) {
    initServiceClients(userData)
  }
})
