<template>
  <InputWrapper v-bind="inputWrapperProps">
    <template #label>
      <slot name="label" />
    </template>

    <div :class="ui.container({ class: props.ui?.slots?.container })">
      <div v-if="!oauthProviderId">
        <div class="space-y-4 mt-3">
          <div class="animate-pulse flex flex-col gap-3">
            <div class="h-6 bg-neutral-200 dark:bg-neutral-800 rounded-md" />
            <div class="h-6 bg-neutral-200 dark:bg-neutral-800 rounded-md" />
            <div class="h-6 bg-neutral-200 dark:bg-neutral-800 rounded-md" />
          </div>
          <p class="text-sm text-neutral-500 dark:text-neutral-400 text-center">
            Connect Stripe account to continue
          </p>
        </div>
      </div>
      <div
        v-else-if="showSuccessState"
        :class="ui.section({ class: props.ui?.slots?.section }) + ' p-4 text-center text-sm text-green-700 bg-green-100 dark:bg-green-900/50 dark:text-green-300 rounded-md'"
      >
        <div class="flex items-center justify-center gap-2">
          <Icon
            name="heroicons:check-circle"
            class="w-5 h-5"
          />
          <p>{{ $t('forms.payment.success') }}.</p>
        </div>
      </div>
      <template v-else>
        <div
          v-if="shouldShowPreviewMessage || (props.isAdminPreview && (!stripeState.stripeAccountId || !publishableKey || !isStripeJsLoaded))"
          :class="ui.section({ class: props.ui?.slots?.section }) + ' p-4 text-center text-sm text-blue-700 bg-blue-100 dark:bg-blue-900/50 dark:text-blue-300 rounded-md'"
        >
          <p v-if="shouldShowPreviewMessage">Please save the form to activate the payment preview.</p>
          <p v-else>
            Payment component configuration incomplete. 
            {{ !stripeState?.stripeAccountId ? 'Stripe account not connected': 'Stripe account connected' }}.
            {{ !publishableKey ? 'Missing Stripe publishable key.' : '' }}
            {{ !isStripeJsLoaded ? 'Stripe.js not loaded.' : '' }}
          </p>
          <p class="mt-2">The complete payment form will be visible to users when viewing the published form.</p>
        </div>
        <div
          v-else-if="stripeState && stripeState.isLoadingAccount"
          :class="ui.section({ class: props.ui?.slots?.section }) + ' flex justify-center'"
        >
          <Loader class="mx-auto h-6 w-6" />
        </div>
        <div
          v-else-if="stripeState && stripeState.hasAccountLoadingError"
          :class="ui.section({ class: props.ui?.slots?.section }) + ' p-4 text-center text-sm text-red-700 bg-red-100 dark:bg-red-900/50 dark:text-red-300 rounded-md'"
        >
          <p>{{ stripeState.errorMessage || 'Failed to load payment configuration' }}</p>
        </div>
        <div
          v-else-if="stripeState && stripeState.stripeAccountId && isStripeJsLoaded && publishableKey"
          :class="ui.section({ class: props.ui?.slots?.section })"
        >
          <div :class="ui.amountBar({ class: props.ui?.slots?.amountBar })">
            <span class="text-sm font-medium text-neutral-700 dark:text-neutral-300">{{ $t('forms.payment.amount_to_pay') }}</span>
            <span class="text-sm font-medium text-neutral-900 dark:text-neutral-100">{{ currencySymbol }}{{ amount }}</span>
          </div>
          <StripeElements
            ref="stripeElementsRef"
            :stripe-key="publishableKey"
            :stripe-account="String(stripeState.stripeAccountId)"
            :instance-options="{ stripeAccount: String(stripeState.stripeAccountId) }"
            :elements-options="{ locale: props.locale }"
            @ready="onStripeReady"
            @error="onStripeError"
          >
            <template #default="{ elements }">
              <div :class="ui.stack({ class: props.ui?.slots?.stack })">
                <div :class="[ui.card({ class: props.ui?.slots?.card }), isCardFocused && !hasError ? 'ring-2 ring-form border-transparent' : '']">
                  <StripeElement
                    v-if="elements"
                    ref="card"
                    type="card"
                    :elements="elements"
                    :options="cardOptions"
                    @ready="onCardReady"
                    @focus="onCardFocus" 
                    @blur="onCardBlur"
                  />
                </div>
                <TextInput
                  v-model="cardHolderName"
                  name="cardholder_name"
                  :placeholder="$t('forms.payment.name_on_card')"
                  class="w-full"
                  :disabled="disabled"
                  wrapper-class="my-0"
                  :color="color"
                />
                <TextInput
                  v-model="cardHolderEmail"
                  name="billing_email"
                  :placeholder="$t('forms.payment.billing_email')"
                  class="w-full"
                  :disabled="disabled"
                  wrapper-class="my-0"
                  :color="color"
                />
              </div>
            </template>
          </StripeElements>
        </div>
        <div v-else>
          <div v-if="props.isAdminPreview" class="my-4 p-4 text-center text-sm text-blue-700 bg-blue-100 dark:bg-blue-900/50 dark:text-blue-300 rounded-md">
            <p>Payment component initializing. {{ !!stripeState?.stripeAccountId ? 'Stripe account connected': 'No Stripe account connected' }}.</p>
            <p class="mt-2">The payment form will be visible to users when viewing the published form.</p>
            <p v-if="!publishableKey" class="mt-2 text-red-500">Missing Stripe publishable key in configuration.</p>
            <p v-if="!stripeState?.stripeAccountId" class="mt-2 text-red-500">Missing Stripe account connection. ID: {{ props.oauthProviderId }}</p>
          </div>
          <div v-else class="flex flex-col items-center justify-center py-4">
            <Loader class="mx-auto h-6 w-6" />
            <p class="text-sm text-neutral-500 mt-2">Initializing payment system...</p>
          </div>
        </div>
      </template>
    </div>

    <template #help>
      <slot name="help" />
    </template>
    <template #error>
      <!-- If we have a specific error for this block, show it -->
      <div v-if="props.error" class="text-sm text-red-500 mt-1">
        {{ props.error }}
      </div>
      <!-- Otherwise, show the default error slot -->
      <slot v-else name="error" />
    </template>
  </InputWrapper>
