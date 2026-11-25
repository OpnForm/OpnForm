export const SENTRY_APPLICATION_KEY = 'opnform'

function parseUrl (value) {
  try {
    return new URL(value)
  } catch {
    return null
  }
}

export function shouldDropThirdPartyError (event, applicationUrls) {
  const exceptions = event.exception?.values
  // A missing build marker alone does not identify third-party code.
  if (event.tags?.third_party_code !== true || !exceptions?.length) {
    return false
  }

  const applicationOrigins = new Set(applicationUrls.map(url => parseUrl(url)?.origin))

  return exceptions.every(({ stacktrace }) => {
    const frames = stacktrace?.frames?.filter(frame => frame.filename !== '[native code]')
    return frames?.length > 0 && frames.every(({ filename }) => {
      const url = parseUrl(filename)
      return url !== null && ['http:', 'https:'].includes(url.protocol) &&
        !applicationOrigins.has(url.origin) && !/\/(?:_nuxt|widgets)\//.test(url.pathname)
    })
  })
}
