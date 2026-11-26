import { describe, expect, it } from 'vitest'
import { isAnonymousFormMessageCloneError } from '../../lib/telemetry/custom-message-event-error.js'

describe('isAnonymousFormMessageCloneError', () => {
  function eventWithFrame (filename: string) {
    return {
      transaction: '/forms/:slug()',
      exception: {
        values: [{
          type: 'DataCloneError',
          value: "Failed to execute 'postMessage' on 'Window': MessageEvent object could not be cloned.",
          mechanism: { type: 'auto.browser.browserapierrors.addEventListener' },
          stacktrace: { frames: [{ filename }] },
        }],
      },
    }
  }

  it('filters the inline form message listener signature', () => {
    expect(isAnonymousFormMessageCloneError(eventWithFrame('<anonymous>'))).toBe(true)
  })

  it('keeps equivalent errors from bundled code reportable', () => {
    expect(isAnonymousFormMessageCloneError(eventWithFrame('/_nuxt/form.js'))).toBe(false)
  })
})
