import { computed, toValue } from 'vue'

/**
 * @fileoverview Composable for handling form validation logic.
 */
export function useFormValidation(formConfig, form, managerState) {

  const formRef = computed(() => toValue(form)) // Ensure reactivity with the form instance
  const configRef = computed(() => toValue(formConfig)) // Ensure reactivity with config
  // Treat Notion-style layout/display blocks (nf-*) as non-validated elements
  const isLayoutBlock = (field) => {
    const t = field?.type
    return typeof t === 'string' && t.startsWith('nf-')
  }


  /**
   * Validates specific fields using the vForm instance's validate method.
   * @param {Array<String>} fieldIds - Array of field IDs to validate.
   * @param {String} [httpMethod='POST'] - HTTP method for the validation request.
   * @returns {Promise<boolean>} Resolves true if validation passes, rejects with errors if fails.
   */
  const validateFields = async (fieldIds, httpMethod = 'POST') => {
    if (!fieldIds || fieldIds.length === 0) {
      return true // Nothing to validate
    }

    const config = configRef.value
    const validationUrl = `/forms/${config.slug}/answer` // Use reactive config

    // Use the reactive formRef
    try {
      await formRef.value.validate(httpMethod, validationUrl, {}, fieldIds)
    } catch (error) {
      console.error('[useFormValidation] validateFields: Validation error (precognition)', error)
      useAlert().formValidationError(error.data)
      throw error
    }
    return true // Validation passed
  }

  /**
   * Validates fields on the current page based on the form mode strategy.
   * Excludes payment fields.
   * @param {Array<Object>} currentPageFields - Array of field objects for the current page.
   * @param {Object} formModeStrategy - The strategy object for the current mode.
   * @returns {Promise<boolean>} Resolves true if validation passes or isn't required, rejects otherwise.
   */
  // Shared filter to remove layout/payment blocks from validation
  const filterValidatableFields = (fields) => {
    return (fields || []).filter(field => field && field.id && field.type !== 'payment' && !isLayoutBlock(field))
  }

  const validateCurrentPage = async (currentPageFields, formModeStrategy) => {
    if (!formModeStrategy?.validation?.validateOnNextPage) {
      return true
    }

    const fieldIdsToValidate = filterValidatableFields(currentPageFields).map(field => field.id)

    if (fieldIdsToValidate.length === 0) {
      return true
    }

    await validateFields(fieldIdsToValidate)
    return true
  }

  /**
   * Validates all form fields before final submission, *unless* currently on the last page.
   * @param {Array<Object>} allFields - Array of all field objects in the form.
   * @param {Object} formModeStrategy - The strategy object for the current mode.
   * @param {boolean} isCurrentlyLastPage - Boolean indicating if the *current* state is the last page.
   * @returns {Promise<boolean>} Resolves true if validation passes or isn't required/skipped, rejects otherwise.
   */
  const validateSubmissionIfNotLastPage = async (allFields, formModeStrategy, isCurrentlyLastPage) => {
    // Skip validation if we are already determined to be on the last page
    if (isCurrentlyLastPage) {
      return true
    }

    // Skip validation if strategy doesn't require it
    if (!formModeStrategy?.validation?.validateOnSubmit) {
      return true
    }

    const fieldIdsToValidate = filterValidatableFields(allFields).map(field => field.id)

    if (fieldIdsToValidate.length === 0) {
      return true
    }

    await validateFields(fieldIdsToValidate)
    return true
  }

  /**
   * Finds the index of the first page containing validation errors.
   * @param {Array<Array<Object>>} fieldGroups - Nested array of fields per page (from useFormStructure).
   * @returns {number} Index of the first page with errors, or -1 if no errors found.
   */
  const findFirstPageWithError = (fieldGroups) => {
    const errors = formRef.value.errors
    if (!errors || !errors.any()) {
      return -1 // No errors exist
    }
    if (!fieldGroups) return -1 // No groups to check

    for (let i = 0; i < fieldGroups.length; i++) {
      const pageHasError = fieldGroups[i]?.some(field => field && field.id && errors.has(field.id))
      if (pageHasError) {
        return i
      }
    }
    return -1
  }

  /**
   * Actions to perform on validation failure (e.g., during submit or page change).
   * @param {Object} context - Object containing { fieldGroups, timerService, setPageIndexCallback }.
   */
  const onValidationFailure = (context) => {
    const { fieldGroups, timerService, setPageIndexCallback } = context

    // Restart timer using the timer composable instance
    if (timerService && typeof timerService.start === 'function') {
      timerService.start()
    }

    // Find and navigate to the first page with an error
    if (fieldGroups && fieldGroups.length > 1 && typeof setPageIndexCallback === 'function') {
      const errorPageIndex = findFirstPageWithError(fieldGroups)
      if (errorPageIndex !== -1 && errorPageIndex !== managerState?.currentPage) {
        setPageIndexCallback(errorPageIndex)
      }
    }
  }

  // --- Exposed API ---
  return {
    // Methods
    validateFields,
    validateCurrentPage,
    validateSubmissionIfNotLastPage,
    filterValidatableFields,
    findFirstPageWithError,
    onValidationFailure,
  }
} 