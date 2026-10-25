/**
 * Lightweight authentication check that does not pull the full login and 2FA
 * flow into public routes.
 */
export const useIsAuthenticated = () => {
  const authStore = useAuthStore()
  const isAuthenticated = computed(() => !!authStore.token)

  return { isAuthenticated }
}
