import { computed, reactive, readonly } from 'vue'
import { useI18n } from '#imports'
import { formsApi } from '~/api'

/**
 * Creates a Stripe elements instance with state management
 * @param {String} initialAccountId - Optional account ID to initialize with
 * @returns {Object} Stripe elements API with state and methods
 */
export const createStripeElements = (initialAccountId = null) => {
  // Ensure we're on the client side
  if (import.meta.server) {
    console.warn('Stripe Elements can only be initialized in the browser')
    return null
  }

  // Initialize i18n at the top level
  const { t } = useI18n()

  // Use reactive for the state to ensure changes propagate
  const state = reactive({
    // Loading states
    isLoadingAccount: false,
    hasAccountLoadingError: false,
    isStripeInstanceReady: false,
    isCardElementReady: false,
    
    // Core Stripe objects
    stripe: null,
    elements: null,
    card: null,
    
    // Form data
    cardHolderName: '',
    cardHolderEmail: '',
    
    // Account & payment state
    stripeAccountId: initialAccountId, // Initialize with provided account ID
    intentId: null,
    showPreviewMessage: false,
    error: null
  })

  // Reset state on initialization
  state.stripe = null
  state.elements = null
  state.card = null
  state.isStripeInstanceReady = false
  state.isCardElementReady = false
  state.error = null

  // Computed properties
  const isReadyForPayment = computed(() => {
    return state.isStripeInstanceReady && 
           state.isCardElementReady && 
           state.stripeAccountId
  })

  const isCardPopulated = computed(() => {
    return state.card && !state.card._empty
  })

  /**
   * Resets the Stripe state to its initial values
   */
  const resetStripeState = () => {
    state.isLoadingAccount = false
    state.hasAccountLoadingError = false
    state.isStripeInstanceReady = false
    state.isCardElementReady = false
    state.stripe = null
    state.elements = null
    state.card = null
    state.stripeAccountId = null
    state.intentId = null
    state.showPreviewMessage = false
    state.error = null
  }

  /**
   * Prepares the Stripe state by fetching account details
   * @param {String} formSlug - Form slug
   * @param {String|Number} providerId - OAuth provider ID
   * @param {Boolean} isEditorPreview - Whether this is in editor preview mode
   * @returns {Promise<Object>} Result object with success and message
   */
  const prepareStripeState = async (formSlug, providerId, isEditorPreview = false) => {
    if (!formSlug || !providerId) {
      resetStripeState()
      return { success: false, message: 'Missing form slug or OAuth provider ID' }
    }
    
    // Always ensure provider ID is a string
    const providerIdStr = String(providerId)
    
    resetStripeState()
    state.isLoadingAccount = true
    
    try {
      console.debug('[useStripeElements] Preparing Stripe state for:', { formSlug, providerId: providerIdStr, isEditorPreview })
      
      // Construct fetch options, adding providerId only for editor preview
      const fetchOptions = {}
      if (isEditorPreview) {
        fetchOptions.query = { oauth_provider_id: providerIdStr }
      }
      
      const response = await formsApi.stripe.getAccount(formSlug, fetchOptions)
      console.debug('[useStripeElements] Got account response:', response)

      if (response?.type === 'success' && response?.stripeAccount) {
        // Ensure account ID is stored as string
        state.stripeAccountId = String(response.stripeAccount)
        state.isLoadingAccount = false
        
        // If card is already set, mark card element as ready
        if (state.card && state.stripe) {
          state.isCardElementReady = true
        }
        
        return { success: true, accountId: String(response.stripeAccount) }
      } else {
        state.hasAccountLoadingError = true
        state.isLoadingAccount = false
        
        if (response?.message?.includes('save the form and try again')) {
          state.showPreviewMessage = true
        }
        
        state.errorMessage = response?.message || 'Failed to get account details'
        return { 
          success: false, 
          message: state.errorMessage,
          requiresSave: state.showPreviewMessage
        }
      }
    } catch (error) {
      console.error('[useStripeElements] Error preparing state:', error)
      state.hasAccountLoadingError = true
      state.isLoadingAccount = false
      
      const message = error?.data?.message || 'Payment setup error'
      
      if (message.includes('save the form and try again')) {
        state.showPreviewMessage = true
      }
      
      state.errorMessage = message
      return { 
        success: false, 
        message: state.errorMessage,
        requiresSave: state.showPreviewMessage
      }
    }
  }

  /**
   * Sets the Stripe instance in the state
   */
  const setStripeInstance = (instance) => {
    console.debug('[useStripeElements] Setting Stripe instance:', {
      hasInstance: !!instance
    })

    try {
      if (!instance) {
        console.warn('[useStripeElements] No Stripe instance provided')
        return
      }

      const isValidStripeInstance = instance && 
        typeof instance === 'object' && 
        typeof instance.confirmCardPayment === 'function' &&
        typeof instance.createToken === 'function'
      
      if (isValidStripeInstance) {
        console.debug('[useStripeElements] Valid Stripe instance detected')
        state.stripe = instance
        state.isStripeInstanceReady = true

        // If we have all required components, ensure card element is ready
        if (state.card && state.stripeAccountId) {
          console.debug('[useStripeElements] Card element ready with account')
          state.isCardElementReady = true
        }
      } else {
        console.warn('[useStripeElements] Invalid Stripe instance provided')
        state.isStripeInstanceReady = false
      }
    } catch (error) {
      console.error('[useStripeElements] Error setting Stripe instance:', error)
    }
  }

  /**
   * Sets the Elements instance in the state
   */
  const setElementsInstance = (elementsInstance) => {
    if (elementsInstance) {
      state.elements = elementsInstance
    }
  }
  
  /**
   * Sets the Card Element in the state
   */
  const setCardElement = (cardElement) => {
    if (cardElement) {
      state.card = cardElement
      state.isCardElementReady = true
    } else {
      state.card = null
      state.isCardElementReady = false
    }
  }
  
  /**
   * Sets the billing details in the state
   */
  const setBillingDetails = ({ name, email }) => {
    if (name !== undefined) state.cardHolderName = name
    if (email !== undefined) state.cardHolderEmail = email
  }

  /**
   * Processes a payment using the Stripe API
   */
  const processPayment = async (formSlug, isRequired = true) => {
    if (!isReadyForPayment.value) {
      return { 
        success: false,
        error: { message: t('forms.payment.errors.systemNotReady') } 
      }
    }

    if (!state.stripe) {
      return {
        success: false,
        error: { message: t('forms.payment.errors.misconfigured') }
      }
    }
    
    if (!state.card) {
      return {
        success: false,
        error: { message: t('forms.payment.errors.notFullyReady') }
      }
    }
    
    if (isRequired && state.card._empty) {
      return { 
        success: false,
        error: { message: t('forms.payment.errors.paymentRequired') } 
      }
    }
    
    if (isRequired || !state.card._empty) {
      if (!state.cardHolderName) {
        return { 
          success: false,
          error: { message: t('forms.payment.errors.nameRequired') } 
        }
      }
      
      if (!state.cardHolderEmail) {
        return { 
          success: false,
          error: { message: t('forms.payment.errors.emailRequired') } 
        }
      }
      
      if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(state.cardHolderEmail)) {
        return { 
          success: false,
          error: { message: t('forms.payment.errors.invalidEmail') } 
        }
      }
    }

    try {
      const responseIntent = await formsApi.stripe.createPaymentIntent(formSlug)
      
      if (responseIntent?.type === 'success') {
        const intentSecret = responseIntent?.intent?.secret
        
        const result = await state.stripe.confirmCardPayment(intentSecret, {
          payment_method: {
            card: state.card,
            billing_details: {
              name: state.cardHolderName,
              email: state.cardHolderEmail
            },
          },
          receipt_email: state.cardHolderEmail,
        })
        
        if (result?.paymentIntent?.status === 'succeeded') {
          state.intentId = result.paymentIntent.id
        }
        
        return {
          success: result?.paymentIntent?.status === 'succeeded',
          ...result
        }
      } else {
        return { 
          success: false,
          error: { message: responseIntent?.message || t('forms.payment.errors.failedIntent') } 
        }
      }
    } catch (error) {
      const errorMessage = error?.message || t('forms.payment.errors.processingFailed')
      const errorType = error?.type || 'unknown'
      const errorCode = error?.code || 'unknown'
      
      return { 
        success: false,
        error: { 
          message: errorMessage,
          type: errorType,
          code: errorCode
        } 
      }
    }
  }

  /**
   * Sets the intent ID
   * @param {String} intentId - The payment intent ID
   */
  const setIntentId = (intentId) => {
    console.debug('[useStripeElements] Setting intentId:', intentId)
    if (intentId && typeof intentId === 'string' && intentId.startsWith('pi_')) {
      state.intentId = intentId
    }
  }

  /**
   * Sets the Stripe Account ID in the state
   * @param {String|Number} accountId - The Stripe account ID
   */
  const setAccountId = (accountId) => {
    const accountIdStr = accountId ? String(accountId) : null
    if (state.stripeAccountId !== accountIdStr) {
      resetStripeState()
      state.stripeAccountId = accountIdStr
    }
  }

  const stripeElements = {
    // Expose readonly state to prevent mutations outside of proper methods
    state: readonly(state),
    
    // Read-only computed values
    isReadyForPayment,
    isCardPopulated,
    
    // Methods
    resetStripeState,
    prepareStripeState,
    processPayment,
    setStripeInstance,
    setElementsInstance,
    setCardElement,
    setBillingDetails,
    setIntentId,
    setAccountId
  }

  return stripeElements
}