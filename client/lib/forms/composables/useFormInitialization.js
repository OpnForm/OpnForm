import { toValue } from 'vue'
import { formsApi } from '~/api'
import clonedeep from 'clone-deep'
import { createError } from '#app'

/**
 * @fileoverview Composable for initializing form data, with complete handling of 
 * form state persistence, URL parameters, and default values.
 */
export function useFormInitialization(formConfig, form, pendingSubmission) {

  /**
     * Main method to initialize the form data.
     * Follows a clear priority order:
     * 1. Load from submission ID (if provided)
     * 2. Load from pendingSubmission (localStorage) - client-side only
     * 3. Apply URL parameters
     * 4. Apply default values for fields
     * 
     * @param {Object} options - Initialization options
     * @param {String} [options.submissionId] - ID of submission to load
     * @param {URLSearchParams} [options.urlParams] - URL parameters
     * @param {Object} [options.defaultData] - Default data to apply
     * @param {Array} [options.fields] - Form fields for special handling
     */
  const initialize = async (options = {}) => {
    const config = toValue(formConfig)
    

    // 1. Reset form state
    form.reset()
    form.errors.clear()
    
    // 2. Try loading from submission ID
    if (options.submissionId) {
      try {
        const loaded = await tryLoadFromSubmissionId(options.submissionId)
        if (loaded) return // Exit if loaded successfully
      } catch (error) {
        // If 404 error, re-throw to show 404 page
        if (error?.statusCode === 404) {
          throw error
        }
        // For other errors, continue with form initialization
      }
    }
    
    // 3. Try loading from pendingSubmission
    if (!(options.skipPendingSubmission ?? false) && restorePendingSubmission()) {
      return // Exit if loaded successfully
    }
    
    // 4. Apply URL parameters
    if (!(options.skipUrlParams ?? false) && options.urlParams) {
      applyUrlParameters(options.urlParams)
    }
    
    // 5. Apply special field handling
    updateSpecialFields()
    
    // 6. Apply default data from config or options
    const defaultValuesToApply = options.defaultData || config?.default_data
    if (defaultValuesToApply) {
      applyDefaultValues(defaultValuesToApply, config?.properties)
    }
    
    // 7. Normalize field values before resolving visibility or rendering inputs.
    resetAndFill(form.data())
  }
  
  /**
   * Normalize input values before rendering and convert select option IDs to names.
   * @param {Object} formData - Form data to clean and fill
   */
  const resetAndFill = (formData) => {
    if (!formData) {
      form.reset()
      return
    }
    
    // Clone the data to avoid mutating the original
    const cleanData = clonedeep(formData)
    const implicitDefaults = {}
    
    // Process select fields to convert IDs to names
    if (!formConfig.value || !formConfig.value.properties || !Array.isArray(formConfig.value.properties)) {
      // If properties aren't available, just use the data as is
      form.resetAndFill(cleanData)
      return
    }
    
    // Iterate through form fields to process select fields
    formConfig.value.properties.forEach(field => {
      // Basic validation
      if (!field || typeof field !== 'object') return
      if (!field.id || !field.type) return

      // Match unchecked inputs before SSR/visibility evaluation, including hidden checkboxes.
      // Focused Yes/No selectors must remain unanswered until a selection is made.
      const isFocusedToggle = formConfig.value.presentation_style === 'focused' && field.use_focused_toggle !== false
      if (field.type === 'checkbox' && (cleanData[field.id] == null || cleanData[field.id] === '') && !isFocusedToggle) {
        implicitDefaults[field.id] = false
        delete cleanData[field.id]
        return
      }

      // Rating and slider inputs previously supplied these defaults only on mount.
      // Include hidden controls so their conditions never depend on render order.
      const value = cleanData[field.id]
      if ((field.type === 'rating' && (value == null || value === '')) ||
          (field.type === 'slider' && (value == null || value === '' || !Number.isFinite(Number(value))))) {
        implicitDefaults[field.id] = 0
        delete cleanData[field.id]
        return
      }

      // Skip only when value is truly undefined or null
      if (cleanData[field.id] === undefined || cleanData[field.id] === null) return
      
      // Preserve fractional scale values; parseInt on mount used to truncate them.
      if (['rating', 'slider', 'scale'].includes(field.type) &&
          typeof value === 'string' && value.trim() !== '' && Number.isFinite(Number(value))) {
        cleanData[field.id] = Number(value)
      }
      // Process checkbox fields - convert string and numeric values to boolean
      else if (field.type === 'checkbox') {
        const value = cleanData[field.id]
        if (typeof value === 'string' && value.toLowerCase() === 'true' || value === '1' || value === 1) {
          cleanData[field.id] = true
        } else if (typeof value === 'string' && value.toLowerCase() === 'false' || value === '0' || value === 0) {
          cleanData[field.id] = false
        }
      }
      // Only process select, multi_select fields
      else if (['select', 'multi_select'].includes(field.type)) {
        // Make sure the field has options
        if (!field[field.type] || !Array.isArray(field[field.type].options)) return
        
        const options = field[field.type].options
        
        // Process array values (multi-select)
        if (Array.isArray(cleanData[field.id])) {
          cleanData[field.id] = cleanData[field.id].map(optionId => {
            const option = options.find(opt => opt.id === optionId)
            return option ? option.name : optionId
          })
        } 
        // Process single values (select)
        else {
          const option = options.find(opt => opt.id === cleanData[field.id])
          if (option) {
            cleanData[field.id] = option.name
          }
        }
      }
    })
    
    // Fill with cleaned data
    form.resetAndFill(cleanData)

    // Match mounted inputs without retaining an unanswered default as an explicit answer.
    // This lets a new configured prefill apply when the form is reinitialized.
    Object.entries(implicitDefaults).forEach(([fieldId, value]) => {
      form[fieldId] = value
    })
  }

  /**
   * Applies URL parameters to the form data.
   * @param {URLSearchParams} params - The URL search parameters.
   */
  const applyUrlParameters = (params) => {
    if (!params) return
    
    // First, handle regular parameters
    params.forEach((value, key) => {
      // Skip array parameters for now
      if (key.endsWith('[]')) return
      
      try {
        // Try to parse JSON if the value starts with '{'
        const parsedValue = (typeof value === 'string' && value.startsWith('{')) 
          ? JSON.parse(value) 
          : value
          
        form[key] = parsedValue
      } catch {
        // If parsing fails, use the original value
        form[key] = value
      }
    })
    
    // Handle array parameters (key[])
    const paramKeys = [...new Set([...params.keys()])]
    paramKeys.forEach(key => {
      if (key.endsWith('[]')) {
        const arrayValues = params.getAll(key)
        if (arrayValues.length > 0) {
          const baseKey = key.slice(0, -2)
          form[baseKey] = arrayValues
        }
      }
    })
  }

  /**
   * Applies default data to form fields that don't already have values.
   * @param {Object} defaultData - Default data object.
   */
  const applyDefaultValues = (defaultData) => {
    if (!defaultData || Object.keys(defaultData).length === 0) return

    form.resetAndFill(defaultData)
  }

  /**
   * Updates special fields like dates with today's date if configured.
   * @param {Array} fields - Form fields
   */
  const updateSpecialFields = () => {
    formConfig.value.properties.forEach(field => {
      // Handle date fields with prefill_today, only if no value is set
      if (field.type === 'date' && field.prefill_today && form[field.id] == null) {
        form[field.id] = new Date().toISOString()
      }
      // Handle matrix fields with prefill data, only if no value is set
      else if (field.type === 'matrix' && form[field.id] == null && field.prefill) {
        form[field.id] = {...field.prefill}
      } 
      // Handle other fields with prefill data, only if no value is set
      else if (field.id && form[field.id] == null && field.prefill) {
        form[field.id] = field.prefill
      }
    })
  }

  /**
   * Attempts to load form data from an existing submission.
   * @param {String} submissionId - UUID of the submission to load
   * @returns {Promise<Boolean>} - Whether loading was successful
   */
  const tryLoadFromSubmissionId = async (submissionId) => {
    const submissionIdValue = toValue(submissionId)
    if (!submissionIdValue) return false
    const config = toValue(formConfig) // Get the form config value
    const slug = config?.slug // Extract the slug

    if (!slug) {
      console.error('Cannot load submission: Form slug is missing from config.')
      form.reset() // Reset if config is invalid
      return false
    }

    // Use the correct route format: /forms/{slug}/submissions/{submission_id}
    return formsApi.submissions.get(slug, submissionIdValue)
      .then(submissionData => {
        if (submissionData.data) {
          resetAndFill({
            ...submissionData.data, 
            submission_id: submissionIdValue
          })
          return true
        } else {
          // No data returned - throw 404 error to show 404 page instead of rendering form
          throw createError({
            statusCode: 404,
            statusMessage: 'Submission not found'
          })
        }
      })
      .catch(error => {
        // Handle 404 errors - throw to show 404 page instead of rendering form
        if (error?.response?.status === 404 || error?.statusCode === 404) {
          throw createError({
            statusCode: 404,
            statusMessage: 'Submission not found'
          })
        } else {
          console.error(`Error loading submission ${submissionIdValue} for form ${slug}:`, error)
          form.reset()
          return false
        }
      })
  }

  /**
   * Attempts to load form data from pendingSubmission in localStorage.
   * @returns {Boolean} - Whether loading was successful
   */
  const tryLoadFromPendingSubmission = () => {
    // Skip on server or if pendingSubmission is not available
    if (import.meta.server || !pendingSubmission) {
      return false
    }
    
    // Check if auto-save is enabled for this form
    if (!pendingSubmission.enabled?.value) {
      return false
    }
    
    // Get the saved data
    const pendingData = pendingSubmission.get()
    
    if (!pendingData || Object.keys(pendingData).length === 0) {
      return false
    }
    
    // Apply configured prefill to omitted draft fields before initializing checkbox defaults.
    form.resetAndFill(pendingData)
    return true
  }

  const restorePendingSubmission = () => {
    if (!tryLoadFromPendingSubmission()) return false
    updateSpecialFields()
    resetAndFill(form.data())
    return true
  }

  return {
    initialize,
    applyUrlParameters,
    applyDefaultValues,
    restorePendingSubmission,
    resetAndFill // Export our wrapped function for use elsewhere
  }
}
