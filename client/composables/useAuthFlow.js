import { WindowMessageTypes, useWindowMessage } from "~/composables/useWindowMessage"
import { authApi } from "~/api"

/**
 * Lightweight authentication check that doesn't require Vue Query context
 * Use this when you only need to check if user is authenticated
 */
export const useIsAuthenticated = () => {
  const authStore = useAuthStore()
  
  const isAuthenticated = computed(() => {
    return !!authStore.token
  })
  
  return { isAuthenticated }
}

/**
 * Initialize service clients without requiring Vue Query context
 * Safe to call from middleware or anywhere outside setup context
 */
export const initServiceClients = (userData) => {
  if (import.meta.server) return
  if (!userData) return
  
  useAmplitude().setUser(userData)
  useCrisp().setUser(userData)
}

// Shared state for 2FA modal - ensures all instances of useAuthFlow share the same state
const showTwoFactorModal = ref(false)
const pendingAuthToken = ref(null)
const pendingAuthContext = ref(null)

export const useAuthFlow = () => {
  const authStore = useAuthStore()
  const { logEvent } = useAmplitude()
  const router = useRouter()

  /**
   * Core authentication success handler  
   * Now checks for 2FA requirement before proceeding
   */
  const handleAuthSuccess = async (tokenData, source, isNewUser = false) => {
    // Check if 2FA is required
    if (tokenData.requires_2fa && tokenData.pending_auth_token) {
      // Store pending auth context and show modal
      pendingAuthToken.value = tokenData.pending_auth_token
      pendingAuthContext.value = { source, isNewUser }
      showTwoFactorModal.value = true
      return
    }

    // No 2FA required, proceed with normal flow
    await proceedWithAuthSuccess(tokenData, source, isNewUser)
  }

  /**
   * Proceed with authentication after 2FA verification or when 2FA not required
   */
  const proceedWithAuthSuccess = async (tokenData, source, isNewUser = false) => {
    // 1. Set token in store
    authStore.setToken(tokenData.token, tokenData.expires_in)

    // 2. Initialize service clients if user data is provided
    if (tokenData.user) {
      initServiceClients(tokenData.user)
    }

    // 3. Handle AppSumo license feedback (registration-specific)
    if (isNewUser && tokenData.appsumo_license !== undefined) {
      if (tokenData.appsumo_license === false) {
        useAlert().error(
          "Invalid AppSumo license. This probably happened because this license was already" +
          " attached to another OpnForm account. Please contact support."
        )
      } else if (tokenData.appsumo_license === true) {
        useAlert().success(
          "Your AppSumo license was successfully activated! You now have access to all the" +
          " features of the AppSumo deal."
        )
      }
    }

    // 4. Track analytics
    const eventName = isNewUser ? 'register' : 'login'
    logEvent(eventName, { source })
    
    try {
      // Check if GTM is available before using it
      const gtm = typeof useGtm === 'function' ? useGtm() : null
      if (gtm && typeof gtm.trackEvent === 'function') {
        gtm.trackEvent({
          event: eventName,
          source
        })
      }
    } catch (error) {
      console.error(error)
    }
  }

  /**
   * Handle 2FA error responses consistently
   * Checks if a 422 error is actually a 2FA requirement (not a validation error)
   * @param {Error} error - The error object from the API call
   * @returns {Object|null} - The response data if it's a 2FA requirement, null otherwise
   */
  const handleTwoFactorError = (error) => {
    // Check if this is a 422 response with requires_2fa flag
    if (error.response?.status === 422 && error.response?._data?.requires_2fa) {
      // This is a 2FA requirement, not a validation error
      return error.response._data
    }
    // This is a real error, return null to indicate it should be thrown
    return null
  }

  /**
   * Handle 2FA verification cancellation
   */
  const handleTwoFactorCancel = () => {
    showTwoFactorModal.value = false
    pendingAuthToken.value = null
    pendingAuthContext.value = null
  }

  /**
   * Handle 2FA verification success
   */
  const handleTwoFactorVerified = async (tokenData) => {
    showTwoFactorModal.value = false
    
    // Proceed with auth success using verified token data
    await proceedWithAuthSuccess(
      tokenData,
      pendingAuthContext.value?.source || 'credentials',
      pendingAuthContext.value?.isNewUser || false
    )
    
    // Clear pending context
    pendingAuthToken.value = null
    pendingAuthContext.value = null
  }

  /**
   * Handle social login callback
   */
  const handleSocialCallback = async (provider, code, utmData) => {
    const tokenData = await authApi.oauth.callback(provider, { code, utm_data: utmData })
    
    // Send messages to parent window if applicable
    if (window.opener && !window.opener.closed) {
      // Send login complete message (for auth flows)
      useWindowMessage(WindowMessageTypes.LOGIN_COMPLETE).send(window.opener, {
        eventType: WindowMessageTypes.LOGIN_COMPLETE,
        useMessageChannel: false,
        waitForAcknowledgment: false
      })
      
      // Send OAuth provider connected message (for cache invalidation)
      useWindowMessage(WindowMessageTypes.OAUTH_PROVIDER_CONNECTED).send(window.opener, {
        eventType: `${WindowMessageTypes.OAUTH_PROVIDER_CONNECTED}:${provider}`,
        useMessageChannel: false,
        waitForAcknowledgment: false
      })
    } 

    return handleAuthSuccess( 
      tokenData, 
      provider,
      tokenData.new_user
    )
  }

  /**
   * Handle manual logout (e.g., from UserDropdown)
   * Clears tokens, cache, and navigates to login page
   */
  const handleManualLogout = async () => {
    // Clear auth store tokens
    authStore.clearToken()
    
    // Clear user data
    authStore.user = null
    
    // Navigate to login page
    await router.push({ name: 'login' })
  }

  /**
   * Handle token expiry (401 errors)
   * Preserves cache and work state, opens QuickRegister modal
   */
  const handleTokenExpiry = async () => {
    const appStore = useAppStore()
    
    // Handle admin token expiry by undoing impersonation
    if (authStore.isImpersonating) {
      console.log("Admin token expired, undoing impersonation")
      authStore.stopImpersonating()
      useAlert().error("User token expired. You have been logged out of the admin account.")
      await router.push({ name: 'home' })
      return 
    }
    
    // Clear only the token, preserve user data and cache
    authStore.clearToken()
    
    // Set unauthorized error state and open quick login modal
    appStore.isUnauthorizedError = true
    appStore.quickLoginModal = true
  }

  return {
    // Auth flow functions
    handleAuthSuccess,
    handleSocialCallback,
    
    // Distinct logout methods
    handleManualLogout,
    handleTokenExpiry,
    
    // 2FA modal state and handlers
    showTwoFactorModal,
    pendingAuthToken,
    handleTwoFactorVerified,
    handleTwoFactorCancel,
    handleTwoFactorError,
  }
} 