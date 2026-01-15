/* eslint-disable */
/**
 * OpnForm Local SDK Stub
 * Creates window.opnform early for custom code support
 * The real SDK (useSdkBridge) will enhance this when the form loads
 */
(function() {
  if (window.opnform) return
  
  var listeners = {}
  var onceListeners = {}
  var forms = {}
  
  function emit(event, data) {
    if (listeners[event]) {
      listeners[event].forEach(function(cb) { try { cb(data) } catch(e) {} })
    }
    if (onceListeners[event]) {
      var cbs = onceListeners[event]
      delete onceListeners[event]
      cbs.forEach(function(cb) { try { cb(data) } catch(e) {} })
    }
  }
  
  function FormStub(slug) {
    this.slug = slug
    this.id = slug
    this._ready = false
    this._listeners = {}
    this._onceListeners = {}
  }
  
  FormStub.prototype.on = function(event, cb) {
    if (Array.isArray(event)) {
      var self = this
      event.forEach(function(e) { self.on(e, cb) })
      return this
    }
    if (!this._listeners[event]) this._listeners[event] = []
    this._listeners[event].push(cb)
    return this
  }
  
  FormStub.prototype.once = function(event, cb) {
    if (!this._onceListeners[event]) this._onceListeners[event] = []
    this._onceListeners[event].push(cb)
    return this
  }
  
  FormStub.prototype.off = function(event, cb) {
    if (!cb) {
      delete this._listeners[event]
      delete this._onceListeners[event]
    } else if (this._listeners[event]) {
      this._listeners[event] = this._listeners[event].filter(function(c) { return c !== cb })
    }
    return this
  };
  
  // Stub methods - will be replaced by real SDK
  ['setField', 'setFields', 'getField', 'getData', 'clearField', 'clearAll', 
   'goToPage', 'nextPage', 'previousPage', 'submit', 'reset', 'toggleDarkMode', 
   'setDarkMode', 'hasError', 'getError', 'getErrors', 'isFieldVisible', 
   'getCurrentPage', 'canGoNext', 'canGoPrevious', 'focusFirstError'].forEach(function(method) {
    FormStub.prototype[method] = function() { return this }
  })
  
  window.opnform = {
    _isStub: true,
    _listeners: listeners,
    _onceListeners: onceListeners,
    _forms: forms,
    
    on: function(event, cb) {
      if (Array.isArray(event)) {
        var self = this
        event.forEach(function(e) { self.on(e, cb) })
        return this
      }
      if (!listeners[event]) listeners[event] = []
      listeners[event].push(cb)
      return this
    },
    
    once: function(event, cb) {
      if (!onceListeners[event]) onceListeners[event] = []
      onceListeners[event].push(cb)
      return this
    },
    
    off: function(event, cb) {
      if (!cb) {
        delete listeners[event]
        delete onceListeners[event]
      } else if (listeners[event]) {
        listeners[event] = listeners[event].filter(function(c) { return c !== cb })
      }
      return this
    },
    
    get: function(slug) {
      if (!forms[slug]) forms[slug] = new FormStub(slug)
      return forms[slug]
    },
    
    getAll: function() { return Object.values(forms) },
    isReady: function(slug) { return forms[slug] ? forms[slug]._ready : false },
    init: function() {}
  }
  
  // Forward SDK events to listeners
  window.addEventListener('message', function(event) {
    var data = event.data
    if (!data || typeof data !== 'object') return
    if (data.type && data.type.indexOf && data.type.indexOf('opnform:event') === 0) {
      var eventName = data.event
      var payload = data.payload || {}
      var formSlug = data.formSlug
      
      if (forms[formSlug]) {
        if (eventName === 'ready') forms[formSlug]._ready = true
        
        // Emit on form instance
        if (forms[formSlug]._listeners[eventName]) {
          forms[formSlug]._listeners[eventName].forEach(function(cb) { 
            try { cb({ form: { slug: formSlug, id: formSlug }, ...payload }) } catch(e) {} 
          })
        }
        if (forms[formSlug]._onceListeners[eventName]) {
          var cbs = forms[formSlug]._onceListeners[eventName]
          delete forms[formSlug]._onceListeners[eventName]
          cbs.forEach(function(cb) { 
            try { cb({ form: { slug: formSlug, id: formSlug }, ...payload }) } catch(e) {} 
          })
        }
      }
      
      // Emit global
      emit(eventName, { form: { slug: formSlug, id: formSlug }, ...payload })
    }
  })
})()

