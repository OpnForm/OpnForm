import { billingApi } from '~/api'

export function useStripeCheckout() {
  const router = useRouter()
  const route = useRoute()
  const { isAuthenticated } = useIsAuthenticated()

  const loadingPlan = ref(null)

  const isLoading = computed(() => loadingPlan.value !== null)

  const isPlanLoading = (plan) => loadingPlan.value === plan

  const startCheckout = async (plan, options = {}) => {
    const {
      yearly = true,
      currency = 'usd',
      closeModal = null,
      redirectToRegister = true,
      trialDuration = route.query.trial_duration ?? null,
      bypassBeforeUnload = true,
    } = options

    if (!plan) {
      throw new Error('Missing plan for checkout')
    }

    if (!isAuthenticated.value) {
      if (typeof closeModal === 'function') {
        closeModal()
      }

      if (redirectToRegister) {
        await router.push({ name: 'register' })
      }
      return null
    }

    loadingPlan.value = plan
    const previousBeforeUnload = import.meta.client ? window.onbeforeunload : null

    try {
      const params = { currency }
      if (trialDuration) {
        params.trial_duration = trialDuration
        useAmplitude().logEvent('extended_trial_used', { duration: trialDuration })
      }

      const subscription = yearly ? 'yearly' : 'monthly'
      const { checkout_url } = await billingApi.getCheckoutUrl(
        plan,
        subscription,
        'with-trial',
        { params }
      )

      if (!checkout_url) {
        throw new Error('No checkout URL returned')
      }

      if (import.meta.client && bypassBeforeUnload) {
        window.onbeforeunload = null
      }

      window.location.href = checkout_url
      return checkout_url
    } catch (error) {
      if (import.meta.client && bypassBeforeUnload) {
        window.onbeforeunload = previousBeforeUnload
      }

      loadingPlan.value = null
      useAlert().error(error.response?._data?.message || 'Unable to start checkout process. Please try again or contact support.')
      throw error
    }
  }

  return {
    loadingPlan: readonly(loadingPlan),
    isLoading,
    isPlanLoading,
    startCheckout,
  }
}
