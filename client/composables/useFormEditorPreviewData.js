/**
 * Composable to share form preview data between FormEditorPreview and other components
 * like ComputedVariableModal for live preview of formulas
 */

// Shared reactive state
const previewFormData = ref({})
const previewFormManager = ref(null)

export function useFormEditorPreviewData() {
  /**
   * Set the preview form data (called by FormEditorPreview)
   */
  function setPreviewData(data) {
    previewFormData.value = data || {}
  }

  /**
   * Set the preview form manager (called by FormEditorPreview)
   */
  function setPreviewFormManager(manager) {
    previewFormManager.value = manager
  }

  /**
   * Get current preview data for a specific field
   */
  function getFieldValue(fieldId) {
    return previewFormData.value[fieldId]
  }

  /**
   * Get all preview data
   */
  function getAllData() {
    return { ...previewFormData.value }
  }

  /**
   * Check if preview has any data
   */
  function hasData() {
    return Object.keys(previewFormData.value).length > 0
  }

  /**
   * Clear preview data
   */
  function clearData() {
    previewFormData.value = {}
    previewFormManager.value = null
  }

  return {
    previewFormData: readonly(previewFormData),
    previewFormManager: readonly(previewFormManager),
    setPreviewData,
    setPreviewFormManager,
    getFieldValue,
    getAllData,
    hasData,
    clearData
  }
}
