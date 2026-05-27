<template>
  <div class="flex flex-col min-h-screen">
    <div
      class="w-full md:max-w-3xl md:mx-auto px-4 mb-10 md:pb-20 md:pt-16 text-center flex-grow"
    >
      <h1 class="text-4xl font-semibold">
        Thank you!
      </h1>
      <h4 class="text-xl mt-6">
        We're checking the status of your subscription please wait a moment...
      </h4>
      <div class="text-center">
        <Loader class="h-6 w-6 text-blue-500 mx-auto mt-20" />
      </div>
    </div>
    <open-form-footer />
  </div>
</template>


<script setup>
import { authApi } from "~/api"

definePageMeta({
  middleware: 'auth'
})

useOpnSeoMeta({
  title: 'Subscription Success'
})

const confetti = useConfetti()
const auth = useAuth()
const workspaces = useWorkspaces()
const alert = useAlert()
const amplitude = useAmplitude()
const crisp = useCrisp()
const gtm = useGtm()
const { data: user } = auth.user()

const interval = ref(null)
const hasHandledSuccess = ref(false)

const handleSubscribed = async () => {
  if (hasHandledSuccess.value || !user.value?.is_subscribed) return

  hasHandledSuccess.value = true

  try {
    const eventData = {
      plan: user.value?.plan_tier || 'pro'
    }
    amplitude.logEvent('subscribed', eventData)
    crisp.pushEvent('subscribed', eventData)
    gtm.trackEvent({ event: 'subscribed', ...eventData })
    if (import.meta.client && window.rewardful) {
      window.rewardful('convert', { email: user.value.email })
    }
  } catch (error) {
    console.error('Failed to register subscription event', error)
  }

  workspaces.invalidateAll()

  alert.success('Your subscription is now active.')
  confetti.play()
  await navigateTo({ name: 'home' })
}

const checkSubscription = () => {
  // Fetch the user.
  return authApi.user.get().then(() => {
    auth.invalidateUser()
    handleSubscribed()
  }).catch((error) => {
    console.error(error)
    clearInterval(interval.value)
  })
}

onMounted(() => {
  handleSubscribed()
  interval.value = setInterval(() => checkSubscription(), 5000)
})

onBeforeUnmount(() => {
  clearInterval(interval.value)
})
</script>
