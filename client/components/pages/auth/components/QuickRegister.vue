<template>
  <div>
    <!--  Login modal  -->
    <UModal
      v-model:open="isLoginModalOpen"
      title="Login to OpnForm"
      :dismissible="!appStore.isUnauthorizedError"
      @pointer-down-outside="handlePointerDownOutside"
    >
      <template #body>
        <template v-if="appStore.isUnauthorizedError">
          <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-900/30 border border-amber-200 dark:border-amber-700 rounded-md">
            <p class="text-amber-800 dark:text-amber-200 text-sm font-medium">
              Your session has expired. Please log in again to continue.
            </p>
          </div>
        </template>

        <LoginForm
          :is-quick="true"
          @open-register="openRegister"
        />

        <template v-if="appStore.isUnauthorizedError">
          <div class="flex items-center my-6">
            <div class="h-[1px] bg-neutral-300 dark:bg-neutral-600 flex-1" />
            <div class="px-4 text-neutral-500 text-sm">
              or
            </div>
            <div class="h-[1px] bg-neutral-300 dark:bg-neutral-600 flex-1" />
          </div>
          <UButton
            icon="i-heroicons-arrow-right-on-rectangle"
            type="button"
            color="neutral"
            variant="outline"
            label="Logout"
            :block="true"
            size="lg"
            @click="logout"
          />
          <p class="text-neutral-500 text-sm text-center mt-2">
            Progress will be lost.
          </p>
        </template>
      </template>
    </UModal>

    <!--  Register modal  -->
    <UModal
      v-model:open="isRegisterModalOpen"
      :ui="{ content: 'sm:max-w-lg' }"
      title="Create an account"
      :dismissible="!appStore.isUnauthorizedError"
      @pointer-down-outside="handlePointerDownOutside"
    >
      <template #body>
        <RegisterForm
          :is-quick="true"
          @open-login="openLogin"
        />
      </template>
    </UModal>
  </div>
</template>

<script setup>
import LoginForm from "./LoginForm.vue"
import RegisterForm from "./RegisterForm.vue"
import { WindowMessageTypes } from "~/composables/useWindowMessage"

const appStore = useAppStore()

// Define emits for component interactions
const emit = defineEmits(['close', 'reopen'])

// Modal state
const isLoginModalOpen = computed({
  get() {
    return appStore.quickLoginModal
  },
  set(value) {
    appStore.quickLoginModal = value
  }
})

const isRegisterModalOpen = computed({
  get() {
    return appStore.quickRegisterModal
  },
  set(value) {
    appStore.quickRegisterModal = value
  }
})

const windowMessage = useWindowMessage(WindowMessageTypes.LOGIN_COMPLETE)

// Set up a listener for after-login messages
const afterLoginMessage = useWindowMessage(WindowMessageTypes.AFTER_LOGIN)

onMounted(() => {
  // Listen for login-complete messages from popup window
  windowMessage.listen(() => {
    // Handle social login completion
    handleSocialLogin()
  })
})

// Reset the unauthorized error flag when component is unmounted
onUnmounted(() => {
  appStore.isUnauthorizedError = false
})

// Handle social login completion
const handleSocialLogin = () => {
  // This function is only called for social logins, so we refresh tokens
  const authStore = useAuthStore()
  const tokenValue = useCookie("token").value
  const adminTokenValue = useCookie("admin_token").value
  
  // Re-initialize the store with the latest tokens from cookies
  authStore.initStore(tokenValue, adminTokenValue)
  
  // Then proceed with normal login flow
  afterQuickLogin()
}

const openLogin = () => {
  appStore.quickLoginModal = true
  appStore.quickRegisterModal = false
  emit('close')
}

const openRegister = () => {
  appStore.quickLoginModal = false
  appStore.quickRegisterModal = true
  emit('reopen')
}

const afterQuickLogin = () => {
  appStore.isUnauthorizedError = false
  appStore.quickLoginModal = false
  appStore.quickRegisterModal = false
  
  // Show success alert
  useAlert().success("Successfully logged in!")
  
  // Use the window message for after-login instead of emitting the event
  afterLoginMessage.send(window, { useMessageChannel: false })
}

const logout = () => {
  appStore.isUnauthorizedError = false
  appStore.quickLoginModal = false
  appStore.quickRegisterModal = false
  useRouter().push('/login')
}

// Handle pointer down outside modal to allow Google One Tap interaction
const handlePointerDownOutside = (event) => {
  const target = event.target
  // Allow interaction with Google One Tap picker
  if (target?.closest('#credential_picker_container') || 
      target?.closest('iframe[src*="accounts.google.com"]') ||
      target?.id === 'credential_picker_iframe') {
    event.preventDefault()
  }
}
</script>
