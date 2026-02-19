/* eslint-disable */
/**
 * OpnForm SDK Bridge & Local SDK
 * Handles communication between embedded form iframe and parent window SDK
 * Also provides local SDK for non-iframe usage (custom code support)
 */
import { watch, toValue, onMounted, onUnmounted } from 'vue'
import { useIsIframe } from '~/composables/useIsIframe'
import { handleDarkMode } from '~/lib/forms/public-page'

const MSG_PREFIX = 'opnform:'

// Event types
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

// ============================================================================
// Local SDK Classes (for custom code support without iframe)
// ============================================================================

/**
 * Simple EventEmitter implementation
 */
class EventEmitter {
  constructor() {
    this._listeners = {}
    this._onceListeners = {}
  }

  on(event, callback) {
    if (Array.isArray(event)) {
      event.forEach(e => this.on(e, callback))
      return this
    }
    if (!this._listeners[event]) {
      this._listeners[event] = []
    }
    this._listeners[event].push(callback)
    return this
  }

  once(event, callback) {
    if (!this._onceListeners[event]) {
      this._onceListeners[event] = []
    }
    this._onceListeners[event].push(callback)
    return this
  }

  off(event, callback) {
    if (!callback) {
      delete this._listeners[event]
      delete this._onceListeners[event]
      return this
    }
    if (this._listeners[event]) {
      this._listeners[event] = this._listeners[event].filter(cb => cb !== callback)
    }
    if (this._onceListeners[event]) {
      this._onceListeners[event] = this._onceListeners[event].filter(cb => cb !== callback)
    }
    return this
  }

  emit(event, data) {
    if (this._listeners[event]) {
      this._listeners[event].forEach(callback => {
        try { callback(data) } catch (e) { console.error('[OpnForm SDK] Error in event handler:', e) }
      })
    }
    if (this._onceListeners[event]) {
      const callbacks = this._onceListeners[event]
      delete this._onceListeners[event]
      callbacks.forEach(callback => {
        try { callback(data) } catch (e) { console.error('[OpnForm SDK] Error in once handler:', e) }
      })
    }
  }
}

/**
 * Local Form Instance - represents the current form on the page
 */
class LocalFormInstance extends EventEmitter {
  constructor(slug, sdk) {
    super()
    this.slug = slug
    this.id = slug
    this._sdk = sdk
    this._ready = false
    this._bridgeData = null
    this._cachedData = {}
    this._cachedErrors = {}
    this._darkMode = false
    this._currentPage = { index: 0, total: 1 }
  }

  /**
   * Register the SDK bridge data from the form component
   */
  _registerBridge(bridgeData) {
    this._bridgeData = bridgeData
    
    // Migrate listeners from stub if exists
    if (typeof window !== 'undefined' && window.opnform?._isStub) {
      const formStub = window.opnform._forms?.[this.slug]
      if (formStub) {
        Object.entries(formStub._listeners || {}).forEach(([event, cbs]) => {
          cbs.forEach(cb => this.on(event, cb))
        })
        Object.entries(formStub._onceListeners || {}).forEach(([event, cbs]) => {
          cbs.forEach(cb => this.once(event, cb))
        })
      }
    }
  }

  _getFormManager() {
    return this._bridgeData?.formManager
  }

  isReady() { return this._ready }

  // --- Field Operations ---
  getField(fieldId) { 
    const fm = this._getFormManager()
    return fm?.form ? fm.form[fieldId] : this._cachedData[fieldId]
  }

  setField(fieldId, value) { 
    const fm = this._getFormManager()
    if (fm?.form) {
      fm.form[fieldId] = value
      return true
    }
    return false
  }

  setFields(data) { 
    const fm = this._getFormManager()
    if (fm?.form && data) {
      Object.entries(data).forEach(([fieldId, value]) => {
        fm.form[fieldId] = value
      })
      return true
    }
    return false
  }

  getData() { 
    const fm = this._getFormManager()
    return fm?.form?.data ? fm.form.data() : { ...this._cachedData }
  }

  clearField(fieldId) { 
    const fm = this._getFormManager()
    if (fm?.form) {
      fm.form[fieldId] = null
      return true
    }
    return false
  }

