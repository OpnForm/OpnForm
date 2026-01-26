<template>
  <div class="flex flex-grow mt-6 mb-10">
    <two-factor-verification-modal
      v-if="pendingAuthToken"
      :show="showTwoFactorModal"
      :pending-auth-token="pendingAuthToken"
      @verified="handleTwoFactorVerifiedAndRedirect"
      @cancel="handleTwoFactorCancel"
    />

    <div class="w-full md:w-2/3 md:mx-auto md:max-w-md px-4">
      <div
        v-if="loading || showTwoFactorModal"
        class="m-10"
      >
        <h3 class="my-6 text-center">
          {{ showTwoFactorModal ? 'Verifying your code...' : 'Completing sign in...' }}
        </h3>
        <Loader class="h-6 w-6 mx-auto m-10" />
      </div>
      <div
        v-else-if="error"
        class="m-6 flex flex-col items-center space-y-4"
      >
        <UAlert
          icon="i-lucide-alert-triangle"
          color="error"
          variant="subtle"
          class="w-full"
          :description="error"
        />
        <UButton
          v-if="linkToken"
          color="primary"
          label="Link existing account"
          @click="goToLinkLogin"
        />
        <UButton
          :to="{ name: 'login' }"
          label="Back to login"
        />
      </div>
    </div>
  </div>
</template>

<script setup>
import { oidcApi } from "~/api"

const router = useRouter()
const route = useRoute()
const loading = ref(true)
const error = ref(null)
const linkToken = ref(null)
const { startLink } = useOidcLinking()
const authFlow = useAuthFlow()
const { showTwoFactorModal, pendingAuthToken, handleTwoFactorVerified, handleTwoFactorCancel: handleTwoFactorCancelFromFlow, handleTwoFactorError } = authFlow

const handleCallback = async () => {
  const slug = route.params.slug
  
  try {
    // Build query params object from route
    const queryParams = {}
    Object.keys(route.query).forEach(key => {
      if (route.query[key]) {
        queryParams[key] = route.query[key]
      }
    })
    
    // Call the OIDC callback endpoint to process authorization code
    let response
    try {
      response = await oidcApi.callback(slug, queryParams)
    } catch (error) {
      // Handle 422 responses that indicate 2FA is required (not validation errors)
      const twoFactorResponse = handleTwoFactorError(error)
      if (twoFactorResponse) {
        response = twoFactorResponse
      } else {
        throw error
      }
    }
    
    // Handle authentication success (handles both 2FA and non-2FA cases)
    // handleAuthSuccess will check for requires_2fa and show modal if needed
    await authFlow.handleAuthSuccess(response, 'oidc', response.new_user)
    
    // If 2FA modal is shown, don't redirect yet (handled in handleTwoFactorVerifiedAndRedirect)
    if (showTwoFactorModal.value) {
      loading.value = false
      return
    }
    
    // No 2FA required, proceed with redirect
    // Response should contain token and user when 2FA is not required
    if (!response.token || !response.user) {
      throw new Error("Unexpected response format from OIDC callback")
    }
    
    // Redirect based on user status (aligned with OAuth callback pattern)
    if (response.new_user) {
      router.push({ name: "forms-create" })
      useAlert().success("Success! You're now registered with OIDC. Welcome to OpnForm.")
    } else {
      // For existing users, redirect to intended URL or home (aligned with OAuth)
      router.push({ name: "home" })
      useAlert().success("Successfully signed in!")
    }
  } catch (err) {
    console.error("[OIDC Callback] Authentication error:", err)
    
    const errorResponse = err.response?._data || {}
    const errorMessage = errorResponse.message || err.message || "Authentication failed"
    error.value = errorMessage
    
    // Handle specific error cases
    if (errorResponse.error === 'oidc_account_link_required' && errorResponse.link_token) {
      error.value = 'An account with this email already exists. Please link your existing account to continue.'
      linkToken.value = errorResponse.link_token
    } else if (errorMessage.includes('account with this email already exists')) {
      error.value = 'An account with this email already exists. Please contact your administrator to link your accounts.'
    } else if (errorMessage.includes('blocked')) {
      error.value = 'Your account has been blocked. Please contact support.'
    }
    
    loading.value = false
  }
}

const handleTwoFactorCancel = () => {
  handleTwoFactorCancelFromFlow()
  router.push({ name: 'login' })
}

const goToLinkLogin = () => {
  startLink(linkToken.value)
}

const handleTwoFactorVerifiedAndRedirect = async (tokenData) => {
  await handleTwoFactorVerified(tokenData)
  
  // Redirect based on user status
  if (tokenData.new_user) {
    router.push({ name: "forms-create" })
    useAlert().success("Success! You're now registered with OIDC. Welcome to OpnForm.")
  } else {
    router.push({ name: "home" })
    useAlert().success("Successfully signed in!")
  }
}

onMounted(() => {
  // Set a timeout to ensure we don't get stuck in loading state
  const timeoutId = setTimeout(() => {
    if (loading.value) {
      loading.value = false
      error.value = 'Authentication timed out. Please try again.'
    }
  }, 10000) // 10 second timeout
  
  handleCallback().finally(() => {
    clearTimeout(timeoutId)
  })
})
</script>

