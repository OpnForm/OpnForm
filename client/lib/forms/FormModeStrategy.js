/**
 * Form modes for OpenForm components
 */
export const FormMode = {
  LIVE: 'live',           // Real form with full validation and submission
  PREVIEW: 'preview',     // Admin preview with no validation
  PREFILL: 'prefill',     // URL prefill preview with no validation
  EDIT: 'edit',           // Editing an existing submission
  TEST: 'test',           // Test mode with validation but no actual submission
  TEMPLATE: 'template',   // Template mode with no validation
  READ_ONLY: 'read_only'  // Read only mode
}

/**
 * Creates a comprehensive strategy based on the form mode
 * This handles all mode-specific behaviors, not just validation
 * 
 * @param {string} mode - One of the FormMode values
 * @returns {Object} - Strategy object with all mode-specific behaviors
 */
export function createFormModeStrategy(mode) {
  // Default configuration (LIVE mode)
  const defaultStrategy = {
    // Validation behaviors
    validation: {
      validateOnNextPage: true,
      validateOnSubmit: true,
      performActualSubmission: true
    },
    
    // Display behaviors
    display: {
      showHiddenFields: false,
      enableDisabledFields: false,
      showFormCleanings: true,
      showFontLink: false,
      showBranding: true,
      disableFields: false,
      // When true, UI should force classic presentation regardless of form config
      forceClassicPresentation: false
    },
    
    // Admin behaviors
    admin: {
      allowDragging: false,
      showAdminControls: false,
    },
    
    // Submission behaviors
    submission: {
      enablePartialSubmissions: true
    }
  }

  // Return default strategy for LIVE mode or unknown modes
  if (mode === FormMode.LIVE || !Object.values(FormMode).includes(mode)) {
    return defaultStrategy
  }

  // Create a copy of the default strategy to modify
  const strategy = JSON.parse(JSON.stringify(defaultStrategy))

  // Apply mode-specific overrides
  switch (mode) {
    case FormMode.PREVIEW:
      // Admin preview - no validation, show admin controls but NOT hidden fields
      strategy.validation.validateOnNextPage = false
      strategy.validation.validateOnSubmit = false
      strategy.validation.performActualSubmission = false
      
      strategy.display.showHiddenFields = false
      strategy.display.showFormCleanings = false
      strategy.display.showFontLink = true
      strategy.display.showBranding = false
      
      strategy.admin.allowDragging = true
      strategy.admin.showAdminControls = true
      
      strategy.submission.enablePartialSubmissions = false
      break

    case FormMode.PREFILL:
      // URL prefill - no validation, show hidden fields
      strategy.validation.validateOnNextPage = false
      strategy.validation.validateOnSubmit = false
      strategy.validation.performActualSubmission = false
      
      strategy.display.showHiddenFields = true
      strategy.display.enableDisabledFields = true
      strategy.display.showBranding = false
      strategy.display.forceClassicPresentation = true
      break

    case FormMode.EDIT:
      // Editing submission - same validation as LIVE mode, but show hidden fields
      // This ensures edit mode behaves like live mode for validation
      strategy.display.showHiddenFields = true
      strategy.display.forceClassicPresentation = true
      strategy.submission.enablePartialSubmissions = false
      break

    case FormMode.TEST:
      // Test mode - validate on submit but don't submit, and don't validate on next page
      strategy.validation.performActualSubmission = false
      strategy.validation.validateOnNextPage = false
      strategy.submission.enablePartialSubmissions = false
      break

    case FormMode.TEMPLATE:
      // Template mode
      strategy.validation.performActualSubmission = false
      strategy.validation.validateOnNextPage = false
      strategy.submission.enablePartialSubmissions = false
      strategy.display.showBranding = false
      break

    case FormMode.READ_ONLY:
      // Read only mode - no validation, no submission, fields are disabled
      strategy.validation.validateOnNextPage = false
      strategy.display.showHiddenFields = true
      strategy.validation.validateOnSubmit = false
      strategy.validation.performActualSubmission = false
      strategy.display.disableFields = true
      strategy.display.forceClassicPresentation = true
      strategy.submission.enablePartialSubmissions = false
      break
    
  }

  return strategy
} 