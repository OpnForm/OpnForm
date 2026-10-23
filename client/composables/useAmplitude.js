let amplitudeClient = null
let amplitudePromise = null
let Identify = null

function loadAmplitude(amplitudeCode) {
  if (!amplitudeCode || import.meta.server) return Promise.resolve(null)
  if (amplitudeClient) return Promise.resolve(amplitudeClient)
  if (amplitudePromise) return amplitudePromise

  amplitudePromise = import('@amplitude/analytics-browser').then((amplitude) => {
    const client = amplitude.createInstance()
    Identify = amplitude.Identify
    return client.init(amplitudeCode, undefined, {
      // Preserve campaign attribution without enabling new automatic events.
      autocapture: {
        attribution: true,
        pageViews: false,
        sessions: false,
        fileDownloads: false,
        formInteractions: false,
        elementInteractions: false,
        networkTracking: false,
        performanceTracking: false,
        pageUrlEnrichment: false,
        frustrationInteractions: false
      }
    }).promise.then(() => {
      amplitudeClient = client
      return client
    })
  }).catch((error) => {
    amplitudePromise = null
    throw error
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

    return loadAmplitude(amplitudeCode).then((client) => client?.track(eventName, eventData).promise)
  }

  const setUser = function (user) {
    return loadAmplitude(amplitudeCode).then((client) => {
      if (!client) return
      client.setUserId(String(user.id))
      return client.identify(new Identify()
        .set('email', user.email)
        .set('subscribed', user.is_subscribed)
        .set('plan_tier', user.plan_tier ?? 'free')).promise
    })
  }

  return {
    logEvent,
    setUser,
    amplitude: amplitudeClient
  }
}