  clearAll() { 
    const fm = this._getFormManager()
    if (fm?.form?.reset) {
      fm.form.reset()
      return true
    }
    return false
  }

  // --- Field State ---
  hasError(fieldId) { 
    const fm = this._getFormManager()
    if (fm?.form?.errors) {
      return !!fm.form.errors.all()[fieldId]
    }
    return !!this._cachedErrors[fieldId]
  }

  getError(fieldId) { 
    const fm = this._getFormManager()
    if (fm?.form?.errors) {
      return fm.form.errors.all()[fieldId] || null
    }
    return this._cachedErrors[fieldId] || null
  }

  getErrors() { 
    const fm = this._getFormManager()
    return fm?.form?.errors ? fm.form.errors.all() : { ...this._cachedErrors }
  }

  isFieldVisible(fieldId) { 
    const fm = this._getFormManager()
    if (fm?.fieldState?.isFieldVisible) {
      const visible = fm.fieldState.isFieldVisible(fieldId)
      return typeof visible === 'object' && 'value' in visible ? visible.value : visible
    }
    return true
  }

  // --- Theme ---
  toggleDarkMode() { 
    return this.setDarkMode(!this._darkMode)
  }

  setDarkMode(enabled) {
    if (typeof window !== 'undefined') {
      if (enabled === 'auto') {
        handleDarkMode('auto')
        this._darkMode = null
      } else {
        handleDarkMode(enabled ? 'dark' : 'light')
        this._darkMode = !!enabled
      }
    }
    return true
  }

  isDarkMode() { return this._darkMode }

  setTheme(theme) { 
    console.warn('[OpnForm SDK] setTheme is not yet implemented')
    return false
  }

  // --- Navigation ---
  goToPage(index) { 
    const fm = this._getFormManager()
    if (fm?.goToPage) {
      fm.goToPage(index)
      return true
    } else if (fm?.state) {
      fm.state.currentPage = index
      return true
    }
    return false
  }

  nextPage() { 
    const fm = this._getFormManager()
    if (fm?.nextPage) {
      fm.nextPage()
      return true
    }
    return false
  }

  previousPage() { 
    const fm = this._getFormManager()
    if (fm?.previousPage) {
      fm.previousPage()
      return true
    }
    return false
  }

  getCurrentPage() { 
    const fm = this._getFormManager()
    if (fm?.state) {
      const total = fm.structure?.value?.pageCount?.value || 1
      return { index: fm.state.currentPage || 0, total }
    }
    return { ...this._currentPage }
  }

  canGoNext() { 
    const page = this.getCurrentPage()
    return page.index < page.total - 1
  }

  canGoPrevious() { 
    const page = this.getCurrentPage()
    return page.index > 0
  }

  // --- Actions ---
  submit() { 
    const submitBtn = document.querySelector('#public-form button[type="submit"], #public-form [data-submit-button]')
    if (submitBtn) {
      submitBtn.click()
      return Promise.resolve(true)
    }
    return Promise.reject(new Error('Submit button not found'))
  }

  reset() { 
    const fm = this._getFormManager()
    if (fm?.restart) {
      fm.restart()
      return true
    } else if (fm?.form?.reset) {
      fm.form.reset()
      return true
    }
    return false
  }

  focusFirstError() { 
    const errors = this.getErrors()
    const firstField = Object.keys(errors)[0]
    if (firstField) {
      const element = document.querySelector(`[name="${firstField}"], #${firstField}`)
      if (element) {
        element.focus()
        element.scrollIntoView({ behavior: 'smooth', block: 'center' })
        return true
      }
    }
    return false
  }

  // --- Popup Control (not applicable for direct page) ---
  open() { console.warn('[OpnForm SDK] open() not applicable for non-iframe forms') }
  close() { console.warn('[OpnForm SDK] close() not applicable for non-iframe forms') }
  toggle() { console.warn('[OpnForm SDK] toggle() not applicable for non-iframe forms') }
  isOpen() { return true }

  _getFormInfo() {
    return { slug: this.slug, id: this.id }
  }
}

