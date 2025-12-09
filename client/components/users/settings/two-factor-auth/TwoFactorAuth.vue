<template>
  <div class="space-y-4">
    <div>
      <h3 class="text-lg font-medium text-neutral-900">Two-Factor Authentication</h3>
      <p class="text-sm text-neutral-500 mt-1">
        Add an extra layer of security to your account using an authenticator app.
      </p>
    </div>

    <!-- 2FA Status -->
    <div v-if="twoFactorEnabled" class="space-y-4">
      <UAlert
        color="success"
        variant="subtle"
        icon="i-heroicons-check-circle"
        description="Two-factor authentication is enabled on your account."
      />

      <div class="flex gap-2">
        <UButton
          color="neutral"
          variant="outline"
          @click="openRecoveryCodesModal"
        >
          View Recovery Codes
        </UButton>
        <UButton
          color="neutral"
          variant="outline"
          @click="showDisableModal = true"
        >
          Disable 2FA
        </UButton>
      </div>
    </div>

    <!-- Enable 2FA Flow -->
    <TwoFactorEnableFlow
      v-else
      :enabling="enabling2FA"
      :confirming="confirming2FA"
      :secret="twoFactorSecret"
      :qr-code="twoFactorQrCode"
      @enable="enableTwoFactor"
      @confirm="confirmTwoFactor"
    />

    <!-- Recovery Codes Modal -->
    <RecoveryCodesModal
      :show="showRecoveryCodesModal"
      :codes="recoveryCodesList"
      :loading="loadingRecoveryCodes"
      :regenerating="regeneratingCodes"
      :just-regenerated="justRegenerated"
      @close="closeRecoveryCodesModal"
      @load="loadRecoveryCodesWithPassword"
      @regenerate="handleRegenerateRecoveryCodes"
      @copy="copyRecoveryCodes"
    />

    <!-- Disable 2FA Modal -->
    <DisableTwoFactorModal
      :show="showDisableModal"
      :loading="disabling2FA"
      @close="closeDisableModal"
      @disable="disableTwoFactor"
    />
  </div>
</template>

<script setup>
import { useTwoFactor } from '~/composables/query/useTwoFactor'
import TwoFactorEnableFlow from './TwoFactorEnableFlow.vue'
import RecoveryCodesModal from './RecoveryCodesModal.vue'
import DisableTwoFactorModal from './DisableTwoFactorModal.vue'

const alert = useAlert()
const auth = useAuth()
const { data: user } = auth.user()

// Two-Factor Authentication
const { enable, confirm, disable, recoveryCodes: getRecoveryCodes, regenerateRecoveryCodes } = useTwoFactor()

const twoFactorEnabled = computed(() => user.value?.two_factor_enabled ?? false)
const twoFactorSecret = ref(null)
const twoFactorQrCode = ref(null)
const recoveryCodesList = ref([])
const showRecoveryCodesModal = ref(false)
const showDisableModal = ref(false)

const enabling2FA = ref(false)
const confirming2FA = ref(false)
const disabling2FA = ref(false)
const loadingRecoveryCodes = ref(false)
const regeneratingCodes = ref(false)
const justRegenerated = ref(false)

const enableMutation = enable()
const confirmMutation = confirm()
const disableMutation = disable()
const recoveryCodesMutation = getRecoveryCodes()
const regenerateMutation = regenerateRecoveryCodes()

const enableTwoFactor = async () => {
  enabling2FA.value = true
  try {
    const response = await enableMutation.mutateAsync()
    twoFactorSecret.value = response.secret
    twoFactorQrCode.value = response.qr_code
  } catch (error) {
    alert.error(error.response?._data?.message || 'Failed to enable two-factor authentication')
  } finally {
    enabling2FA.value = false
  }
}

const confirmTwoFactor = async (code) => {
  confirming2FA.value = true
  try {
    const response = await confirmMutation.mutateAsync({ code })
    
    recoveryCodesList.value = response.recovery_codes || []
    twoFactorSecret.value = null
    twoFactorQrCode.value = null
    
    // Refresh user data
    await auth.invalidateUser()
    
    // Show recovery codes modal automatically after enabling
    justRegenerated.value = false
    showRecoveryCodesModal.value = true
    
    alert.success('Two-factor authentication has been enabled successfully.')
  } catch (error) {
    alert.error(error.response?._data?.message || error.response?._data?.errors?.code?.[0] || 'Invalid code. Please try again.')
  } finally {
    confirming2FA.value = false
  }
}

const disableTwoFactor = async (code) => {
  disabling2FA.value = true
  try {
    await disableMutation.mutateAsync({ code })
    
    showDisableModal.value = false
    
    // Refresh user data
    await auth.invalidateUser()
    
    alert.success('Two-factor authentication has been disabled.')
  } catch (error) {
    alert.error(error.response?._data?.message || error.response?._data?.errors?.code?.[0] || 'Invalid code. Please try again.')
  } finally {
    disabling2FA.value = false
  }
}

const openRecoveryCodesModal = () => {
  recoveryCodesList.value = []
  justRegenerated.value = false
  showRecoveryCodesModal.value = true
}

const closeRecoveryCodesModal = () => {
  showRecoveryCodesModal.value = false
}

const closeDisableModal = () => {
  showDisableModal.value = false
}

const loadRecoveryCodesWithPassword = async (password) => {
  loadingRecoveryCodes.value = true
  try {
    const response = await recoveryCodesMutation.mutateAsync({ password })
    recoveryCodesList.value = response.recovery_codes || []
  } catch (error) {
    alert.error(error.response?._data?.message || error.response?._data?.errors?.password?.[0] || 'Failed to load recovery codes')
  } finally {
    loadingRecoveryCodes.value = false
  }
}

const handleRegenerateRecoveryCodes = async () => {
  regeneratingCodes.value = true
  try {
    const response = await regenerateMutation.mutateAsync()
    recoveryCodesList.value = response.recovery_codes || []
    justRegenerated.value = true
    alert.success('Recovery codes have been regenerated. Please save them in a safe place.')
  } catch (error) {
    alert.error(error.response?._data?.message || 'Failed to regenerate recovery codes')
  } finally {
    regeneratingCodes.value = false
  }
}

const copyRecoveryCodes = async () => {
  try {
    // Extract just the codes from objects
    const codes = recoveryCodesList.value.map(item => 
      typeof item === 'string' ? item : item.code
    )
    const codesText = codes.join('\n')
    await navigator.clipboard.writeText(codesText)
    alert.success('Recovery codes copied to clipboard')
  } catch {
    alert.error('Failed to copy recovery codes')
  }
}
</script>

