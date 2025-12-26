<template>
  <UModal
    v-model:open="isModalOpen"
    :close="false"
    :dismissible="false"
    :ui="{ content: 'sm:max-w-2xl' }"
  >
    <template #body>
      <div class="text-center py-2">
        <Transition
          appear
          enter-active-class="transition-all duration-500 ease-out"
          enter-from-class="opacity-0 scale-75 -translate-y-4"
          enter-to-class="opacity-100 scale-100 translate-y-0"
        >
          <div
            class="inline-flex items-center gap-1.5 bg-gradient-to-r from-green-100 to-emerald-100 text-green-700 px-4 py-1.5 rounded-full text-xs font-semibold mb-6 shadow-sm border border-green-200/50 animate-pulse-subtle"
          >
            <Icon name="mdi:gift" class="w-4 h-4 animate-bounce-subtle" />
            <span>Special Offer</span>
          </div>
        </Transition>

        <Transition
          appear
          enter-active-class="transition-all duration-600 ease-out delay-100"
          enter-from-class="opacity-0 translate-y-4"
          enter-to-class="opacity-100 translate-y-0"
        >
          <h2
            class="text-3xl font-bold text-neutral-900 mb-3"
          >
            Upgrade to <span class="text-primary bg-gradient-to-r bg-clip-text">Yearly</span> and Save Big
          </h2>
        </Transition>

        <Transition
          appear
          enter-active-class="transition-all duration-600 ease-out delay-200"
          enter-from-class="opacity-0 translate-y-4"
          enter-to-class="opacity-100 translate-y-0"
        >
          <p
            class="text-sm text-neutral-600 mb-8"
          >
            Get <span class="text-primary font-bold">2 months for free</span> when you switch to annual billing
          </p>
        </Transition>

        <Transition
          appear
          enter-active-class="transition-all duration-700 ease-out delay-300"
          enter-from-class="opacity-0 scale-95"
          enter-to-class="opacity-100 scale-100"
        >
          <div
            class="flex items-center justify-center gap-8 mb-8 pb-8 border-b border-neutral-200"
          >
            <div class="text-center transform transition-all duration-300 hover:scale-105">
              <p class="text-neutral-500 mb-2 text-sm">Monthly plan</p>
              <p class="text-3xl font-semibold text-neutral-900">$19<span class="text-lg">/month</span></p>
            </div>

            <div class="h-16 w-px bg-gradient-to-b from-transparent via-neutral-200 to-transparent"></div>
            
            <div class="text-center transform transition-all duration-300 hover:scale-105 relative">
              <div class="absolute -top-4 -right-2 bg-green-500 text-white text-[10px] font-bold px-2 py-0.5 rounded-full animate-bounce-subtle">
                BEST
              </div>
              <p class="text-primary mb-2 text-sm font-medium">Yearly plan</p>
              <p class="text-4xl font-bold text-primary bg-gradient-to-r bg-clip-text">$16<span class="text-lg">/month</span></p>
              <p class="text-xs text-neutral-500 mt-1">$192 billed annually</p>
            </div>
          </div>
        </Transition>

        <div class="w-1/2 mx-auto">
          <div class="space-y-3 mb-8 text-left max-w-md mx-auto">
            <TransitionGroup
              appear
              enter-active-class="transition-all duration-500 ease-out"
              enter-from-class="opacity-0 translate-x-[-20px]"
              enter-to-class="opacity-100 translate-x-0"
              tag="div"
            >
              <div
                v-for="(benefit, index) in benefits"
                :key="benefit"
                :style="{ transitionDelay: `${400 + index * 100}ms` }"
                class="flex items-start gap-3 group"
              >
                <div class="relative">
                  <Icon
                    name="heroicons:check-circle"
                    class="w-6 h-6 text-primary flex-shrink-0 mt-0.5 transform transition-all duration-300 group-hover:scale-110"
                  />
                  <div class="absolute inset-0 bg-primary/20 rounded-full blur-md opacity-0 group-hover:opacity-50 transition-opacity duration-300"></div>
                </div>
                <span class="text-sm text-neutral-700 font-medium">{{ benefit }}</span>
              </div>
            </TransitionGroup>
          </div>

          <Transition
            appear
            enter-active-class="transition-all duration-600 ease-out delay-700"
            enter-from-class="opacity-0 translate-y-6 scale-95"
            enter-to-class="opacity-100 translate-y-0 scale-100"
          >
            <div class="mb-2">
              <UButton
                color="primary"
                size="lg"
                class="transform transition-all duration-300 hover:scale-105 hover:shadow-lg shadow-md"
                trailing-icon="i-heroicons-arrow-right"
                :loading="loading"
                @click="handleUpgrade"
              >
                Upgrade to Yearly Now
              </UButton>
            </div>
          </Transition>

          <Transition
            appear
            enter-active-class="transition-all duration-500 ease-out delay-800"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
          >
            <p
              class="text-xs text-neutral-500 mb-2"
            >
              By clicking, you'll be charged <span class="font-bold">$192 annually</span>.
            </p>
          </Transition>
        </div>
      </div>
    </template>
    <template #footer>
      <Transition
        appear
        enter-active-class="transition-all duration-400 ease-out delay-900"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
      >
        <div class="flex justify-end w-full">
          <UButton
            color="neutral"
            variant="link"
            class="underline transition-colors duration-200 hover:text-neutral-700"
            :loading="loading"
            @click="closeModal"
          >
            No thanks
          </UButton>
        </div>
      </Transition>
    </template>
  </UModal>
