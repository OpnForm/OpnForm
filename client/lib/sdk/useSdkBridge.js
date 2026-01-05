/**
 * OpnForm SDK Bridge Composable
 * Handles communication between embedded form iframe and parent window SDK
 */
import { watch, toValue, onMounted, onUnmounted } from 'vue'
import { useIsIframe } from '~/composables/useIsIframe'
import { handleDarkMode } from '~/lib/forms/public-page'

const MSG_PREFIX = 'opnform:'

// Event types to emit
const EVENTS = {
  READY: 'ready',
  SUBMIT: 'submit',
  SUBMIT_START: 'submitStart',
  SUBMIT_ERROR: 'submitError',
  RESET: 'reset',
  PAGE_CHANGE: 'pageChange',
  NEXT_PAGE: 'nextPage',
  PREVIOUS_PAGE: 'previousPage',
  DATA_CHANGE: 'dataChange',
  ERROR: 'error',
  SHOW: 'show',
  HIDE: 'hide',
  RESIZE: 'resize'
}

/**
 * Creates an SDK bridge for form-parent communication
 * @param {Object} options - Bridge options
 * @param {Ref} options.formConfig - Reactive form configuration
 * @param {Ref} options.formData - Reactive form data
 * @param {Ref} options.formErrors - Reactive form errors
 * @param {Object} options.formManager - Form manager instance
 * @param {Ref} options.darkMode - Dark mode state ref
 */
