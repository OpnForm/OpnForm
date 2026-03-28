<template>
  <div class="flex flex-col items-center justify-center min-h-screen gap-4">
    <Loader class="w-8 h-8 text-blue-500" />
    <p class="text-neutral-500">
      Preparing your checkout...
    </p>
  </div>
</template>

<script setup>
import { billingApi } from "~/api"

definePageMeta({
  middleware: 'auth'
})

const route = useRoute()

onMounted(async () => {
  const { plan, yearly, trial_duration, currency, name, email } = route.query
  
  if (!plan) {
    useAlert().error('Missing plan information')
    navigateTo({ name: 'pricing' })
    return
  }

  try {
    // Update customer details if provided
    if (name && email) {
      try {
        await billingApi.updateCustomerDetails({ name, email })
      } catch {
        useAlert().error('Failed to update customer details, but proceeding with checkout')
      }
    }

    // Get checkout URL
    const params = { trial_duration, currency }
    const subscription = yearly === 'true' ? 'yearly' : 'monthly'
    const { checkout_url } = await billingApi.getCheckoutUrl(
      plan, 
      subscription, 
      'with-trial', 
      { params }
    )
    
    if (!checkout_url) {
      throw new Error('No checkout URL returned')
    }

    // Log trial usage if applicable
    if (trial_duration) {
      useAmplitude().logEvent('extended_trial_used', { duration: trial_duration })
    }
    
    window.location.href = checkout_url
  } catch {
    useAlert().error('Unable to start checkout process. Please try again or contact support.')
    setTimeout(() => {
      navigateTo({ name: 'pricing' })
    }, 2000)
  }
})
</script> 