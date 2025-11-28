/* eslint-disable */
/**
 * Composable for handling window message communication
 * Provides a consistent interface for cross-window/iframe communication
 */

import { ref, onUnmounted } from 'vue'

// Define common message types as constants
export const WindowMessageTypes = {
  LOGIN_COMPLETE: 'login-complete',
  AFTER_LOGIN: 'after-login',
  OAUTH_PROVIDER_CONNECTED: 'oauth-provider-connected'
}

export const useWindowMessage = (messageType = null) => {
  const listeners = ref(new Map())
  
  /**
   * Derives an acknowledgment message name from the original message name
   * @param {string} originalMessage - The original message name
   * @returns {string} - The derived acknowledgment message name
   */
  const deriveAcknowledgmentName = (originalMessage) => {
    return `${originalMessage}-acknowledged`
  }
  
  /**
   * Add a listener for a specific message type
   * 
   * @param {Function} callback - The callback function to call when the message is received
   * @param {Object} options - Options for the listener
   * @param {boolean} options.useMessageChannel - Ignored; maintained for API compatibility
   * @param {boolean} options.acknowledge - Whether to automatically acknowledge the message
   * @param {string|null} eventType - Override event type (defaults to constructor value)
   */
  const listen = (callback, options = {}, eventType = null) => {
    const targetEventType = eventType || messageType
    if (!targetEventType) {
      console.error('No message type provided to listen for')
      return
    }
    
    const { 
      // kept for API compatibility; no effect with BroadcastChannel
      useMessageChannel = true, 
      acknowledge = true 
    } = options
    
    const acknowledgmentName = deriveAcknowledgmentName(targetEventType)

    // Create a BroadcastChannel per message type
    let channel
    try {
      channel = new BroadcastChannel(targetEventType)
    } catch (err) {
      console.error('BroadcastChannel is not supported in this environment', err)
      return
    }

    const handler = (event) => {
      // Only react to our expected payload (the message name string)
      if (event && event.data === targetEventType) {
        // Send acknowledgement if requested (best-effort)
        if (acknowledge) {
          try {
            const ackChannel = new BroadcastChannel(acknowledgmentName)
            ackChannel.postMessage(acknowledgmentName)
            // Close quickly to avoid leaks
            setTimeout(() => {
              try { ackChannel.close() } catch { /* ignore */ }
            }, 50)
          } catch (e) {
            // ignore ack failures
          }
        }
        callback(event)
      }
    }

    channel.onmessage = handler

    // Store for cleanup (channel + handler)
    listeners.value.set(targetEventType, { channel, handler })
    
    // Return a function to remove the listener
    return () => stopListening(targetEventType)
  }
  
  /**
   * Remove a listener for a specific message type
   * 
   * @param {string} eventType - The type of message to stop listening for
   */
  const stopListening = (eventType = null) => {
    const targetEventType = eventType || messageType
    if (!targetEventType) {
      console.error('No message type provided to stop listening for')
      return
    }
    
    const record = listeners.value.get(targetEventType)
    if (record && record.channel) {
      try { record.channel.close() } catch { /* ignore */ }
    }
    listeners.value.delete(targetEventType)
  }
  
  /**
   * Send a message
   * 
   * @param {Window} _targetWindow - Ignored; maintained for API compatibility
   * @param {Object} options - Options for sending the message
   * @param {string} options.eventType - The type of message to send (defaults to constructor value)
   * @param {string} options.targetOrigin - Ignored; maintained for API compatibility
   * @param {boolean} options.useMessageChannel - Ignored; maintained for API compatibility
   * @param {number} options.timeout - Timeout in ms for the acknowledgment (best-effort), defaults to 500ms
   * @param {boolean} options.waitForAcknowledgment - Whether to wait for acknowledgment
   * @returns {Promise} - Resolves true if acknowledged, false on timeout, or resolves immediately when not waiting
   */
  const send = (_targetWindow, options = {}) => {
    const { 
      eventType = null,
      targetOrigin = '*', // unused with BroadcastChannel
      useMessageChannel = true, // unused with BroadcastChannel
      timeout = 500,
      waitForAcknowledgment = true
    } = options
    
    const targetEventType = eventType || messageType
    if (!targetEventType) {
      console.error('No message type provided to send')
      return Promise.reject(new Error('No message type provided'))
    }
    
    const acknowledgmentName = deriveAcknowledgmentName(targetEventType)

    // Helper to post the message
    const postMessage = () => {
      let channel
      try {
        channel = new BroadcastChannel(targetEventType)
      } catch (err) {
        console.error('BroadcastChannel is not supported in this environment', err)
        return null
      }
      channel.postMessage(targetEventType)
      // Close shortly after posting
      setTimeout(() => {
        try { channel.close() } catch { /* ignore */ }
      }, 50)
      return true
    }

    if (!waitForAcknowledgment) {
      postMessage()
      return Promise.resolve(true)
    }

    // Wait for ack on acknowledgment channel
    return new Promise((resolve) => {
      let ackChannel
      let resolved = false
      try {
        ackChannel = new BroadcastChannel(acknowledgmentName)
      } catch (err) {
        // If BC not supported, fallback to fire-and-forget
        postMessage()
        resolve(false)
        return
      }

      const finalize = (value) => {
        if (resolved) return
        resolved = true
        try { ackChannel.close() } catch { /* ignore */ }
        resolve(value)
      }

      ackChannel.onmessage = (event) => {
        if (event && event.data === acknowledgmentName) {
          finalize(true)
        }
      }

      // Post after listener is attached
      postMessage()

      // Timeout to avoid hanging
      setTimeout(() => finalize(false), timeout)
    })
  }
  
  /**
   * Cleanup all listeners
   * Call this explicitly if needed, though it's automatically called on unmount
   */
  const cleanup = () => {
    listeners.value.forEach((record) => {
      try { record.channel.close() } catch { /* ignore */ }
    })
    listeners.value.clear()
  }
  
  // Auto-cleanup when the component is unmounted
  onUnmounted(() => {
    cleanup()
  })
  
  // If messageType was provided on creation, set up a default listener
  const setupDefaultListener = (callback, options = {}) => {
    if (messageType && callback) {
      return listen(callback, options)
    }
  }
  
  return {
    listen,
    stopListening,
    send,
    cleanup,
    setupDefaultListener
  }
} 