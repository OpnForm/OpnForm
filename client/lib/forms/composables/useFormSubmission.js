import { toValue } from 'vue'

/**
 * @fileoverview Composable for handling the final form submission process.
 */
export function useFormSubmission(formConfig, form) {

  /**
   * Prepares additional metadata for the submission payload.
   * Focuses only on metadata fields, not the form data itself.
   * @param {Object} options - Options containing completionTime, captchaToken, etc.
   * @returns {Object} Metadata to be included in the submission request.
   */
  const _prepareMetadata = (options = {}) => {
    const metadata = {}

    // Add completion time if provided
    if (options.completionTime !== undefined) {
      metadata.completion_time = options.completionTime
    }
    
    // Add captcha token if provided
    if (options.captchaToken) {
      metadata.captcha_token = options.captchaToken
    }
    
    // Add submission hash if provided (for partial submissions)
    if (options.submissionHash) {
      metadata.submission_hash = options.submissionHash
    }
    
    // Add submission ID if provided (for editable submissions)
    // Supports both UUID (new secure format) and Hashid (legacy format)
    if (options.submissionId) {
      metadata.submission_id = options.submissionId
    }

    return metadata
  }

  /**
   * Performs the form submission.
   * @param {Object} options - Submission options.
   * @param {Object} options.formModeStrategy - The strategy object.
   * @param {Number} [options.completionTime] - Form completion time in seconds.
   * @param {String} [options.captchaToken] - Captcha verification token.
   * @param {String} [options.submissionHash] - Hash for partial submissions.
   * @param {String} [options.submissionId] - UUID or Hashid for editable submissions.
   * @returns {Promise<Object>} The response data from the submission endpoint.
   * @throws {Error} If submission fails.
   */
  const submit = async (options = {}) => {
    // Get the form slug from config
    const formSlug = formConfig.value.slug
    // Get the URL for form submission
    const url = `/forms/${formSlug}/answer`
    // Prepare metadata only (form data will be auto-merged by Form.js)
    const metadata = _prepareMetadata(options)

    // Check if we should actually perform the submission based on mode strategy
    const formModeStrategy = options.formModeStrategy
    const shouldSubmit = formModeStrategy?.validation?.performActualSubmission !== false
    
    let response
    
    if (shouldSubmit) {
      // Only perform the actual API call if the strategy allows it
      response = await toValue(form).post(url, { 
        data: metadata
      })
    } else {
      // Return a mock successful response when in preview/test mode
      response = {
        success: true,
        data: {
          message: 'Form preview submission (no actual submission performed)',
          mock: true
        }
      }
    }
    
    // Optionally reset form after successful submission based on strategy
    if (formModeStrategy?.submission?.resetAfterSubmit) {
      toValue(form).reset()
    }
    
    return response
  }

  // Expose the main submission function
  return {
    submit,
  }
} 