/**
 * Local OpnForm SDK - runs on form pages directly
 */
class OpnFormLocalSDK extends EventEmitter {
  constructor() {
    super()
    this._forms = {}
    this._options = { defaultDarkMode: 'auto' }
    this._initialized = false
    this._messageHandler = this._handleMessage.bind(this)
  }

  init(options = {}) {
    this._options = { ...this._options, ...options }
    
    if (!this._initialized && typeof window !== 'undefined') {
      window.addEventListener('message', this._messageHandler)
      this._initialized = true
    }

    if (this._options.onReady && Object.keys(this._forms).length > 0) {
      const readyForms = Object.values(this._forms).filter(f => f.isReady())
      if (readyForms.length > 0) {
        this._options.onReady(readyForms)
      }
    }
  }

  _registerForm(slug, bridgeData) {
    if (!this._forms[slug]) {
      this._forms[slug] = new LocalFormInstance(slug, this)
    }
    this._forms[slug]._registerBridge(bridgeData)
    
    // Migrate global listeners from stub
    if (typeof window !== 'undefined' && window.opnform?._isStub) {
      const stub = window.opnform
      Object.entries(stub._listeners || {}).forEach(([event, cbs]) => {
        cbs.forEach(cb => this.on(event, cb))
      })
      Object.entries(stub._onceListeners || {}).forEach(([event, cbs]) => {
        cbs.forEach(cb => this.once(event, cb))
      })
    }
    
    return this._forms[slug]
  }

  _handleMessage(event) {
    const data = event.data
    if (!data || typeof data !== 'object') return

    if (data.type && data.type.startsWith && data.type.startsWith(MSG_PREFIX)) {
      const messageType = data.type.replace(MSG_PREFIX, '')
      if (messageType === 'event') {
        this._handleEvent(data)
      }
    }
  }

  _handleEvent(message) {
    const { event, formSlug, payload } = message
    let form = this._forms[formSlug]

    if (!form && formSlug) {
      form = new LocalFormInstance(formSlug, this)
      this._forms[formSlug] = form
    }

    if (!form) return

    // Update internal state
    switch (event) {
      case EVENTS.READY:
        form._ready = true
        if (payload.data) form._cachedData = payload.data
        if (payload.currentPage) form._currentPage = payload.currentPage
        if (payload.darkMode !== undefined) form._darkMode = payload.darkMode
        break
      case EVENTS.DATA_CHANGE:
        form._cachedData = payload.data || {}
        break
      case EVENTS.ERROR:
      case EVENTS.SUBMIT_ERROR:
        form._cachedErrors = payload.errors || {}
        break
      case EVENTS.SUBMIT:
        form._cachedData = payload.data || {}
        break
      case EVENTS.PAGE_CHANGE:
      case EVENTS.NEXT_PAGE:
      case EVENTS.PREVIOUS_PAGE:
        if (payload.currentPage !== undefined) form._currentPage.index = payload.currentPage
        if (payload.toPage !== undefined) form._currentPage.index = payload.toPage
        if (payload.totalPages !== undefined) form._currentPage.total = payload.totalPages
        break
      case EVENTS.RESET:
        form._cachedData = {}
        form._cachedErrors = {}
        form._currentPage = { index: 0, total: form._currentPage.total }
        break
    }

    // Emit events
    const eventData = { form: form._getFormInfo(), ...payload }
    form.emit(event, eventData)
    this.emit(event, eventData)

    if (event === EVENTS.READY && this._options.onReady) {
      const allForms = Object.values(this._forms)
      if (allForms.every(f => f.isReady())) {
        this._options.onReady(allForms)
      }
    }
  }

  get(slugOrId) { 
    return this._forms[slugOrId] || null 
  }

  getAll() {
    return Object.values(this._forms)
  }

  isReady(slugOrId) {
    const form = this._forms[slugOrId]
    return form ? form.isReady() : false
  }

  create(slug, options = {}) {
    console.warn('[OpnForm SDK] create() not applicable for local SDK.')
    return null
  }
}

/**
 * Create and initialize the local SDK
 */
