let amplitudeClient = null
let amplitudePromise = null

function loadAmplitude(amplitudeCode) {
  if (!amplitudeCode || import.meta.server) return Promise.resolve(null)
  if (amplitudeClient) return Promise.resolve(amplitudeClient)
  if (amplitudePromise) return amplitudePromise

  amplitudePromise = import('amplitude-js').then(({ default: amplitude }) => {
    amplitudeClient = amplitude.getInstance()
    amplitudeClient.init(amplitudeCode, null, {
      includeReferrer: true,
      includeUtm: true,
      includeGclid: true,
      includeFbclid: true
    })
    return amplitudeClient
  })

  return amplitudePromise
}

export function useAmplitude () {
  const config = useRuntimeConfig()
  const amplitudeCode = config.public.amplitudeCode
  const logEvent = function (eventName, eventData) {
    if (config.public.env !== 'production') {
      console.log('[DEBUG] Amplitude logged event:', eventName, eventData)
    }

    if (eventData && typeof eventData !== 'object')
      throw new Error('Amplitude event value must be an object.')

    return loadAmplitude(amplitudeCode).then((client) => client?.logEvent(eventName, eventData))
  }

  const setUser = function (user) {
    return loadAmplitude(amplitudeCode).then((client) => {
      if (!client) return
      client.setUserId(user.id)
      client.setUserProperties({
        email: user.email,
        subscribed: user.is_subscribed,
        plan_tier: user.plan_tier ?? 'free'
      })
    })
  }

  return {
    logEvent,
    setUser,
    amplitude: amplitudeClient
  }
}