</template>

<script setup>
import { ref, computed, watch, onMounted, nextTick } from 'vue'
import { inputProps, useFormInput } from '../useFormInput.js'
import { loadStripe } from '@stripe/stripe-js'
import { StripeElements, StripeElement } from 'vue-stripe-js'
import stripeCurrencies from "~/data/stripe_currencies.json"
import { useAlert } from '~/composables/useAlert'
import { useFeatureFlag } from '~/composables/useFeatureFlag'
import { paymentInputTheme } from '~/lib/forms/themes/payment-input.theme.js'

const props = defineProps({
  ...inputProps,
  direction: { type: String, default: 'ltr' },
  currency: { type: String, default: 'USD' },
  amount: { type: Number, default: 0 },
  oauthProviderId: { type: [String, Number], default: null },
  isAdminPreview: { type: Boolean, default: false },
  color: { type: String, default: '#000000' },
  isDark: { type: Boolean, default: false },
  paymentData: { type: Object, default: null },
  prefillName: { type: String, default: '' },
  prefillEmail: { type: String, default: '' }
})

const emit = defineEmits([])
const { compVal, hasError, inputWrapperProps, ui } = useFormInput(props, { emit }, {
  variants: paymentInputTheme
})

const route = useRoute()
const alert = useAlert()

const publishableKey = computed(() => {
  return useFeatureFlag('billing.stripe_publishable_key', '')
})

const card = ref(null)
const stripeElementsRef = ref(null)
const cardHolderName = ref(props.prefillName || '')
const cardHolderEmail = ref(props.prefillEmail || '')
const isCardFocused = ref(false)
const isStripeJsLoaded = ref(false)

// Watch for prefill changes to update card holder details
watch(() => props.prefillName, (newVal) => {
  const resolved = newVal ?? ''
  if (resolved !== cardHolderName.value) {
    cardHolderName.value = resolved
  }
})
watch(() => props.prefillEmail, (newVal) => {
  const resolved = newVal ?? ''
  if (resolved !== cardHolderEmail.value) {
    cardHolderEmail.value = resolved
  }
})

// Get Stripe elements from paymentData
const stripeElements = computed(() => props.paymentData?.stripeElements)
const stripeState = computed(() => stripeElements.value?.state || {})
const setStripeInstance = computed(() => stripeElements.value?.setStripeInstance)
const setElementsInstance = computed(() => stripeElements.value?.setElementsInstance)
const setCardElement = computed(() => stripeElements.value?.setCardElement)
const setBillingDetails = computed(() => stripeElements.value?.setBillingDetails)
const prepareStripeState = computed(() => stripeElements.value?.prepareStripeState)