export function createLocalSDK() {
  if (typeof window === 'undefined') return null
  
  // Already initialized with real SDK
  if (window.opnform && window.opnform._isLocal) {
    return window.opnform
  }

  const sdk = new OpnFormLocalSDK()
  sdk._isLocal = true
  
  // Migrate from stub if exists
  if (window.opnform && window.opnform._isStub) {
    const stub = window.opnform
    
    // Copy global listeners
    Object.entries(stub._listeners || {}).forEach(([event, cbs]) => {
      cbs.forEach(cb => sdk.on(event, cb))
    })
    Object.entries(stub._onceListeners || {}).forEach(([event, cbs]) => {
      cbs.forEach(cb => sdk.once(event, cb))
    })
    
    // Copy form stubs
    Object.entries(stub._forms || {}).forEach(([slug, formStub]) => {
      if (!sdk._forms[slug]) {
        const instance = new LocalFormInstance(slug, sdk)
        Object.entries(formStub._listeners || {}).forEach(([event, cbs]) => {
          cbs.forEach(cb => instance.on(event, cb))
        })
        Object.entries(formStub._onceListeners || {}).forEach(([event, cbs]) => {
          cbs.forEach(cb => instance.once(event, cb))
        })
        sdk._forms[slug] = instance
      }
    })
  }
  
  sdk.init()
  return sdk
}

/**
 * Initialize local SDK for non-iframe custom code support
 */
function initLocalSDK() {
  if (typeof window === 'undefined') return null
  
  // Don't replace existing SDK (iframe SDK takes precedence)
  if (window.opnform && !window.opnform._isStub) return window.opnform
  
  const sdk = createLocalSDK()
  if (sdk) {
    window.opnform = sdk
  }
  return sdk
}

// ============================================================================
// SDK Bridge Composable (for form-parent communication)
// ============================================================================

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
   * Deep clone to convert reactive objects/proxies to plain objects
   * This is necessary because postMessage uses structured clone which cannot handle Proxies
   */
  function toPlainObject(obj) {
    if (obj === null || obj === undefined) return obj
    try {
      return JSON.parse(JSON.stringify(obj))
    } catch (e) {
      // If JSON serialization fails, return empty object
      console.warn('[OpnForm SDK] Failed to serialize payload:', e)
      return {}
    }
  }

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
      payload: toPlainObject(payload)
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
      data: toPlainObject(data),
      error: toPlainObject(error)
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
              .then(() => sendResponse(requestId, true, { success: true }))
              .catch((err) => sendResponse(requestId, false, null, err?.message || 'Submit failed'))
            return
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
      currentPage: { index: currentPage, total: pageCount },
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
            emitEvent(EVENTS.NEXT_PAGE, { currentPage: newPage, totalPages })
          } else {
            emitEvent(EVENTS.PREVIOUS_PAGE, { currentPage: newPage, totalPages })
          }

          previousPage = newPage
        }
      })
    }
  }

  // --- Public API ---
  function onSubmitStart() {
    emitEvent(EVENTS.SUBMIT_START, {})
  }

  function onSubmitSuccess(submissionData) {
    emitEvent(EVENTS.SUBMIT, {
      data: submissionData.data || toValue(formData),
      submissionId: submissionData.submissionId,
      completionTime: submissionData.completionTime
    })
  }

  function onSubmitError(errors) {
    emitEvent(EVENTS.SUBMIT_ERROR, { errors })
  }

  function onReset() {
    emitEvent(EVENTS.RESET, {})
  }

  // Lifecycle
  onMounted(() => {
    if (!import.meta.client) return

    messageHandler = onMessage
    window.addEventListener('message', messageHandler)
    setupWatchers()

    // Initialize local SDK for custom code support
    const localSDK = initLocalSDK()
    if (localSDK && localSDK._registerForm) {
      const config = toValue(formConfig)
      if (config?.slug) {
        localSDK._registerForm(config.slug, {
          formManager,
          formConfig,
          formData,
          formErrors,
          darkMode
        })
      }
    }

    // Emit ready after short delay
    setTimeout(() => emitReady(), 100)
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

// Export for external use
export { OpnFormLocalSDK, EVENTS }
