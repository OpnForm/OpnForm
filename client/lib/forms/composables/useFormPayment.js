import { toValue, ref } from 'vue'
import { createStripeElements } from '~/composables/components/stripe/useStripeElements'
import { formsApi } from '~/api'
// Assume Stripe is loaded globally or via another mechanism if needed client-side
// For server-side/Nuxt API routes, import Stripe library properly.

/**
 * @fileoverview Composable for handling payment processing, currently focused on Stripe.
 */
export function useFormPayment(formConfig, form) {
  const stripeElements = ref(null)
  
  /**
   * Gets payment-related data for a specific payment block
   * @param {Object} paymentBlock - The payment field configuration
   * @returns {Object|null} Payment data including stripeElements or null if not applicable
   */
  const getPaymentData = (paymentBlock) => {
    if (!import.meta.client || !paymentBlock || paymentBlock.type !== 'payment') return null
    
    // Create Stripe elements if needed and this is a Stripe payment
    if (paymentBlock.provider === 'stripe' || !paymentBlock.provider) {
      // Ensure account ID is a string (Stripe.js requires a string)
      const accountId = paymentBlock.stripe_account_id ? String(paymentBlock.stripe_account_id) : null
      
      if (!stripeElements.value) {
        // Create the Stripe elements with the account ID
        stripeElements.value = createStripeElements(accountId)
      } else if (stripeElements.value && accountId) {
        // Update the account ID if the instance already exists
        // Use the proper setter method to avoid readonly errors
        if (typeof stripeElements.value.setAccountId === 'function') {
          stripeElements.value.setAccountId(accountId)
        }
      }
      
      return {
        stripeElements: stripeElements.value,
        oauthProviderId: accountId
      }
    }
    return null
  }

  /**
   * Creates a payment intent with the Stripe API using a POST request.
   * @param {Number} amount - The amount to charge in cents.
   * @param {String} currency - The currency code (e.g., 'usd').
   * @param {String} description - A description for the payment.
   * @returns {Promise<Object>} The result of creating the payment intent.
   */
  const _createPaymentIntent = async (_amount, _currency, _description) => {
    if (!import.meta.client) {
      return { success: false, error: 'Client-side only operation' }
    }

    try {
      
      // Get form slug from config
      const config = toValue(formConfig)
      const formSlug = config.slug
      
      if (!formSlug) {
        console.error('Missing form slug in config')
        return { success: false, error: 'Invalid form configuration' }
      }
      
      // Use formsApi for the payment intent creation
      const response = await formsApi.stripe.createPaymentIntent(formSlug, { submission_data: form.data() ?? [] })
      
      // Handle response structure with type and intent fields
      if (response?.type === 'success' && response?.intent?.secret) {
        return {
          success: true,
          client_secret: response.intent.secret,
          intentId: response.intent.id
        }
      }
      
      // Handle error response
      return { 
        success: false, 
        error: response?.message || 'Could not create payment: Invalid response from server' 
      }
    } catch (error) {
      return {
        success: false,
        error: error.message || 'Failed to create payment'
      }
    }
  }

  /**
   * Confirms the Stripe payment on the client-side using Stripe.js.
   * @param {String} clientSecret - The Stripe client secret.
   * @param {String} paymentBlockId - The ID of the payment block for setting errors.
   * @returns {Promise<Object>} The result of the payment confirmation.
   */
  const _confirmStripePayment = async (clientSecret, paymentBlockId) => {
    if (!import.meta.client) {
      return { success: false, error: 'Client-side only operation' }
    }
    
    if (!stripeElements.value || !stripeElements.value.state) {
      return { success: false, error: 'Stripe elements not initialized' }
    }
    
    const state = stripeElements.value.state
    const { stripe, card } = state
    
    if (!stripe || !card) {
      const error = 'Stripe or card element not available'
      if (paymentBlockId) form.errors.set(paymentBlockId, error)
      return { success: false, error }
    }
    
    try {      
      const result = await stripe.confirmCardPayment(clientSecret, {
        payment_method: {
          card: card,
          billing_details: {
            name: state.cardHolderName || '',
            email: state.cardHolderEmail || ''
          }
        },
        receipt_email: state.cardHolderEmail
      })
      
      // Check for errors
      if (result.error) {
        console.error('Payment confirmation error:', result.error)
        const errorMessage = result.error.message || 'Payment failed. Please try again.'
        
        if (paymentBlockId) {
          form.errors.set(paymentBlockId, errorMessage)
        }
        
        return { 
          success: false, 
          error: errorMessage,
          code: result.error.code,
          type: result.error.type 
        }
      }
      
      // Handle payment intent status
      if (result.paymentIntent) {
        const status = result.paymentIntent.status
        const intentId = result.paymentIntent.id
        
        // Store successful payment intent ID
        if (status === 'succeeded' || status === 'processing') {
          // Update form data with payment information
          const updateData = {}
          updateData['stripe_payment_intent_id'] = intentId
          updateData['payment_status'] = status
          
          // Update payment block field with intent ID
          if (paymentBlockId) {
            updateData[paymentBlockId] = intentId
          }
          
          // Update form data
          form.update(updateData)
          
          // Also update the Stripe state
          if (state.intentId !== intentId && stripeElements.value.setIntentId) {
            stripeElements.value.setIntentId(intentId)
          }
          
          return { 
            success: true, 
            paymentIntent: result.paymentIntent,
            status: status,
            intentId: intentId
          }
        } else {
          // Payment intent exists but status is not successful
          const failMessage = `Payment failed with status: ${status}`
          console.error(failMessage)
          
          if (paymentBlockId) {
            form.errors.set(paymentBlockId, failMessage)
          }
          
          return { success: false, error: failMessage }
        }
      }
      
      // If we get here, something unexpected happened
      const unexpectedError = 'Payment failed with an unexpected error'
      if (paymentBlockId) {
        form.errors.set(paymentBlockId, unexpectedError)
      }
      
      return { success: false, error: unexpectedError }
      
    } catch (error) {
      console.error('Payment confirmation error:', error)
      
      const errorMessage = error.message || 'Payment confirmation failed'
      
      if (paymentBlockId) {
        form.errors.set(paymentBlockId, errorMessage)
      }
      
      return { 
        success: false, 
        error: errorMessage,
        code: error.code,
        type: error.type
      }
    }
  }

  /**
   * Process a payment for the form
   * @param {Object} paymentBlock - The payment block from the form config
   * @param {Boolean} isRequired - Whether payment is required
   * @returns {Promise<Object>} The result of the payment processing
   */
  const processPayment = async (paymentBlock, isRequired = true) => {
    // Only process payments on the client side
    if (!import.meta.client) {
      console.warn('Payment processing attempted on server')
      return { success: false, error: 'Payment can only be processed in the browser' }
    }

    // Validate the payment block
    if (!paymentBlock || paymentBlock.type !== 'payment') {
      console.error('Invalid payment block provided:', paymentBlock)
      return { success: false, error: 'Invalid payment block' }
    }

    const paymentBlockId = paymentBlock.id

    // First check for existing payment in form data
    const existingPaymentId = form[paymentBlockId]
    if (existingPaymentId && isPaymentIntentId(existingPaymentId)) {
      return { success: true, intentId: existingPaymentId }
    }

    // Check if Stripe elements are initialized
    if (!stripeElements.value || !stripeElements.value.state) {
      console.error('Stripe elements not initialized')
      if (paymentBlockId) form.errors.set(paymentBlockId, 'Payment system not ready')
      return { success: false, error: 'Stripe elements not initialized' }
    }

    const state = stripeElements.value.state
    const { stripe, card } = state

    // Check if Stripe is loaded
    if (!stripe) {
      const error = 'Stripe.js not initialized'
      console.error(error)
      if (paymentBlockId) form.errors.set(paymentBlockId, error)
      return { success: false, error }
    }

    // Check if card is complete
    const cardComplete = card && !card._empty
    if (!cardComplete) {
      // If payment is not required and card is empty, just skip payment
      if (!isRequired) {
        return { success: true, skipped: true }
      }
      
      const error = 'Please enter your card details'
      if (paymentBlockId) form.errors.set(paymentBlockId, error)
      return { success: false, error }
    }

    // Validate billing details
    if (!state.cardHolderName) {
      const error = 'Please enter the name on your card'
      if (paymentBlockId) form.errors.set(paymentBlockId, error)
      return { success: false, error }
    }

    if (!state.cardHolderEmail) {
      const error = 'Please enter your billing email'
      if (paymentBlockId) form.errors.set(paymentBlockId, error)
      return { success: false, error }
    }

    try {
      // Step 1: Create payment intent
      const config = toValue(formConfig)
      const formSlug = config.slug
      
      if (!formSlug) {
        const error = 'Missing form slug'
        if (paymentBlockId) form.errors.set(paymentBlockId, error)
        return { success: false, error }
      }
      
      // Create payment intent
      const intentResult = await _createPaymentIntent(
        paymentBlock.amount,
        paymentBlock.currency || 'usd',
        paymentBlock.description || ''
      )
      
      if (!intentResult.success || !intentResult.client_secret) {
        const error = intentResult.error || 'Failed to create payment intent'
        console.error('Payment intent creation failed:', error)
        if (paymentBlockId) form.errors.set(paymentBlockId, error)
        return { success: false, error }
      }
      
      // Step 2: Confirm payment with Stripe
      const confirmResult = await _confirmStripePayment(intentResult.client_secret, paymentBlockId)
      
      // Return the result from confirmation
      return confirmResult
      
    } catch (error) {
      console.error('Payment processing error:', error)
      const errorMessage = error.message || 'Payment processing failed'
      if (paymentBlockId) form.errors.set(paymentBlockId, errorMessage)
      return { success: false, error: errorMessage }
    }
  }

  /**
   * Confirms a Stripe payment with given client secret.
   * This method is available for direct usage if needed.
   * @param {String} clientSecret - The client secret from a payment intent
   * @param {String} [paymentBlockId] - Optional ID of payment block for error display
   * @returns {Promise<Object>} The result of payment confirmation
   */
  const confirmStripePayment = async (clientSecret, paymentBlockId) => {
    if (!clientSecret) {
      console.error('Missing client secret for payment confirmation')
      return { success: false, error: 'Invalid payment data' }
    }
    
    return await _confirmStripePayment(clientSecret, paymentBlockId)
  }

  // Helper function to check if a value looks like a Stripe payment intent ID
  const isPaymentIntentId = (value) => {
    return typeof value === 'string' && value.startsWith('pi_')
  }

  // Expose the main payment processing function
  return {
    processPayment,
    getPaymentData,
    createPaymentIntent: _createPaymentIntent,
    confirmStripePayment
  }
} 