// Computed to determine if we should show success state
const showSuccessState = computed(() => {
  return stripeState.value?.intentId || (compVal.value && isPaymentIntentId(compVal.value))
})

// Computed to determine if we should always show preview message in editor
const shouldShowPreviewMessage = computed(() => {
  return props.isAdminPreview && stripeState.value?.showPreviewMessage
})

// Helper function to check if a string looks like a Stripe payment intent ID
const isPaymentIntentId = (value) => {
  return typeof value === 'string' && value.startsWith('pi_')
}

// Initialize Stripe.js if needed
onMounted(async () => {
  try {
    console.debug('[PaymentInput] Mounting with:', {
      oauthProviderId: props.oauthProviderId,
      hasPaymentData: !!props.paymentData,
      publishableKey: publishableKey.value,
      stripeElementsInstance: !!stripeElements.value
    })

    // Initialize Stripe.js globally first if needed
    if (typeof window !== 'undefined' && !window.Stripe && publishableKey.value) {
      console.debug('[PaymentInput] Loading Stripe.js with key:', publishableKey.value)
      await loadStripe(publishableKey.value)
      isStripeJsLoaded.value = true
    } else if (typeof window !== 'undefined' && window.Stripe) {
      isStripeJsLoaded.value = true
    }
    console.debug('[PaymentInput] Stripe.js loaded status:', isStripeJsLoaded.value)

    // Skip initialization if missing essential data
    if (!props.oauthProviderId || !props.paymentData || !publishableKey.value) {
      console.debug('[PaymentInput] Skipping initialization - missing requirements:', { 
        oauthProviderId: props.oauthProviderId,
        paymentData: !!props.paymentData,
        publishableKey: !!publishableKey.value
      })
      
      // Set error state if publishable key is missing
      if (!publishableKey.value && stripeState.value) {
        stripeState.value.hasAccountLoadingError = true
        stripeState.value.errorMessage = 'Missing Stripe configuration. Please check your settings.'
      }
      return
    }

    // If compVal already contains a payment intent ID, sync it to stripeState
    if (compVal.value && isPaymentIntentId(compVal.value) && stripeState.value) {
      console.debug('[PaymentInput] Syncing existing payment intent:', compVal.value)
      stripeState.value.intentId = compVal.value
    }

    // Fetch account details from the API, even in preview mode
    const slug = formSlug.value
    if (slug && props.oauthProviderId && prepareStripeState.value) {
      console.debug('[PaymentInput] Preparing Stripe state with:', { 
        slug, 
        oauthProviderId: props.oauthProviderId,
        isAdminPreview: props.isAdminPreview
      })
      const result = await prepareStripeState.value(slug, props.oauthProviderId, props.isAdminPreview)
      console.debug('[PaymentInput] Stripe state preparation result:', result)
      
      if (!result.success && result.message && !result.requiresSave) {
        // Show error only if it's not the "Save the form" message
        alert.error(result.message)
      }
    }
  } catch (error) {
    console.error('[PaymentInput] Stripe initialization error:', error)
    if (stripeState.value) {
      stripeState.value.hasAccountLoadingError = true
      stripeState.value.errorMessage = 'Failed to initialize Stripe. Please refresh and try again.'
    }
    alert.error('Failed to initialize Stripe. Please refresh and try again.')
  }
})

// Watch for provider ID changes
watch(() => props.oauthProviderId, async (newVal, oldVal) => {
  if (newVal && newVal !== oldVal && prepareStripeState.value) {
    const slug = formSlug.value
    if (slug) {
      await prepareStripeState.value(slug, newVal, props.isAdminPreview)
    }
  }
})

// Update onStripeReady to use the computed methods
const onStripeReady = ({ stripe, elements }) => {
  console.debug('[PaymentInput] onStripeReady called with:', { 
    hasStripe: !!stripe,
    hasElements: !!elements,
    setStripeInstance: !!setStripeInstance.value,
    setElementsInstance: !!setElementsInstance.value
  })

  if (!stripe) {
    console.warn('[PaymentInput] No Stripe instance in onStripeReady')
    return
  }
  
  if (setStripeInstance.value) {
    console.debug('[PaymentInput] Setting Stripe instance')
    setStripeInstance.value(stripe)
  } else {
    console.warn('[PaymentInput] No setStripeInstance method available')
  }
  
  if (elements && setElementsInstance.value) {
    console.debug('[PaymentInput] Setting Elements instance')
    setElementsInstance.value(elements)
  } else {
    console.warn('[PaymentInput] Missing elements or setElementsInstance')
  }
}

