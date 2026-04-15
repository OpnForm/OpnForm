// @vitest-environment happy-dom

import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { ref, computed, nextTick } from 'vue'
import { usePendingSubmission } from '../../lib/forms/composables/usePendingSubmission.js'

const { storageRefs } = vi.hoisted(() => ({
  storageRefs: new Map()
}))

vi.mock('@vueuse/core', async () => {
  const { ref, watch } = await import('vue')

  return {
    useStorage: (key, defaultValue = null) => {
      if (!storageRefs.has(key)) {
        storageRefs.set(key, ref(defaultValue))
      }

      return storageRefs.get(key)
    },
    watchThrottled: (source, callback, options = {}) => {
      return watch(source, (value, oldValue, onCleanup) => {
        callback(value, oldValue, onCleanup)
      }, options)
    }
  }
})

describe('usePendingSubmission', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    storageRefs.clear()
    window.history.replaceState({}, '', 'https://example.com/forms/test')
  })

  afterEach(() => {
    vi.useRealTimers()
    storageRefs.clear()
  })

  function createPendingSubmission(configOverrides = {}, initialFormData = {}) {
    const formConfig = ref({
      form_pending_submission_key: 'pending-submission-test',
      auto_save: false,
      enable_partial_submissions: false,
      ...configOverrides
    })
    const formData = ref(initialFormData)
    const pendingSubmission = usePendingSubmission(formConfig, computed(() => formData.value))

    return {
      formData,
      pendingSubmission
    }
  }

  async function flushAutosave() {
    await nextTick()
    await nextTick()
  }

  it('stores submission hash when only partial submissions are enabled', () => {
    const { pendingSubmission } = createPendingSubmission({
      auto_save: false,
      enable_partial_submissions: true
    })

    pendingSubmission.setSubmissionHash('submission-hash-1')

    expect(pendingSubmission.getSubmissionHash()).toBe('submission-hash-1')
    expect(pendingSubmission.get()).toEqual({
      submission_hash: 'submission-hash-1'
    })
  })

  it('preserves submission hash when autosave updates the stored draft', async () => {
    const { formData, pendingSubmission } = createPendingSubmission({
      auto_save: true,
      enable_partial_submissions: true
    }, {
      name: 'Initial value'
    })

    pendingSubmission.setSubmissionHash('submission-hash-2')
    formData.value = {
      name: 'Updated value'
    }

    await flushAutosave()

    expect(pendingSubmission.get()).toEqual({
      name: 'Updated value',
      submission_hash: 'submission-hash-2'
    })
    expect(pendingSubmission.getSubmissionHash()).toBe('submission-hash-2')
  })
})
