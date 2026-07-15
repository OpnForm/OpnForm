export const isOidcRateLimitedError = (error) => {
  return error?.response?.status === 429 || error?.status === 429
}

export const getOidcRetryAfterSeconds = (error) => {
  const response = error?.response
  const retryAfter = response?.headers?.get?.('retry-after')
    ?? response?.headers?.['retry-after']
    ?? response?._data?.retry_after
  const seconds = Number.parseInt(retryAfter, 10)

  return Number.isFinite(seconds) && seconds > 0 ? seconds : 60
}

export const oidcRateLimitMessage = (seconds) => {
  return `Too many sign-in requests. Please try again in ${seconds} second${seconds === 1 ? '' : 's'}.`
}