const onStripeError = (error) => {
  console.error('[PaymentInput] Stripe initialization error:', error)
  const errorMessage = error?.message || 'Failed to load payment component'
  
  alert.error('Failed to load payment component. ' + errorMessage)
  
  if (stripeState.value) {
    stripeState.value.hasAccountLoadingError = true
    stripeState.value.errorMessage = errorMessage + '. Please check configuration or refresh.'
  }
}

// Card focus/blur event handlers
const onCardFocus = () => {
  isCardFocused.value = true
}

const onCardBlur = () => {
  isCardFocused.value = false
}

const onCardReady = (_element) => {
  console.debug('[PaymentInput] Card ready:', {
    hasCardRef: !!card.value,
    hasStripeElement: !!card.value?.stripeElement,
    hasSetCardElement: !!setCardElement.value
  })

  if (card.value?.stripeElement && setCardElement.value) {
    console.debug('[PaymentInput] Setting card element')
    setCardElement.value(card.value.stripeElement)
  } else {
    console.warn('[PaymentInput] Cannot set card element - missing dependencies')
  }
}

// Billing details
watch(cardHolderName, (newValue) => {
  if (setBillingDetails.value) {
    setBillingDetails.value({ name: newValue })
  }
})

watch(cardHolderEmail, (newValue) => {
  if (setBillingDetails.value) {
    setBillingDetails.value({ email: newValue })
  }
})

// Payment intent sync
watch(() => stripeState.value?.intentId, (newValue) => {
  if (newValue) compVal.value = newValue
})

watch(compVal, (newValue) => {
  if (newValue && stripeState.value && newValue !== stripeState.value.intentId) {
    stripeState.value.intentId = newValue
  }
}, { immediate: true })

// Direction and locale handling
watch(() => props.direction, async () => {
  await resetCard()
})

watch(() => props.locale, async () => {
  await resetCard()
})

// Computed properties
const currencySymbol = computed(() => {
  return stripeCurrencies.find(item => item.code === props.currency)?.symbol
})

const cardOptions = computed(() => {
  const darkPlaceholderColor = '#6B7280'
  const lightPlaceholderColor = '#9CA3AF'
  
  return {
    hidePostalCode: true,
    disableLink: true,
    disabled: props.disabled || false,
    style: {
      base: {
        iconColor: props.color,
        color: props.isDark ? '#D1D5DB' : '#374151',
        fontSmoothing: 'antialiased',
        fontSize: '16px',
        '::placeholder': {
          color: props.isDark ? darkPlaceholderColor : lightPlaceholderColor
        }
      },
      invalid: {
        iconColor: '#df1b41',
        color: '#df1b41'
      }
    }
  }
})

const formSlug = computed(() => {
  if (route.params && route.params.slug) {
    return route.params.slug
  }
  return null
})

// Reset card element
const resetCard = async () => {
  if (card.value?.stripeElement) {
    card.value.stripeElement.unmount()
    await nextTick()
    
    if (stripeElementsRef.value?.elements) {
      card.value.stripeElement.mount(stripeElementsRef.value.elements)
      if (setCardElement.value) {
        setCardElement.value(card.value.stripeElement)
      }
    } else {
      console.error('Cannot remount card, Stripe Elements instance not found.')
    }
  }
}

// Add watcher to check when stripeElementsRef becomes available for fallback access
watch(() => stripeElementsRef.value, async (newRef) => {
  if (newRef) {
    console.debug('[PaymentInput] StripeElementsRef updated:', {
      hasInstance: !!newRef.instance,
      hasElements: !!newRef.elements
    })
    
    // If @ready event hasn't fired, try accessing the instance directly
    if (newRef.instance && setStripeInstance.value && !stripeState.value?.isStripeInstanceReady) {
      console.debug('[PaymentInput] Setting Stripe instance from ref')
      setStripeInstance.value(newRef.instance)
    }
    
    if (newRef.elements && setElementsInstance.value) {
      console.debug('[PaymentInput] Setting Elements instance from ref')
      setElementsInstance.value(newRef.elements)
    }
  }
}, { immediate: true })
</script>