export function useSdkBridge(options) {
  const {
    formConfig,
    formData,
    formErrors,
    formManager,
    darkMode
  } = options

  const isIframe = useIsIframe()
  let messageHandler = null

  /**
   * Send event to parent window
   */
  function emitEvent(event, payload = {}) {
    if (!import.meta.client) return
    
    const config = toValue(formConfig)
    const message = {
      type: MSG_PREFIX + 'event',
      event: event,
      formSlug: config?.slug,
      payload: payload
    }

    // Send to parent if in iframe
    if (isIframe) {
      window.parent.postMessage(message, '*')
    }
    // Also send to current window for local listeners
    window.postMessage(message, '*')
  }

  /**
   * Send response to parent window
   */
  function sendResponse(requestId, success, data = null, error = null) {
    if (!import.meta.client) return

    const config = toValue(formConfig)
    const message = {
      type: MSG_PREFIX + 'response',
      formSlug: config?.slug,
      requestId: requestId,
      success: success,
      data: data,
      error: error
    }

    if (isIframe) {
      window.parent.postMessage(message, '*')
    }
    window.postMessage(message, '*')
  }

  /**
   * Handle incoming command from parent
   */
  function handleCommand(message) {
    const { command, payload, requestId } = message
    const config = toValue(formConfig)
    
    // Verify this command is for our form
    if (message.formSlug !== config?.slug) return

    try {
      let result = null

      switch (command) {
        case 'setField':
          if (formManager?.form) {
            formManager.form[payload.fieldId] = payload.value
            result = { success: true }
          }
          break

        case 'setFields':
          if (formManager?.form && payload.data) {
            Object.entries(payload.data).forEach(([fieldId, value]) => {
              formManager.form[fieldId] = value
            })
            result = { success: true }
          }
          break

        case 'clearField':
          if (formManager?.form) {
            formManager.form[payload.fieldId] = null
            result = { success: true }
          }
          break

        case 'clearAll':
          if (formManager?.form) {
            formManager.form.reset()
            result = { success: true }
          }
          break

        case 'isFieldVisible':
          // Check field visibility from form manager's field state
          if (formManager?.fieldState) {
            const visible = formManager.fieldState.isFieldVisible(payload.fieldId)
            result = { visible: toValue(visible) }
          } else {
            result = { visible: true }
          }
          break

        case 'setDarkMode':
          {
            const enabled = payload.enabled
            if (enabled === 'auto') {
              handleDarkMode('auto')
            } else {
              handleDarkMode(enabled ? 'dark' : 'light')
            }
            result = { success: true }
          }
          break

        case 'setTheme':
          // Future enhancement
          result = { success: false, error: 'Not implemented' }
          break

        case 'goToPage':
          if (formManager?.goToPage) {
            formManager.goToPage(payload.index)
            result = { success: true }
          } else if (formManager?.state) {
            formManager.state.currentPage = payload.index
            result = { success: true }
          }
          break

        case 'nextPage':
          if (formManager?.nextPage) {
            formManager.nextPage()
            result = { success: true }
          }
          break

        case 'previousPage':
          if (formManager?.previousPage) {
            formManager.previousPage()
            result = { success: true }
          }
          break

        case 'submit':
          if (formManager?.submit) {
            formManager.submit()
              .then(() => {
                sendResponse(requestId, true, { success: true })
              })
              .catch((err) => {
                sendResponse(requestId, false, null, err?.message || 'Submit failed')
              })
            return // Don't send response yet, it will be sent async
          }
          break

        case 'reset':
          if (formManager?.restart) {
            formManager.restart()
            result = { success: true }
          } else if (formManager?.form) {
            formManager.form.reset()
            result = { success: true }
          }
          break

        case 'focusFirstError':
          // Find and focus first field with error
          if (formManager?.form?.errors) {
            const errors = formManager.form.errors.all()
            const firstField = Object.keys(errors)[0]
            if (firstField) {
              const element = document.querySelector(`[name="${firstField}"], #${firstField}`)
              if (element) {
                element.focus()
                element.scrollIntoView({ behavior: 'smooth', block: 'center' })
              }
            }
            result = { success: true }
          }
          break

        default:
          result = { success: false, error: 'Unknown command: ' + command }
      }

      if (result !== null) {
        sendResponse(requestId, result.success !== false, result)
      }
    } catch (e) {
      console.error('[OpnForm SDK Bridge] Command error:', e)
      sendResponse(requestId, false, null, e.message)
    }
  }

  /**
   * Handle incoming messages
   */
  function onMessage(event) {
    const data = event.data
    if (!data || typeof data !== 'object') return
    
    // Handle SDK commands
    if (data.type === MSG_PREFIX + 'command') {
      handleCommand(data)
    }
  }

  /**
   * Emit ready event with initial state
   */
  function emitReady() {
    const config = toValue(formConfig)
    const data = toValue(formData) || {}
    const pageCount = formManager?.structure?.value?.pageCount?.value || 1
    const currentPage = formManager?.state?.currentPage || 0

    emitEvent(EVENTS.READY, {
      slug: config?.slug,
      id: config?.id,
      data: data,
      currentPage: {
        index: currentPage,
        total: pageCount
      },
      darkMode: toValue(darkMode)
    })
  }

  /**
   * Set up watchers for reactive data
   */
  function setupWatchers() {
    // Watch form data changes
    if (formData) {
      let previousData = JSON.stringify(toValue(formData) || {})
      
      watch(formData, (newData) => {
        const newDataStr = JSON.stringify(newData || {})
        if (newDataStr !== previousData) {
          // Find what changed
          const oldData = JSON.parse(previousData)
          let changedField = null
          let previousValue = null
          let newValue = null

          for (const key of Object.keys(newData || {})) {
            if (JSON.stringify(oldData[key]) !== JSON.stringify(newData[key])) {
              changedField = key
              previousValue = oldData[key]
              newValue = newData[key]
              break
            }
          }

          emitEvent(EVENTS.DATA_CHANGE, {
            data: newData,
            changedField,
            previousValue,
            newValue
          })

          previousData = newDataStr
        }
      }, { deep: true })
    }

    // Watch form errors
    if (formErrors) {
      watch(formErrors, (errors) => {
        if (errors && Object.keys(errors).length > 0) {
          emitEvent(EVENTS.ERROR, { errors })
        }
      }, { deep: true })
    }

    // Watch page changes
    if (formManager?.state) {
      let previousPage = formManager.state.currentPage
      
      watch(() => formManager.state.currentPage, (newPage) => {
        if (newPage !== previousPage) {
          const totalPages = formManager.structure?.value?.pageCount?.value || 1
          
          emitEvent(EVENTS.PAGE_CHANGE, {
            fromPage: previousPage,
            toPage: newPage,
            currentPage: newPage,
            totalPages
          })

          if (newPage > previousPage) {
            emitEvent(EVENTS.NEXT_PAGE, {
              currentPage: newPage,
              totalPages
            })
          } else {
            emitEvent(EVENTS.PREVIOUS_PAGE, {
              currentPage: newPage,
              totalPages
            })
          }

          previousPage = newPage
        }
      })
    }
  }

  // --- Public API for form components to use ---

  /**
   * Emit submit start event
   */
  function onSubmitStart() {
    emitEvent(EVENTS.SUBMIT_START, {})
  }

  /**
   * Emit submit success event
   */
  function onSubmitSuccess(submissionData) {
    const config = toValue(formConfig)
    emitEvent(EVENTS.SUBMIT, {
      data: submissionData.data || toValue(formData),
      submissionId: submissionData.submissionId,
      completionTime: submissionData.completionTime
    })
  }

  /**
   * Emit submit error event
   */
  function onSubmitError(errors) {
    emitEvent(EVENTS.SUBMIT_ERROR, { errors })
  }

  /**
   * Emit reset event
   */
  function onReset() {
    emitEvent(EVENTS.RESET, {})
  }

  // Lifecycle
  onMounted(() => {
    if (!import.meta.client) return

    messageHandler = onMessage
    window.addEventListener('message', messageHandler)
    setupWatchers()

    // Emit ready after a short delay to ensure form is fully loaded
    setTimeout(() => {
      emitReady()
    }, 100)
  })

  onUnmounted(() => {
    if (messageHandler) {
      window.removeEventListener('message', messageHandler)
    }
  })

  return {
    emitEvent,
    onSubmitStart,
    onSubmitSuccess,
    onSubmitError,
    onReset,
    EVENTS
  }
}

