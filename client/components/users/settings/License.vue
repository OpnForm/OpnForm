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
          v-if="!hasLicense"
          :label="(isExpired || isGracePeriod) ? 'Renew License' : 'Purchase License'"
          icon="i-heroicons-shopping-cart"
          @click="openPurchase"
        />
      </div>
    </div>

    <div v-if="hasLicense" class="space-y-4">
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
          :to="{ name: 'redirect-billing-portal' }"
          target="_blank"
        >
          Manage Subscription
        </UButton>
      </div>
    </div>

    <div v-else class="space-y-4">
      <div class="rounded-lg border p-4 sm:p-5 space-y-4" :class="isExpired ? 'border-red-200 bg-red-50/40' : 'border-neutral-200 bg-white'">
        <div class="flex items-start gap-3">
          <div
            class="mt-0.5 flex h-8 w-8 items-center justify-center rounded-full"
            :class="isExpired ? 'bg-red-100 text-red-600' : 'bg-neutral-100 text-neutral-500'"
          >
            <Icon
              :name="isExpired ? 'i-heroicons-exclamation-triangle' : 'i-heroicons-key'"
              class="h-5 w-5"
            />
          </div>
          <div>
            <p class="text-sm font-semibold text-neutral-900">
              {{ isExpired ? 'License Expired' : 'No Active License' }}
            </p>
            <p class="mt-1 text-sm text-neutral-600">
              {{ isExpired ? 'Enterprise features are currently disabled. Renew or activate a valid license key to restore access.' : 'Enter your license key below to enable Enterprise features for this self-hosted instance.' }}
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
  isGracePeriod,
  isExpired,
} = useInstanceLicense()

const activating = ref(false)
const licenseKeyForm = useForm({
  license_key: '',
})

const openPurchase = () => {
  openSubscriptionModal({ plan: 'self_hosted' })
}

const activateLicense = async () => {
  activating.value = true
  try {
    const result = await licenseApi.activate(licenseKeyForm.license_key)
    if (result.status === 'active') {
      alert.success(result.message)
      await invalidateFlags()
      await invalidateUser()
    } else {
      alert.error(result.message)
    }
  } catch (error) {
    alert.error(error?.data?.message || 'Failed to activate license. Please check your key.')
  } finally {
    activating.value = false
  }
}
</script>