</template>

<script setup>
const amplitude = useAmplitude()
const auth = useAuth()
const { data: user } = auth.user()
const { current: workspace } = useCurrentWorkspace()

const alert = useAlert()
const loading = ref(false)
const upgradeForm = useForm({
  workspace_id: workspace.value?.id
})

// Storage key for tracking last shown date
const STORAGE_KEY = 'yearly_upgrade_modal_last_shown'

// Helper function to check if modal can be shown (once per month)
const canShowModal = () => {
  if (!import.meta.client) return false

  const lastShown = localStorage.getItem(STORAGE_KEY)
  if (!lastShown) return true // Never shown before
  
  const lastShownDate = new Date(lastShown)
  const now = new Date()
  const daysSinceLastShown = Math.floor((now - lastShownDate) / (1000 * 60 * 60 * 24))
  
  // Show if 30 days (1 month) have passed
  return daysSinceLastShown >= 30
}

// Helper function to save the current date when modal is shown
const markModalAsShown = () => {
  if (!import.meta.client) return
  localStorage.setItem(STORAGE_KEY, new Date().toISOString())
}

// Determine if modal should be shown
const isSelfHosted = computed(() => useFeatureFlag('self_hosted'))
const shouldShowModal = computed(() => {
  return import.meta.client && 
    !isSelfHosted.value && 
    workspace.value?.is_admin &&
    workspace.value?.is_pro && 
    !workspace.value?.is_yearly_plan &&
    canShowModal()
})

// Modal state - controlled internally
const isModalOpen = ref(false)

// Watch for when modal should be shown
watch(shouldShowModal, (newValue) => {
  if (newValue) {
    isModalOpen.value = true
    markModalAsShown() // Save the date when modal is shown
    amplitude.logEvent('yearly_upgrade_modal_viewed', {
      user_id: user.value?.id,
    })
  } else {
    isModalOpen.value = false
  }
}, { immediate: true })

const benefits = [
  'Save 20% compared to monthly billing',
  'Lock in current pricing for a full year',
  'Keep access to the same features'
]

const closeModal = () => {
  isModalOpen.value = false
}

const handleUpgrade = async () => {
  loading.value = true
  amplitude.logEvent('yearly_upgrade_button_clicked', {
    user_id: user.value?.id,
  })
  upgradeForm.post('/subscription/upgrade-to-yearly').then(async (response) => {
    alert.success(response.message)

    // Refetch the user
    await auth.invalidateUser()

    loading.value = false
    closeModal()
  }).catch((error) => {
    loading.value = false
    alert.error(error.data?.message || 'Failed to upgrade to yearly plan. Please try again later.')
  })
}
</script>

<style scoped>
@keyframes pulse-subtle {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.8;
  }
}

@keyframes bounce-subtle {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-3px);
  }
}

.animate-pulse-subtle {
  animation: pulse-subtle 2s ease-in-out infinite;
}

.animate-bounce-subtle {
  animation: bounce-subtle 2s ease-in-out infinite;
}
</style>