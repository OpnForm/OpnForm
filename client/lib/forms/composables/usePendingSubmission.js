import { hash } from "~/lib/utils.js"
import { useStorage, watchThrottled } from "@vueuse/core"
import { computed, toValue } from 'vue'

/**
 * Composable for managing pending form submission data and timer in localStorage.
 * Includes throttled auto-saving of form data.
 *
 * @param {import('vue').Ref<Object>} formConfig - Reactive reference to the form configuration object.
 * @param {import('vue').ComputedRef<Object>} formDataRef - Computed reference to the reactive form data object.
 */
export function usePendingSubmission(formConfig, formDataRef) {
  const config = computed(() => toValue(formConfig)) // Ensure reactivity to config changes

  const formPendingSubmissionKey = computed(() => {
    const currentConfig = config.value
    return currentConfig && typeof window !== 'undefined'
      ? currentConfig.form_pending_submission_key + "-" + hash(window.location.href)
      : ""
  })

  const formPendingSubmissionTimerKey = computed(() => {
    return formPendingSubmissionKey.value ? formPendingSubmissionKey.value + "-timer" : ""
  })

  const enabled = computed(() => {
    // Auto-save is enabled if the feature is turned on in the config
    return config.value?.auto_save ?? false
  })

  // Draft + submission_hash share the same storage key; allow reads/writes when either
  // auto-save or partial submissions need metadata (hash) persisted without full draft sync.
  const canUsePendingStorage = computed(() => {
    return enabled.value || (config.value?.enable_partial_submissions ?? false)
  })

  // Internal function to save data to localStorage
  const saveData = (value) => {
    if (import.meta.server || !canUsePendingStorage.value || !formPendingSubmissionKey.value) return
    try {
      useStorage(formPendingSubmissionKey.value).value =
        value === null ? null : JSON.stringify(value)
    } catch (e) {
      console.error("Error saving pending submission to localStorage:", e)
    }
  }

  // Internal function to retrieve data from localStorage
  const retrieveData = (defaultValue = {}) => {
    if (import.meta.server || !canUsePendingStorage.value || !formPendingSubmissionKey.value) return defaultValue
    try {
      const storageValue = useStorage(formPendingSubmissionKey.value).value
      return storageValue ? JSON.parse(storageValue) : defaultValue
    } catch (e) {
      console.error("Error reading pending submission from localStorage:", e)
      // Attempt to clear corrupted data
      remove()
      return defaultValue
    }
  }

  // Watch formDataRef with throttling
  watchThrottled(
    formDataRef,
    (newData) => {
      // Only persist full draft when auto-save is on (not only partial-submission metadata)
      if (import.meta.client && enabled.value) {
        saveData(newData)
      }
    },
    { deep: true, throttle: 1000 } // Throttle saving to once per second
  )

  // --- Exposed Methods ---

  const remove = () => {
    // Clear the data from storage
    saveData(null)
  }

  const get = (defaultValue = {}) => {
    // Retrieve the currently stored data
    return retrieveData(defaultValue)
  }

  const setSubmissionHash = (hash) => {
    if (!canUsePendingStorage.value) return
    const currentData = retrieveData()
    saveData({
      ...currentData,
      submission_hash: hash
    })
  }

  const getSubmissionHash = () => {
    return retrieveData()?.submission_hash ?? null
  }

  const setTimer = (value) => {
    if (import.meta.server || !formPendingSubmissionTimerKey.value) return
    useStorage(formPendingSubmissionTimerKey.value).value = value
  }

  const removeTimer = () => {
    setTimer(null)
  }

  const getTimer = (defaultValue = null) => {
    if (import.meta.server || !formPendingSubmissionTimerKey.value) return defaultValue
    return useStorage(formPendingSubmissionTimerKey.value, defaultValue).value
  }

  const clear = () => {
    remove()
    removeTimer()
  }

  return {
    formPendingSubmissionKey, // Keep for potential external use (e.g., partial submission hash map key)
    enabled,
    get,                  // Method to retrieve stored data
    remove,               // Method to clear stored data
    setSubmissionHash,    // Method to specifically set the submission hash
    getSubmissionHash,    // Method to specifically get the submission hash
    setTimer,             // Method to set the timer value
    removeTimer,          // Method to clear the timer value
    getTimer,             // Method to get the timer value
    clear,                // Method to clear both data and timer
  }
}
