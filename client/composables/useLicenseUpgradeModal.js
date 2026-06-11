export function useLicenseUpgradeModal() {
  const { openSubscriptionModal } = useAppModals()

  const getErrorStatus = (error) => {
    return error?.response?.status || error?.status || error?.statusCode
  }

  const getErrorMessage = (error) => {
    return error?.response?._data?.message
      || error?.response?.data?.message
      || error?.data?.message
      || error?.message
      || ''
  }

  const isLicenseError = (error, options = {}) => {
    const message = getErrorMessage(error)
    if (getErrorStatus(error) !== 403) return false

    return message.includes('self-hosted license')
      || message.includes('Enterprise license is required')
      || (options.includeUnauthorized && message === 'This action is unauthorized.')
  }

  const handleLicenseError = (error, options = {}) => {
    if (!isLicenseError(error, options)) return false

    openSubscriptionModal({
      plan: 'self_hosted',
      modal_title: options.title || 'Enterprise self-hosted license required',
      modal_description: options.description || getErrorMessage(error)
    })

    return true
  }

  return {
    getErrorMessage,
    handleLicenseError,
    isLicenseError
  }
}
