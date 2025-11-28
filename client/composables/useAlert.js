import { h } from 'vue'

export function useAlert() {

  function success (message, autoClose = 10000, options = {}) {
    if (typeof message === 'object' && message !== null) {
      options = { ...message, ...options }
      message = options.description || options.message || ''
    }
    // Compose actions, optionally appending an "Open form" action when a form is provided
    const composedActions = Array.isArray(options.actions) ? [...options.actions] : []
    const form = options.form || options.openForm
    const shareUrl = form?.share_url || options.shareUrl
    if (form && shareUrl) {
      composedActions.push({
        label: options.openFormLabel ?? 'Open form',
        icon: 'i-heroicons-arrow-top-right-on-square',
        onClick: () => {
          if (form.visibility === 'draft') {
            useAlert().warning(
              'This form is currently in Draft mode and is not publicly accessible, You can change the form status on the edit form page.'
            )
          } else if (import.meta.client) {
            window.open(shareUrl, '_blank')
          }
        }
      })
    }

    const extraOptions = { ...options }
    if (composedActions.length > 0) {
      extraOptions.actions = composedActions
    }

    return useToast().add({
      icon: 'i-heroicons-check-circle',
      title: options.title ?? 'Success',
      description: message,
      color: 'success',
      closeIcon: 'i-heroicons-x-mark-20-solid',
      duration: autoClose,
      ...extraOptions
    })
  }

  function error (message, autoClose = 10000, options = {}) {
    return useToast().add({
      icon: 'i-heroicons-exclamation-circle',
      title: options.title ?? 'Error',
      description: message,
      color: 'error',
      duration: autoClose,
      closeIcon: 'i-heroicons-x-mark-20-solid',
      ...options
    })
  }

  function warning (message, autoClose = 10000, options = {}) {
    return useToast().add({
      icon: 'i-heroicons-exclamation-triangle',
      title: options.title ?? 'Warning',
      description: message,
      color: 'warning',
      duration: autoClose,
      closeIcon: 'i-heroicons-x-mark-20-solid',
      ...options
    })
  }

  function confirm (
    message,
    onSuccess,
    onFailure = null,
    autoClose = 10000,
    options = {}
  ) {
    return useToast().add({
      icon: 'i-heroicons-question-mark-circle',
      title: options.title ?? 'Are you sure?',
      description: message,
      color: 'info',
      duration: autoClose,
      actions: [
        { label: options.successLabel ?? 'Yes', onClick: onSuccess },
        { label: options.failureLabel ?? 'No', onClick: onFailure }
      ],
      ...options
    })
  }

  function remove (id) {
    useToast().remove(id)
  }

  function validationError(error, autoClose = 10000, options = {}) {
    const t = useNuxtApp().$i18n.t
    const message = error.message || t('forms.validation_error')
    let description = message

    if (error.errors) {
      description = Object.entries(error.errors)
        .map(([field, messages]) => {
          // Format the field name (remove dots and array indices)
          const fieldName = field.split('.').pop().replace(/\d+/g, '')
          return `${fieldName}: ${messages.join(', ')}`
        })
        .join('\n')
    }

    return useToast().add({
      icon: 'i-heroicons-x-circle',
      title: options.title ?? t('forms.validation_error'),
      description,
      color: 'error',
      duration: autoClose,
      ...options
    })
  }

  function formValidationError(error, form, autoClose = 10000, options = {}) {
    if (!error || !error.errors) {
      return error(error.message || 'An unknown validation error occurred', autoClose, options)
    }

    const t = useNuxtApp().$i18n.t
    
    // Count total errors
    const errorCount = Object.keys(error.errors).length

    // Create VNode for error messages instead of HTML strings
    let description = ''
    if (Object.keys(error.errors).length > 0) {
      // Create list items as VNodes
      const listItems = Object.entries(error.errors)
        .flatMap(([_field, messages]) => {
          return messages.map(message => h('li', message))
        })
      
      // Create the description as a VNode with an unordered list
      description = h('ul', { class: 'list-disc pl-4' }, listItems)
    }

    // Add count of errors to the title
    const title = options.title || (errorCount > 1 
      ? t('forms.validation_error_with_count', { count: errorCount })
      : t('forms.validation_error'))

    return useToast().add({
      icon: 'i-heroicons-x-circle',
      title,
      description,
      color: 'error',
      duration: autoClose,
      ...options
    })
  }

  return {
    success,
    error,
    warning,
    confirm,
    remove,
    validationError,
    formValidationError
  }
}
