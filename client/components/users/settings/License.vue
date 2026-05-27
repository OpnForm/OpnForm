<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col flex-wrap items-start justify-between gap-4 sm:flex-row sm:items-center">
      <div>
        <h3 class="text-lg font-medium text-neutral-900">Enterprise License</h3>
        <p class="mt-1 text-sm text-neutral-500">
          Manage your self-hosted Enterprise license.
        </p>
      </div>

      <div class="flex shrink-0 items-center gap-2">
        <UButton
          label="Help"
          icon="i-heroicons-question-mark-circle"
          variant="outline"
          color="primary"
          @click="crisp.openHelpdeskArticle('self-hosted-license-3ihg7e')"
        />

        <UButton
          v-if="!canAccessEnterprise || isGracePeriod"
          :label="(isExpired || isGracePeriod) ? 'Renew License' : 'Purchase License'"
          icon="i-heroicons-shopping-cart"
          @click="openPurchase"
        />
      </div>
    </div>

    <div v-if="canAccessEnterprise" class="space-y-4">
      <div class="rounded-lg border border-neutral-200 bg-white p-4 sm:p-5 space-y-4">
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-start gap-3">
            <div
              class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-full"
              :class="isGracePeriod ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'"
            >
              <Icon
                :name="isGracePeriod ? 'i-heroicons-clock' : 'i-heroicons-check-circle'"
                class="h-5 w-5"
              />
            </div>
            <div>
              <p class="text-sm font-semibold text-neutral-900">
                {{ isGracePeriod ? 'License in grace period' : 'License activated' }}
              </p>
              <p class="mt-1 text-sm text-neutral-600">
                {{ isGracePeriod ? 'Renew now to avoid losing enterprise features.' : 'Your Enterprise license is active and all licensed features are enabled.' }}
              </p>
            </div>
          </div>
        </div>
      </div>
      <div class="flex flex-wrap gap-3">
        <UButton
          icon="i-heroicons-credit-card"
          :loading="managingSubscription"
          :disabled="managingSubscription"
          @click="openPortal"
        >
          Manage Subscription
        </UButton>
      </div>
    </div>

    <div v-else class="space-y-4">
      <div class="rounded-lg border p-4 sm:p-5 space-y-4" :class="inactiveCardClasses">
        <div class="flex items-start gap-3">
          <div
            class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-full"
            :class="inactiveIconClasses"
          >
            <Icon
              :name="inactiveIcon"
              class="h-5 w-5"
            />
          </div>
          <div>
            <p class="text-sm font-semibold text-neutral-900">
              {{ inactiveTitle }}
            </p>
            <p class="mt-1 text-sm text-neutral-600">
              {{ inactiveDescription }}
            </p>
          </div>
        </div>
      </div>

      <div class="space-y-2">
        <TextInput
          label="License Key"
          name="license_key"
          :form="licenseKeyForm"
          placeholder="lic_xxxxxxxxxxxxxxxxxxxxxxxx"
        />
        <UButton
          :loading="activating"
          :disabled="!licenseKeyForm.license_key || activating"
          icon="i-heroicons-check-circle"
          @click="activateLicense"
        >
          Activate License
        </UButton>
      </div>
    </div>
  </div>
</template>

<script setup>
import { licenseApi } from '~/api'
import { useInstanceLicense } from '~/composables/useInstanceLicense'

const crisp = useCrisp()
const alert = useAlert()
const { invalidateFlags } = useFeatureFlags()
const { invalidateUser } = useAuth()
const { openSubscriptionModal } = useAppModals()

const {
  hasLicense,
  canAccessEnterprise,
  isGracePeriod,
  isExpired,
  isActivationLimitReached,
} = useInstanceLicense()

const activating = ref(false)
const managingSubscription = ref(false)
const licenseKeyForm = useForm({
  license_key: '',
})

const openPurchase = () => {
  openSubscriptionModal({ plan: 'self_hosted' })
}

const inactiveCardClasses = computed(() => {
  return hasLicense.value ? 'border-red-200 bg-red-50/40' : 'border-neutral-200 bg-white'
})

const inactiveIconClasses = computed(() => {
  return hasLicense.value ? 'bg-red-100 text-red-600' : 'bg-neutral-100 text-neutral-500'
})

const inactiveIcon = computed(() => {
  return hasLicense.value ? 'i-heroicons-exclamation-triangle' : 'i-heroicons-key'
})

const inactiveTitle = computed(() => {
  if (isActivationLimitReached.value) return 'License Already Activated'
  if (isExpired.value) return 'License Expired'
  if (hasLicense.value) return 'License Inactive'
  return 'No Active License'
})

const inactiveDescription = computed(() => {
  if (isActivationLimitReached.value) {
    return 'This license key is already activated on another self-hosted instance. Contact support to reset it or activate a different key.'
  }
  if (isExpired.value) {
    return 'Enterprise features are currently disabled. Renew or activate a valid license key to restore access.'
  }
  if (hasLicense.value) {
    return 'Enterprise features are currently disabled. Activate a valid license key to restore access.'
  }
  return 'Enter your license key below to enable Enterprise features for this self-hosted instance.'
})

const openPortal = () => {
  managingSubscription.value = true
  licenseApi.portal().then((response) => {
    window.open(response.portalUrl, '_blank')
  }).catch((error) => {
    alert.error(error?.data?.message || 'Failed to open billing portal.')
  }).finally(() => {
    managingSubscription.value = false
  })
}

const activateLicense = () => {
  activating.value = true
  licenseApi.activate(licenseKeyForm.license_key).then((result) => {
    if (result.status === 'active') {
      alert.success(result.message)
      return invalidateFlags().then(() => invalidateUser())
    } else {
      alert.error(result.message)
    }
  }).catch((error) => {
    alert.error(error?.data?.message || 'Failed to activate license. Please check your key.')
  }).finally(() => {
    activating.value = false
  })
}
</script>
