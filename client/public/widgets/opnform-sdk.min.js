/**
 * OpnForm JavaScript SDK
 * Enables programmatic interaction with embedded OpnForm forms
 * Includes iFrame Resizer for automatic height adjustment
 * @version 1.0.0
 */

// ============================================================================
// iFrame Resizer v4.3.2 - (c) 2021 David J. Bradshaw - MIT License
// https://github.com/davidjbradshaw/iframe-resizer
// ============================================================================
!function(e){var n,i,t,o,r,a,s,l,f,c,d,u,m,g;function h(){return window.MutationObserver||window.WebKitMutationObserver||window.MozMutationObserver}function p(e,n,i){e.addEventListener(n,i,!1)}function b(e,n,i){e.removeEventListener(n,i,!1)}function y(e){return c[e]?c[e].log:i}function v(e,n){_("log",e,n,y(e))}function $(e,n){_("info",e,n,y(e))}function w(e,n){_("warn",e,n,!0)}function _(e,n,i,t){var o,a;!0===t&&"object"==typeof window.console&&console[e](r+"["+(a="Host page: "+(o=n),a=window.top!==window.self?window.parentIFrame&&window.parentIFrame.getId?window.parentIFrame.getId()+": "+o:"Nested host page: "+o:a)+"]",i)}function x(e){function n(){i("Height"),i("Width"),O(function(){E(j),I(L),h("onResized",j)},j,"init")}function i(e){var n=Number(c[L]["max"+e]),i=Number(c[L]["min"+e]),e=e.toLowerCase(),t=Number(j[e]);v(L,"Checking "+e+" is in range "+i+"-"+n),t<i&&(t=i,v(L,"Set "+e+" to min value")),n<t&&(t=n,v(L,"Set "+e+" to max value")),j[e]=""+t}function t(e){return P.slice(P.indexOf(":")+o+e)}function l(e,n){var i,t,o;i=function(){var i,t;T("Send Page Info","pageInfo:"+(i=document.body.getBoundingClientRect(),JSON.stringify({iframeHeight:(t=j.iframe.getBoundingClientRect()).height,iframeWidth:t.width,clientHeight:Math.max(document.documentElement.clientHeight,window.innerHeight||0),clientWidth:Math.max(document.documentElement.clientWidth,window.innerWidth||0),offsetTop:parseInt(t.top-i.top,10),offsetLeft:parseInt(t.left-i.left,10),scrollTop:window.pageYOffset,scrollLeft:window.pageXOffset,documentHeight:document.documentElement.clientHeight,documentWidth:document.documentElement.clientWidth,windowHeight:window.innerHeight,windowWidth:window.innerWidth})),e,n)},t=32,m[o=n]||(m[o]=setTimeout(function(){m[o]=null,i()},t))}function f(e){return e=e.getBoundingClientRect(),M(L),{x:Math.floor(Number(e.left)+Number(s.x)),y:Math.floor(Number(e.top)+Number(s.y))}}function d(e){var n=e?f(j.iframe):{x:0,y:0},i={x:Number(j.width)+n.x,y:Number(j.height)+n.y};v(L,"Reposition requested from iFrame (offset x:"+n.x+" y:"+n.y+")"),window.top===window.self?(s=i,u(),v(L,"--")):window.parentIFrame?window.parentIFrame["scrollTo"+(e?"Offset":"")](i.x,i.y):w(L,"Unable to scroll to requested position, window.parentIFrame not found")}function u(){!1===h("onScroll",s)?R():I(L)}function g(e){var n,i={};i=0===Number(j.width)&&0===Number(j.height)?{x:(n=t(9).split(":"))[1],y:n[0]}:{x:j.width,y:j.height},h(e,{iframe:j.iframe,screenX:Number(i.x),screenY:Number(i.y),type:j.type})}function h(e,n){return k(L,e,n)}var y,_,x,z,H,S,P=e.data,j={},L=null;if("[iFrameResizerChild]Ready"===P)for(var A in c)T("iFrame requested init",C(A),c[A].iframe,A);else r===(""+P).slice(0,a)&&P.slice(a).split(":")[0]in c?(c[L=(j=(_=(y=P.slice(a).split(":"))[1]?parseInt(y[1],10):0,z=getComputedStyle(x=c[y[0]]&&c[y[0]].iframe),{iframe:x,id:y[0],height:_+function(e){if("border-box"!==e.boxSizing)return 0;var n=e.paddingTop?parseInt(e.paddingTop,10):0,e=e.paddingBottom?parseInt(e.paddingBottom,10):0;return n+e}(z)+function(e){if("border-box"!==e.boxSizing)return 0;var n=e.borderTopWidth?parseInt(e.borderTopWidth,10):0,e=e.borderBottomWidth?parseInt(e.borderBottomWidth,10):0;return n+e}(z),width:y[2],type:y[3]})).id]&&(c[L].loaded=!0),(S=j.type in{true:1,false:1,undefined:1})&&v(L,"Ignoring init message from meta parent page"),!S&&(S=!0,c[H=L]||(S=!1,w(j.type+" No settings for "+H+". Message was: "+P)),S)&&(v(L,"Received: "+P),H=!0,null===j.iframe&&(w(L,"IFrame ("+j.id+") not found"),H=!1),H&&function n(){var i=e.origin,t=c[L]&&c[L].checkOrigin;if(t&&""+i!="null"&&!function(){if(t.constructor!==Array)return e=c[L]&&c[L].remoteHost,v(L,"Checking connection is from: "+e),i===e;var e,n=0,o=!1;for(v(L,"Checking connection is from allowed list of origins: "+t);n<t.length;n++)if(t[n]===i){o=!0;break}return o}())throw Error("Unexpected message received from: "+i+" for "+j.iframe.id+". Message was: "+e.data+". This error can be disabled by setting the checkOrigin: false option or by providing of array of trusted domains.");return 1}()&&function e(){var i,o,r,a,m;switch(c[L]&&c[L].firstRun&&c[L]&&(c[L].firstRun=!1),j.type){case"close":F(j.iframe);break;case"message":o=t(6),v(L,"onMessage passed: {iframe: "+j.iframe.id+", message: "+o+"}"),h("onMessage",{iframe:j.iframe,message:JSON.parse(o)}),v(L,"--");break;case"mouseenter":g("onMouseEnter");break;case"mouseleave":g("onMouseLeave");break;case"autoResize":c[L].autoResize=JSON.parse(t(9));break;case"scrollTo":d(!1);break;case"scrollToOffset":d(!0);break;case"pageInfo":l(c[L]&&c[L].iframe,L),i=L,y("Add ",p),c[i]&&(c[i].stopPageInfo=$);break;case"pageInfoStop":c[L]&&c[L].stopPageInfo&&(c[L].stopPageInfo(),delete c[L].stopPageInfo);break;case"inPageLink":t(9),m=decodeURIComponent(a=a.split("#")[1]||""),(m=document.getElementById(m)||document.getElementsByName(m)[0])?(m=f(m),v(L,"Moving to in page link (#"+a+") at x: "+m.x+" y: "+m.y),s={x:m.x,y:m.y},u(),v(L,"--")):window.top===window.self?v(L,"In page link #"+a+" not found"):window.parentIFrame?window.parentIFrame.moveToAnchor(a):v(L,"In page link #"+a+" not found and window.parentIFrame not found");break;case"reset":W(j);break;case"init":n(),h("onInit",j.iframe);break;default:0===Number(j.width)&&0===Number(j.height)?w("Unsupported message received ("+j.type+"), this is likely due to the iframe containing a later version of iframe-resizer than the parent page"):n()}function y(e,n){function t(){c[i]?l(c[i].iframe,i):$()}["scroll","resize"].forEach(function(o){v(i,e+o+" listener for sendPageInfo"),n(window,o,t)})}function $(){y("Remove ",b)}}())):$(L,"Ignored: "+P)}function k(e,n,i){var t=null,o=null;if(c[e]){if("function"!=typeof(t=c[e][n]))throw TypeError(n+" on iFrame["+e+"] is not a function");o=t(i)}return o}function z(e){delete c[e=e.id]}function F(e){var n=e.id;if(!1===k(n,"onClose",n))v(n,"Close iframe cancelled by onClose event");else{v(n,"Removing iFrame: "+n);try{e.parentNode&&e.parentNode.removeChild(e)}catch(i){w(i)}k(n,"onClosed",n),v(n,"--"),z(e)}}function M(n){null===s&&v(n,"Get page position: "+(s={x:window.pageXOffset===e?document.documentElement.scrollLeft:window.pageXOffset,y:window.pageYOffset===e?document.documentElement.scrollTop:window.pageYOffset}).x+","+s.y)}function I(e){null!==s&&(window.scrollTo(s.x,s.y),v(e,"Set page position: "+s.x+","+s.y),R())}function R(){s=null}function W(e){v(e.id,"Size reset requested by "+("init"===e.type?"host page":"iFrame")),M(e.id),O(function(){E(e),T("reset","reset",e.iframe,e.id)},e,"reset")}function E(e){function n(n){var o;o=n,e.id?(e.iframe.style[o]=e[o]+"px",v(e.id,"IFrame ("+i+") "+o+" set to "+e[o]+"px")):v("undefined","messageData id not set"),function n(o){var r;function a(){Object.keys(c).forEach(function(e){var n;function i(e){return"0px"===(c[n]&&c[n].iframe.style[e])}c[n=e]&&null!==c[n].iframe.offsetParent&&(i("height")||i("width"))&&T("Visibility change","resize",c[n].iframe,n)})}!t&&"0"===e[o]&&(t=!0,v(i,"Hidden iFrame detected, creating visibility listener"),o=h())&&(r=document.querySelector("body"),new o(function e(n){v("window","Mutation observed: "+n[0].target+" "+n[0].type),S(a,16)}).observe(r,{attributes:!0,attributeOldValue:!1,characterData:!0,characterDataOldValue:!1,childList:!0,subtree:!0}))}(n)}var i=e.iframe.id;c[i]&&(c[i].sizeHeight&&n("height"),c[i].sizeWidth)&&n("width")}function O(e,n,i){i!==n.type&&l&&!window.jasmine?(v(n.id,"Requesting animation frame"),l(e)):e()}function T(e,n,i,t,o){var a,s=!1;c[t=t||i.id]&&(i&&"contentWindow"in i&&null!==i.contentWindow?(a=c[t]&&c[t].targetOrigin,v(t,"["+e+"] Sending msg to iframe["+t+"] ("+n+") targetOrigin: "+a),i.contentWindow.postMessage(r+n,a)):w(t,"["+e+"] IFrame("+t+") not found"),o&&c[t]&&c[t].warningTimeout&&(c[t].msgTimeout=setTimeout(function(){!c[t]||c[t].loaded||s||(s=!0,w(t,"IFrame has not responded within "+c[t].warningTimeout/1e3+" seconds. Check iFrameResizer.contentWindow.js has been loaded in iFrame. This message can be ignored if everything is working, or you can set the warningTimeout option to a higher value or zero to suppress this warning."))},c[t].warningTimeout)))}function C(e){return e+":"+c[e].bodyMarginV1+":"+c[e].sizeWidth+":"+c[e].log+":"+c[e].interval+":"+c[e].enablePublicMethods+":"+c[e].autoResize+":"+c[e].bodyMargin+":"+c[e].heightCalculationMethod+":"+c[e].bodyBackground+":"+c[e].bodyPadding+":"+c[e].tolerance+":"+c[e].inPageLinks+":"+c[e].resizeFrom+":"+c[e].widthCalculationMethod+":"+c[e].mouseEvents}function H(t,o){function r(e){var n=e.split("Callback");2===n.length&&(this[n="on"+n[0].charAt(0).toUpperCase()+n[0].slice(1)]=this[e],delete this[e],w(l,"Deprecated: '"+e+"' has been renamed '"+n+"'. The old method will be removed in the next major version."))}var a,s,l=function(e){var r;if("string"!=typeof e)throw TypeError("Invaild id for iFrame. Expected String");return""===e&&(t.id=(r=o&&o.id||u.id+n++,null!==document.getElementById(r)&&(r+=n++),e=r),i=(o||{}).log,v(e,"Added missing iframe ID: "+e+" ("+t.src+")")),e}(t.id);if(l in c&&"iFrameResizer"in t)w(l,"Ignored iFrame, already setup.");else{switch(function e(n){if(n=n||{},c[l]=Object.create(null),c[l].iframe=t,c[l].firstRun=!0,c[l].remoteHost=t.src&&t.src.split("/").slice(0,3).join("/"),"object"!=typeof n)throw TypeError("Options is not an object");Object.keys(n).forEach(r,n);var i,o=n;for(i in u)Object.prototype.hasOwnProperty.call(u,i)&&(c[l][i]=(Object.prototype.hasOwnProperty.call(o,i)?o:u)[i]);c[l]&&(c[l].targetOrigin=!0!==c[l].checkOrigin||""===(n=c[l].remoteHost)||null!==n.match(/^(about:blank|javascript:|file:\/\/)/)?'*':n)}(o),v(l,"IFrame scrolling "+(c[l]&&c[l].scrolling?"enabled":"disabled")+" for "+l),t.style.overflow=!1===(c[l]&&c[l].scrolling)?"hidden":"auto",c[l]&&c[l].scrolling){case"omit":break;case!0:t.scrolling="yes";break;case!1:t.scrolling="no";break;default:t.scrolling=c[l]?c[l].scrolling:"no"}m("Height"),m("Width"),d("maxHeight"),d("minHeight"),d("maxWidth"),d("minWidth"),"number"!=typeof(c[l]&&c[l].bodyMargin)&&"0"!==(c[l]&&c[l].bodyMargin)||(c[l].bodyMarginV1=c[l].bodyMargin,c[l].bodyMargin=c[l].bodyMargin+"px"),a=C(l),(s=h())&&t.parentNode&&new s(function(e){e.forEach(function(e){Array.prototype.slice.call(e.removedNodes).forEach(function(e){e===t&&F(t)})})}).observe(t.parentNode,{childList:!0}),p(t,"load",function(){var n,i;T("iFrame.onload",a,t,e,!0),n=c[l]&&c[l].firstRun,i=c[l]&&c[l].heightCalculationMethod in f,!n&&i&&W({iframe:t,height:0,width:0,type:"init"})}),T("init",a,t,e,!0),c[l]&&(c[l].iframe.iFrameResizer={close:F.bind(null,c[l].iframe),removeListeners:z.bind(null,c[l].iframe),resize:T.bind(null,"Window resize","resize",c[l].iframe),moveToAnchor:function(e){T("Move to anchor","moveToAnchor:"+e,c[l].iframe,l)},sendMessage:function(e){T("Send Message","message:"+(e=JSON.stringify(e)),c[l].iframe,l)}})}function d(e){var n=c[l][e];1/0!==n&&0!==n&&(t.style[e]="number"==typeof n?n+"px":n,v(l,"Set "+e+" = "+t.style[e]))}function m(e){if(c[l]["min"+e]>c[l]["max"+e])throw Error("Value for min"+e+" can not be greater than max"+e)}}function S(e,n){null===d&&(d=setTimeout(function(){d=null,e()},n))}function P(){"hidden"!==document.visibilityState&&(v("document","Trigger event: Visibility change"),S(function(){j("Tab Visible","resize")},16))}function j(e,n){Object.keys(c).forEach(function(i){var t;c[t=i]&&"parent"===c[t].resizeFrom&&c[t].autoResize&&!c[t].firstRun&&T(e,n,c[i].iframe,i)})}function L(){function n(e,n){if(n){if(!n.tagName)throw TypeError("Object is not a valid DOM element");if("IFRAME"!==n.tagName.toUpperCase())throw TypeError("Expected <IFRAME> tag, found <"+n.tagName+">");H(n,e),i.push(n)}}for(var i,t=["moz","webkit","o","ms"],o=0;o<t.length&&!l;o+=1)l=window[t[o]+"RequestAnimationFrame"];return l?l=l.bind(window):v("setup","RequestAnimationFrame not supported"),p(window,"message",x),p(window,"resize",function(){var e;v("window","Trigger event: "+(e="resize")),S(function(){j("Window "+e,"resize")},16)}),p(document,"visibilitychange",P),p(document,"-webkit-visibilitychange",P),function(t,o){var r;switch(i=[],(r=t)&&r.enablePublicMethods&&w("enablePublicMethods option has been removed, public methods are now always available in the iFrame"),typeof o){case"undefined":case"string":Array.prototype.forEach.call(document.querySelectorAll(o||"iframe"),n.bind(e,t));break;case"object":n(t,o);break;default:throw TypeError("Unexpected data type ("+typeof o+")")}return i}}"undefined"!=typeof window&&(n=0,t=i=!1,o=7,a=(r="[iFrameSizer]").length,s=null,l=window.requestAnimationFrame,f=Object.freeze({max:1,scroll:1,bodyScroll:1,documentElementScroll:1}),c={},d=null,u=Object.freeze({autoResize:!0,bodyBackground:null,bodyMargin:null,bodyMarginV1:8,bodyPadding:null,checkOrigin:!0,inPageLinks:!1,enablePublicMethods:!0,heightCalculationMethod:"bodyOffset",id:"iFrameResizer",interval:32,log:!1,maxHeight:1/0,maxWidth:1/0,minHeight:0,minWidth:0,mouseEvents:!0,resizeFrom:"parent",scrolling:!1,sizeHeight:!0,sizeWidth:!1,warningTimeout:5e3,tolerance:0,widthCalculationMethod:"scroll",onClose:function(){return!0},onClosed:function(){},onInit:function(){},onMessage:function(){w("onMessage function not defined")},onMouseEnter:function(){},onMouseLeave:function(){},onResized:function(){},onScroll:function(){return!0}}),m={},window.jQuery!==e&&((g=window.jQuery).fn?g.fn.iFrameResize||(g.fn.iFrameResize=function(e){return this.filter("iframe").each(function(n,i){H(i,e)}).end()}):$("","Unable to bind to jQuery, it is not fully loaded.")),"function"==typeof define&&define.amd?define([],L):"object"==typeof module&&"object"==typeof module.exports&&(module.exports=L()),window.iFrameResize=window.iFrameResize||L())}();

