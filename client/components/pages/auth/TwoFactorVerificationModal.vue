<template>
  <UModal
    :open="isOpen"
    :ui="{ content: 'sm:max-w-md' }"
    :dismissible="true"
    @close="handleCancel"
  >
    <template #header>
      <h2 class="text-lg font-semibold">Two-Factor Authentication</h2>
    </template>

    <template #body>
      <div class="space-y-4">
        <p class="text-sm text-neutral-600">
          Enter the 6-digit code from your authenticator app.
        </p>

        <div class="flex justify-center">
          <UPinInput
            v-model="code"
            :length="6"
            type="number"
            otp
            autofocus
            size="lg"
            :highlight="!!error"
            @complete="handleComplete"
            @update:model-value="handleCodeChange"
          />
        </div>

        <div v-if="error && hasAttemptedVerification" class="mt-4">
          <UAlert
            color="error"
            variant="subtle"
            :description="error"
          />
        </div>

        <div class="mt-4 text-center">
          <UButton
            variant="link"
            size="sm"
            @click="showRecoveryCode = !showRecoveryCode"
          >
            Use recovery code instead
          </UButton>
        </div>

        <VTransition name="fadeHeight">
          <div v-if="showRecoveryCode" class="mt-4">
            <TextInput
              v-model="recoveryCode"
              label="Recovery Code"
              placeholder="Enter recovery code"
              @keyup.enter="verifyRecoveryCode"
            />
            <UButton
              class="mt-2"
              block
              :loading="verifying"
              @click="verifyRecoveryCode"
            >
              Verify Recovery Code
            </UButton>
          </div>
        </VTransition>
      </div>
    </template>

    <template #footer>
      <div class="flex justify-end gap-2">
        <UButton
          color="neutral"
          variant="outline"
          @click="handleCancel"
        >
          Cancel
        </UButton>
      </div>
    </template>
  </UModal>
</template>

<script setup>
import { authApi } from '~/api/auth'

const props = defineProps({
  show: { type: Boolean, required: true },
  pendingAuthToken: { type: String, default: null },
})

const emit = defineEmits(['verified', 'cancel'])

const isOpen = computed(() => props.show && !!props.pendingAuthToken)

const code = ref([])
const recoveryCode = ref('')
const showRecoveryCode = ref(false)
const verifying = ref(false)
const error = ref(null)
const hasAttemptedVerification = ref(false) // Track if we've actually tried to verify

// Clear error and reset state when modal opens
watch(() => props.show, (isShowing) => {
  if (isShowing) {
    error.value = null
    code.value = []
    recoveryCode.value = ''
    showRecoveryCode.value = false
    verifying.value = false
    hasAttemptedVerification.value = false
  }
}, { immediate: true })


const handleCodeChange = (value) => {
  // Clear error when user starts typing a new code (only if we've attempted verification before)
  if (error.value && hasAttemptedVerification.value && value && value.length < 6) {
    error.value = null
  }
}

const handleComplete = async (value) => {
  // Only verify if we have exactly 6 digits, we're not already verifying, and value is valid
  if (value && Array.isArray(value) && value.length === 6 && !verifying.value) {
    const codeString = value.join('')
    // Ensure all digits are valid numbers
    if (codeString.length === 6 && /^\d{6}$/.test(codeString)) {
      await verifyCode(codeString)
    }
  }
}

const verifyCode = async (codeValue) => {
  // Validate code format before making API call
  if (!codeValue || codeValue.length !== 6 || !/^\d{6}$/.test(codeValue)) {
    return
  }

  // Validate pending auth token exists
  if (!props.pendingAuthToken) {
    error.value = 'Authentication session expired. Please try logging in again.'
    return
  }

  verifying.value = true
  error.value = null
  hasAttemptedVerification.value = true

  try {
    const response = await authApi.twoFactor.verify({
      pending_auth_token: props.pendingAuthToken,
      code: codeValue,
    })

    emit('verified', response)
  } catch (err) {
    // TanStack Query errors have different structure - check for response or data
    if (err.response) {
      error.value = err.response?._data?.message || err.response?._data?.errors?.code?.[0] || 'Invalid code. Please try again.'
    } else if (err.data) {
      error.value = err.data?.message || err.data?.errors?.code?.[0] || 'Invalid code. Please try again.'
    } else {
      // Network error or other issue
      console.error('2FA verification error:', err)
      error.value = 'Unable to verify code. Please check your connection and try again.'
    }
    code.value = []
  } finally {
    verifying.value = false
  }
}

const verifyRecoveryCode = async () => {
  if (!recoveryCode.value) return
  await verifyCode(recoveryCode.value)
}

const handleCancel = () => {
  code.value = []
  recoveryCode.value = ''
  showRecoveryCode.value = false
  error.value = null
  emit('cancel')
}
</script>

