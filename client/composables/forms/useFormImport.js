const showFormImportModal = ref(false)
const importCallback = ref(null)

export function useFormImport() {
  const openFormImport = (options = {}) => {
    importCallback.value = options.onImported || null
    showFormImportModal.value = true
  }

  const closeFormImport = () => {
    showFormImportModal.value = false
    importCallback.value = null
  }

  const handleImported = (formData) => {
    if (importCallback.value) {
      importCallback.value(formData)
    }
    closeFormImport()
  }

  return {
    showFormImportModal: readonly(showFormImportModal),
    openFormImport,
    closeFormImport,
    handleImported,
  }
}