// ============================================================================
// OpnForm SDK
// ============================================================================
(function (global) {
  'use strict'

  // Message protocol prefix
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
   * Form Instance - represents a single embedded form
   */
  class FormInstance extends EventEmitter {
    constructor(iframe, slug, sdk) {
      super()
      this.iframe = iframe
      this.slug = slug
      this.id = iframe.id || slug
      this._sdk = sdk
      this._ready = false
      this._pendingRequests = {}
      this._requestId = 0
      this._cachedData = {}
      this._cachedErrors = {}
      this._darkMode = false
      this._currentPage = { index: 0, total: 1 }
      this._popupOpen = false
      this._resizeInitialized = false
    }

    isReady() { return this._ready }

    _sendCommand(command, payload = {}) {
      if (!this.iframe || !this.iframe.contentWindow) {
        console.warn('[OpnForm SDK] Cannot send command - iframe not available')
        return Promise.reject(new Error('Iframe not available'))
      }

      const requestId = ++this._requestId
      const message = {
        type: MSG_PREFIX + 'command',
        command: command,
        formSlug: this.slug,
        requestId: requestId,
        payload: payload
      }

      return new Promise((resolve, reject) => {
        this._pendingRequests[requestId] = { resolve, reject }
        setTimeout(() => {
          if (this._pendingRequests[requestId]) {
            delete this._pendingRequests[requestId]
            reject(new Error('Command timeout'))
          }
        }, 5000)
        this.iframe.contentWindow.postMessage(message, '*')
      })
    }

    _handleResponse(response) {
      const pending = this._pendingRequests[response.requestId]
      if (pending) {
        delete this._pendingRequests[response.requestId]
        if (response.success) {
          pending.resolve(response.data)
        } else {
          pending.reject(new Error(response.error || 'Command failed'))
        }
      }
    }

    // --- Field Operations ---
    getField(fieldId) { return this._cachedData[fieldId] }
    setField(fieldId, value) { return this._sendCommand('setField', { fieldId, value }) }
    setFields(data) { return this._sendCommand('setFields', { data }) }
    getData() { return { ...this._cachedData } }
    clearField(fieldId) { return this._sendCommand('clearField', { fieldId }) }
    clearAll() { return this._sendCommand('clearAll', {}) }

    // --- Field State ---
    hasError(fieldId) { return !!this._cachedErrors[fieldId] }
    getError(fieldId) { return this._cachedErrors[fieldId] || null }
    getErrors() { return { ...this._cachedErrors } }
    isFieldVisible(fieldId) { return this._sendCommand('isFieldVisible', { fieldId }) }

    // --- Theme ---
    toggleDarkMode() { return this.setDarkMode(!this._darkMode) }
    setDarkMode(enabled) {
      this._darkMode = enabled === 'auto' ? null : !!enabled
      return this._sendCommand('setDarkMode', { enabled })
    }
    isDarkMode() { return this._darkMode }
    setTheme(theme) { return this._sendCommand('setTheme', { theme }) }

    // --- Navigation ---
    goToPage(index) { return this._sendCommand('goToPage', { index }) }
    nextPage() { return this._sendCommand('nextPage', {}) }
    previousPage() { return this._sendCommand('previousPage', {}) }
    getCurrentPage() { return { ...this._currentPage } }
    canGoNext() { return this._currentPage.index < this._currentPage.total - 1 }
    canGoPrevious() { return this._currentPage.index > 0 }

    // --- Actions ---
    submit() { return this._sendCommand('submit', {}) }
    reset() { return this._sendCommand('reset', {}) }
    focusFirstError() { return this._sendCommand('focusFirstError', {}) }

    // --- Popup Control ---
    open() {
      const mainDiv = document.querySelector('.nf-main')
      if (mainDiv) {
        mainDiv.classList.add('open')
        this._popupOpen = true
        this.emit(EVENTS.SHOW, { form: this._getFormInfo() })
      }
    }

    close() {
      const mainDiv = document.querySelector('.nf-main')
      if (mainDiv) {
        mainDiv.classList.remove('open')
        this._popupOpen = false
        this.emit(EVENTS.HIDE, { form: this._getFormInfo() })
      }
    }

    toggle() {
      if (this._popupOpen) { this.close() } else { this.open() }
    }

    isOpen() { return this._popupOpen }

    _getFormInfo() {
      return { slug: this.slug, id: this.id }
    }

    /**
     * Initialize auto-resize for this form's iframe
     */
    initResize() {
      if (this._resizeInitialized) return
      if (global.iFrameResize && this.iframe) {
        global.iFrameResize({ log: false, checkOrigin: false }, this.iframe)
        this._resizeInitialized = true
      }
    }
  }

  /**
   * Main OpnForm SDK
   */
  class OpnFormSDK extends EventEmitter {
    constructor() {
      super()
      this._forms = {}
      this._options = {
        autoResize: true,
        defaultDarkMode: 'auto',
        preventRedirect: false
      }
      this._initialized = false
      this._messageHandler = this._handleMessage.bind(this)
      
      if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => this._autoInit())
      } else {
        this._autoInit()
      }
    }

    init(options = {}) {
      this._options = { ...this._options, ...options }
      
      if (!this._initialized) {
        window.addEventListener('message', this._messageHandler)
        this._initialized = true
      }

      this._discoverForms()

      if (this._options.onReady && Object.keys(this._forms).length > 0) {
        const readyForms = Object.values(this._forms).filter(f => f.isReady())
        if (readyForms.length > 0) {
          this._options.onReady(readyForms)
        }
      }
    }

    _autoInit() {
      window.addEventListener('message', this._messageHandler)
      this._initialized = true
      this._discoverForms()
    }

    _discoverForms() {
      const iframes = document.querySelectorAll('iframe')
      iframes.forEach(iframe => {
        const src = iframe.src || ''
        if (src.includes('/forms/') && !this._forms[iframe.id]) {
          const slugMatch = src.match(/\/forms\/([^/?#]+)/)
          const slug = slugMatch ? slugMatch[1] : iframe.id
          if (slug) {
            this._registerForm(iframe, slug)
          }
        }
      })
    }

    _registerForm(iframe, slug) {
      const instance = new FormInstance(iframe, slug, this)
      this._forms[slug] = instance
      
      if (iframe.id && iframe.id !== slug) {
        this._forms[iframe.id] = instance
      }

      // Auto-initialize resize if enabled
      if (this._options.autoResize !== false) {
        // Wait for iframe to be ready
        if (iframe.contentWindow) {
          instance.initResize()
        } else {
          iframe.addEventListener('load', () => instance.initResize())
        }
      }

      return instance
    }

    _handleMessage(event) {
      const data = event.data
      if (!data || typeof data !== 'object') return

      // Handle legacy form-submitted event
      if (data.type === 'form-submitted') {
        this._handleLegacySubmit(data)
        return
      }

      // Handle SDK events
      if (data.type && data.type.startsWith && data.type.startsWith(MSG_PREFIX)) {
        const messageType = data.type.replace(MSG_PREFIX, '')
        if (messageType === 'event') {
          this._handleEvent(data)
        } else if (messageType === 'response') {
          this._handleResponse(data)
        }
      }
    }

    _handleLegacySubmit(data) {
      const formSlug = data.form?.slug
      const form = this._forms[formSlug]
      
      const eventData = {
        form: { slug: formSlug, id: data.form?.id },
        data: data.submission_data || {},
        submissionId: data.submission_id,
        completionTime: data.completion_time
      }

      if (form) {
        form._cachedData = eventData.data
        form.emit(EVENTS.SUBMIT, eventData)
      }

      this.emit(EVENTS.SUBMIT, eventData)

      if (!this._options.preventRedirect && data.form?.redirect_target_url) {
        window.top.location.href = data.form.redirect_target_url
      }
    }

    _handleEvent(message) {
      const { event, formSlug, payload } = message
      const form = this._forms[formSlug]

      if (!form) return

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
          form._cachedErrors = payload.errors || {}
          break
        case EVENTS.SUBMIT:
          form._cachedData = payload.data || {}
          break
        case EVENTS.SUBMIT_ERROR:
          form._cachedErrors = payload.errors || {}
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

      const eventData = { form: form._getFormInfo(), ...payload }
      form.emit(event, eventData)
      this.emit(event, eventData)

      if (event === EVENTS.READY && this._options.onReady) {
        const allForms = Object.values(this._forms)
        const uniqueForms = [...new Set(allForms)]
        if (uniqueForms.every(f => f.isReady())) {
          this._options.onReady(uniqueForms)
        }
      }
    }

    _handleResponse(message) {
      const { formSlug, requestId, success, data, error } = message
      const form = this._forms[formSlug]
      if (form) {
        form._handleResponse({ requestId, success, data, error })
      }
    }

    get(slugOrId) { return this._forms[slugOrId] || null }

    getAll() {
      const instances = Object.values(this._forms)
      return [...new Set(instances)]
    }

    isReady(slugOrId) {
      const form = this._forms[slugOrId]
      return form ? form.isReady() : false
    }

    create(slug, options = {}) {
      const { container, width = '100%', height = 'auto', darkMode, onSubmit } = options

      let containerEl = container
      if (typeof container === 'string') {
        containerEl = document.querySelector(container)
      }

      if (!containerEl) {
        console.error('[OpnForm SDK] Container element not found')
        return null
      }

      const iframe = document.createElement('iframe')
      iframe.id = slug
      iframe.style.border = 'none'
      iframe.style.width = width
      iframe.style.height = height === 'auto' ? '600px' : height

      let url = `/forms/${slug}`
      if (darkMode !== undefined) {
        url += `?darkMode=${darkMode}`
      }
      iframe.src = url

      containerEl.appendChild(iframe)

      const form = this._registerForm(iframe, slug)

      if (onSubmit) {
        form.on(EVENTS.SUBMIT, onSubmit)
      }

      return form
    }

    /**
     * Initialize auto-resize for a specific form
     */
    initAutoResize(slugOrId) {
      const form = this._forms[slugOrId]
      if (form) {
        form.initResize()
      } else if (global.iFrameResize) {
        const selector = '#' + slugOrId
        global.iFrameResize({ log: false, checkOrigin: false }, selector)
      }
    }
  }

  // Create singleton instance
  const opnform = new OpnFormSDK()

  // Expose to global scope
  global.opnform = opnform

  // Backward compatibility with initEmbed
  if (!global.initEmbed) {
    global.initEmbed = function(formSlug, options = {}) {
      opnform.init(options)
      
      const form = opnform.get(formSlug)
      if (form) {
        form.on('submit', (data) => {
          if (data.form?.redirect_target_url) {
            window.top.location.href = data.form.redirect_target_url
          }
        })
      }

      // Auto-resize is now handled automatically by the SDK
      if (options.autoResize !== false) {
        opnform.initAutoResize(formSlug)
      }
    }
  }

})(typeof window !== 'undefined' ? window : this)
