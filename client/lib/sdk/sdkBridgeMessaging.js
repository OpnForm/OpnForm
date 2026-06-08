export const MSG_PREFIX = 'opnform:'
export const WILDCARD_ORIGIN = '*'

export function resolveInitialTrustedOrigin(isIframe) {
  if (!isIframe || typeof window === 'undefined') return null

  try {
    const params = new URLSearchParams(window.location.search)
    const parentOrigin = params.get('_sdkParentOrigin')
    if (parentOrigin) {
      return new URL(decodeURIComponent(parentOrigin), window.location.href).origin
    }
  } catch { /* ignore */ }

  try {
    if (window.location.ancestorOrigins?.length) {
      return window.location.ancestorOrigins[window.location.ancestorOrigins.length - 1]
    }
  } catch { /* ignore */ }

  try {
    if (document.referrer) {
      return new URL(document.referrer).origin
    }
  } catch { /* ignore */ }

  return null
}

export function readSdkTokenFromUrl() {
  if (typeof window === 'undefined') return null

  try {
    const params = new URLSearchParams(window.location.search)
    return params.get('_sdkToken') || null
  } catch {
    return null
  }
}

/**
 * Creates postMessage security handlers for the iframe SDK bridge.
 */
export function createSdkBridgeMessageHandler(options) {
  const {
    isIframe,
    getFormSlug,
    initialSdkToken = null,
    initialTrustedOrigin = null,
    postToParent,
    onCommand,
  } = options

  let trustedOrigin = initialTrustedOrigin
  let sdkToken = initialSdkToken

  function getPostTargetOrigin() {
    return trustedOrigin || WILDCARD_ORIGIN
  }

  function validateAndLockParentOrigin(event, claimedParentOrigin) {
    if (!isIframe || event.source !== window.parent) {
      return false
    }

    if (claimedParentOrigin && claimedParentOrigin !== event.origin) {
      return false
    }

    if (!trustedOrigin && event.origin) {
      trustedOrigin = event.origin
    } else if (trustedOrigin && event.origin !== trustedOrigin) {
      return false
    }

    return true
  }

  function sendResponse(requestId, success, data = null, error = null) {
    postToParent({
      type: MSG_PREFIX + 'response',
      formSlug: getFormSlug(),
      requestId,
      success,
      data,
      error,
    }, getPostTargetOrigin())
  }

  function sendHandshakeAck(success) {
    postToParent({
      type: MSG_PREFIX + 'handshake-ack',
      formSlug: getFormSlug(),
      success,
    }, getPostTargetOrigin())
  }

  function handleHandshake(message, event) {
    if (message.formSlug !== getFormSlug()) return
    if (!validateAndLockParentOrigin(event, message.parentOrigin)) return

    const incomingToken = message._sdkToken
    if (!incomingToken || typeof incomingToken !== 'string') return

    if (sdkToken && sdkToken !== incomingToken) {
      sendHandshakeAck(false)
      return
    }

    sdkToken = sdkToken || incomingToken
    sendHandshakeAck(true)
  }

  function onMessage(event) {
    if (isIframe) {
      if (event.source !== window.parent) return
    } else if (event.source !== window) {
      return
    }

    const data = event.data
    if (!data || typeof data !== 'object') return

    if (data.type === MSG_PREFIX + 'handshake') {
      if (isIframe) {
        handleHandshake(data, event)
      }
      return
    }

    if (data.type === MSG_PREFIX + 'command') {
      if (isIframe && !validateAndLockParentOrigin(event)) {
        return
      }

      if (isIframe && (!sdkToken || data._sdkToken !== sdkToken)) {
        sendResponse(data.requestId, false, null, 'Invalid SDK token')
        return
      }

      onCommand(data)
    }
  }

  return {
    onMessage,
    sendResponse,
    getTrustedOrigin: () => trustedOrigin,
    getSdkToken: () => sdkToken,
    getPostTargetOrigin,
  }
}
