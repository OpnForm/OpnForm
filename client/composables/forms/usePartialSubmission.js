import { formsApi } from "~/api"
import { watch, onBeforeUnmount, ref, toValue } from 'vue'

// Create a Map to store submission hashes for different forms
// This map might be better managed globally or passed if needed across instances
const submissionHashes = ref(new Map())

/**
 * Composable for handling partial form submissions (auto-syncing to server).
 *
 * @param {Object} formConfig - The form configuration object (not reactive needed here, just for slug).
 * @param {import('vue').ComputedRef<Object>} formDataRef - Computed reference to the reactive form data.
 * @param {Object} pendingSubmissionService - The instantiated service from usePendingSubmission.
 */
export function usePartialSubmission(formConfig, formDataRef, pendingSubmissionService) {

  let syncTimeout = null
  let dataWatcher = null

  const getSubmissionHash = () => {
    // Prioritize hash from the pendingSubmission service (localStorage)
    const storedHash = pendingSubmissionService.getSubmissionHash()
    if (storedHash) return storedHash
    // Fallback to the in-memory map for this instance if needed (should ideally be synced)
    const key = pendingSubmissionService.formPendingSubmissionKey?.value // Use optional chaining
    return key ? submissionHashes.value.get(key) : null
  }

  const setSubmissionHash = (hash) => {
    // Set in both localStorage (via service) and the local map
    pendingSubmissionService.setSubmissionHash(hash)
    const key = pendingSubmissionService.formPendingSubmissionKey?.value
    if (key) {
      submissionHashes.value.set(key, hash)
    }
  }

  /**
   * Clears the submission hash from both localStorage and memory.
   * Call this after successful form submission to prevent stale data.
   */
  const clearSubmissionHash = () => {
    pendingSubmissionService.setSubmissionHash(null)
    const key = pendingSubmissionService.formPendingSubmissionKey?.value
    if (key) {
      submissionHashes.value.delete(key)
    }
  }

  const debouncedSync = () => {
    // Clear existing timeout to reset the timer
    if (syncTimeout) {
      clearTimeout(syncTimeout)
    }
    
    syncTimeout = setTimeout(() => {
      syncToServer()
    }, 2000) // 2 second debounce
  }

  // Add a function to execute sync immediately without debouncing
  // This is used for critical moments like page unload
  const syncImmediately = () => {
    if (syncTimeout) {
      clearTimeout(syncTimeout)
      syncTimeout = null
    }
    return syncToServer()
  }

  const syncToServer = async () => {
    const config = toValue(formConfig) // Ensure we have the latest config value
    // Check if partial submissions are enabled and if we have data
    if (!config?.enable_partial_submissions) {
      return
    }
    
    // Get current form data from the reactive ref
    const currentData = formDataRef.value // Directly use .value from computed ref
      
    // Skip if no data or empty data
    if (!currentData || Object.keys(currentData).length === 0) {
      return
    }

    try {
      const response = await formsApi.submissions.answer(config.slug, {
        ...currentData,
        'is_partial': true,
        'submission_hash': getSubmissionHash() // Use the updated getter
      })
      if (response.submission_hash) {
        setSubmissionHash(response.submission_hash) // Use the updated setter
      }
    } catch (error) {
      console.error(error)
    }
  }

  // Add these handlers as named functions so we can remove them later
  const handleVisibilityChange = () => {
    if (document.visibilityState === 'hidden') {
      // When tab becomes hidden, sync immediately
      debouncedSync()
    }
  }

  const handleBlur = () => {
    // When window loses focus, sync immediately
    debouncedSync()
  }

  const handleBeforeUnload = () => {
    // Before page unloads, sync immediately
    syncImmediately()
  }

  const startSync = () => {
    if (dataWatcher || import.meta.server) { // Prevent starting multiple times or on server
      return
    }

    // Initial sync
    debouncedSync()

    // Watch formDataRef using Vue's reactivity
    dataWatcher = watch(
      formDataRef,
      (_newValue) => {
        debouncedSync()
      },
      { deep: true }
    )

    // Add event listeners for critical moments
    document.addEventListener('visibilitychange', handleVisibilityChange)
    window.addEventListener('blur', handleBlur)
    window.addEventListener('beforeunload', handleBeforeUnload)
  }

  /**
   * Stops the partial submission sync.
   * @param {Object} options - Options for stopping
   * @param {boolean} options.skipFinalSync - If true, skip the final sync (use when about to do a complete submission)
   */
  const stopSync = (options = {}) => {
    if (import.meta.server) return
    
    const { skipFinalSync = false } = options
    
    // Only sync before stopping if we have a hash AND we're not skipping
    // Skip when doing a final submission to avoid race conditions where
    // the partial submission (is_partial: true) arrives after the final submission
    if (!skipFinalSync && getSubmissionHash()) {
      syncImmediately()
    }
    
    const key = pendingSubmissionService.formPendingSubmissionKey?.value
    if (key) {
      submissionHashes.value.delete(key) // Clear from instance map on stop
    }

    if (dataWatcher) {
      dataWatcher()
      dataWatcher = null
    }
    
    if (syncTimeout) {
      clearTimeout(syncTimeout)
      syncTimeout = null
    }

    // Remove event listeners
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    window.removeEventListener('blur', handleBlur)
    window.removeEventListener('beforeunload', handleBeforeUnload)
  }

  // Ensure cleanup when component is unmounted
  onBeforeUnmount(() => {
    stopSync()
  })

  return {
    startSync,
    stopSync,
    syncToServer: debouncedSync, // Expose the debounced version externally
    syncImmediately, // Also expose the immediate sync for critical situations
    getSubmissionHash, // Use the getter that prioritizes localStorage
    setSubmissionHash, // Use the setter that updates both
    clearSubmissionHash // Clear hash from both localStorage and memory after successful submission
  }
}