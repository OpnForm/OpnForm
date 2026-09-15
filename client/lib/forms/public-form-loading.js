const TRANSIENT_RESPONSE_STATUSES = new Set([408, 425, 429])

function getErrorStatus (error) {
  const rawStatus = error?.statusCode ?? error?.status ?? error?.response?.status
  const status = Number(rawStatus)

  return Number.isInteger(status) && status > 0 ? status : null
}

export function isPublicFormNotFoundError (error) {
  return getErrorStatus(error) === 404
}

export function shouldRetryPublicFormRequest (failureCount, error) {
  if (failureCount >= 2) {
    return false
  }

  const status = getErrorStatus(error)

  return status === null || TRANSIENT_RESPONSE_STATUSES.has(status) || status >= 500
}

export function publicFormRetryDelay (attemptIndex) {
  return Math.min(250 * (2 ** attemptIndex), 1_000)
}

export function getPublicFormResponseStatus (error) {
  return isPublicFormNotFoundError(error) ? 404 : 503
}
