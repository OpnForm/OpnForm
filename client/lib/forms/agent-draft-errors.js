export function isAgentDraftVersionConflict(exception) {
  const status = exception?.statusCode ?? exception?.status ?? exception?.response?.status
  // Nitro wraps the Laravel response in its own `data` field.
  const payload = exception?.data ?? exception?.response?._data
  const errors = payload?.errors ?? payload?.data?.errors

  return status === 422
    && errors !== null
    && typeof errors === 'object'
    && Object.prototype.hasOwnProperty.call(errors, 'expected_version')
}
