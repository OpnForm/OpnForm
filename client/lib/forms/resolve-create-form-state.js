export function resolveCreateFormState(baseFormData, options = {}) {
  const importedData = parseImportedFormData(options.importedFormData)
  if (importedData) {
    return {
      formData: { ...baseFormData, ...importedData },
      showInitialModal: false,
    }
  }

  if (options.templateStructure) {
    return {
      formData: { ...baseFormData, ...options.templateStructure },
      showInitialModal: false,
    }
  }

  return {
    formData: baseFormData,
    showInitialModal: true,
  }
}

export function parseImportedFormData(importedFormData) {
  if (!importedFormData) {
    return null
  }

  try {
    return JSON.parse(importedFormData)
  } catch {
    return null
  }
}
