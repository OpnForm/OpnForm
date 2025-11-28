import { toValue } from 'vue'
import { formsApi } from '~/api'
import clonedeep from 'clone-deep'

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
      const loaded = await tryLoadFromSubmissionId(options.submissionId)
      if (loaded) return // Exit if loaded successfully
    }
    
    // 3. Try loading from pendingSubmission
    if (!(options.skipPendingSubmission ?? false) && tryLoadFromPendingSubmission()) {
      updateSpecialFields()
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
    
    // 7. Process any select fields to ensure IDs are converted to names
    // This is crucial when receiving data that might contain IDs instead of names
    const currentData = form.data()
    if (Object.keys(currentData).length > 0) {
      resetAndFill(currentData)
    }
  }
  
  /**
   * Wrapper for form.resetAndFill that converts select option IDs to names
   * @param {Object} formData - Form data to clean and fill
   */
  const resetAndFill = (formData) => {
    if (!formData) {
      form.reset()
      return
    }
    
    // Clone the data to avoid mutating the original
    const cleanData = clonedeep(formData)
    
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
      // Skip only when value is truly undefined or null
      if (cleanData[field.id] === undefined || cleanData[field.id] === null) return
      
      // Process checkbox fields - convert string and numeric values to boolean
      if (field.type === 'checkbox') {
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
   * @param {String} submissionId - ID of the submission to load
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
          console.warn(`Submission ${submissionIdValue} for form ${slug} loaded but returned no data.`)
          form.reset()
          return false
        }
      })
      .catch(error => {
        console.error(`Error loading submission ${submissionIdValue} for form ${slug}:`, error)
        form.reset()
        return false
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
    
    resetAndFill(pendingData)
    return true
  }

  return {
    initialize,
    applyUrlParameters,
    applyDefaultValues,
    resetAndFill // Export our wrapped function for use elsewhere
  }
} 