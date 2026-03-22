<template>
  <div class="flex flex-col items-center justify-center min-h-screen gap-4">
    <Loader class="w-8 h-8 text-blue-500" />
    <p class="text-neutral-500">
      Preparing your checkout...
    </p>
  </div>
</template>

<script setup>
definePageMeta({
  middleware: 'auth'
})

const route = useRoute()
const { startCheckout } = useStripeCheckout()

onMounted(async () => {
  const { plan, yearly, trial_duration, currency } = route.query
  
  if (!plan) {
    useAlert().error('Missing plan information')
    navigateTo({ name: 'pricing' })
    return
  }

  try {
    await startCheckout(plan, {
      yearly: yearly === 'true',
      trialDuration: trial_duration,
      currency: currency || 'usd',
      bypassBeforeUnload: false,
    })
  } catch {
    setTimeout(() => {
      navigateTo({ name: 'pricing' })
    }, 2000)
  }
})
</script> 
