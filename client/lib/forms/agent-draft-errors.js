export function isAgentDraftVersionConflict(exception) {
  const status = exception?.statusCode ?? exception?.status ?? exception?.response?.status
  const errors = exception?.data?.errors ?? exception?.response?._data?.errors

  return status === 422
    && errors !== null
    && typeof errors === 'object'
    && Object.prototype.hasOwnProperty.call(errors, 'expected_version')
}
