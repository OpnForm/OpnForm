import amplitude from 'amplitude-js'

let amplitudeClient = null

export function useAmplitude () {
  const config = useRuntimeConfig()
  const amplitudeCode = config.public.amplitudeCode
  if (!amplitudeClient && amplitudeCode && !process.server) {
    amplitudeClient = amplitude.getInstance()
    amplitudeClient.init(amplitudeCode, null, {
      includeReferrer: true,
      includeUtm: true,
      includeGclid: true,
      includeFbclid: true
    })
  }

  const logEvent = function (eventName, eventData) {
    if (config.public.env !== 'production') {
      console.log('[DEBUG] Amplitude logged event:', eventName, eventData)
    }

    if (!amplitudeClient) {
      return
    }

    if (eventData && typeof eventData !== 'object')
      throw new Error('Amplitude event value must be an object.')

    amplitudeClient.logEvent(eventName, eventData)
  }

  const setUser = function (user) {
    if (!amplitudeClient) {
      return
    }
    amplitudeClient.setUserId(user.id)
    amplitudeClient.setUserProperties({
      email: user.email,
      subscribed: user.is_subscribed,
      enterprise_subscription: user.has_enterprise_subscription
    })
  }

  return {
    logEvent,
    setUser,
    amplitude: amplitudeClient
  }
}
