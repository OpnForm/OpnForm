import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest'
import { ref, computed, nextTick, effectScope } from 'vue'
import { usePendingSubmission } from '../../lib/forms/composables/usePendingSubmission.js'

const { storageRefs } = vi.hoisted(() => ({
  storageRefs: new Map()
}))

vi.mock('@vueuse/core', async (importOriginal) => {
  const { ref } = await import('vue')
  const { watchThrottled } = await importOriginal<typeof import('@vueuse/core')>()

  return {
    useStorage: (key, defaultValue = null) => {
      if (!storageRefs.has(key)) {
        storageRefs.set(key, ref(defaultValue))
      }

      return storageRefs.get(key)
    },
    watchThrottled
  }
})

describe('usePendingSubmission', () => {
  const scopes = []
  beforeEach(() => {
    vi.useFakeTimers()
    storageRefs.clear()
    window.history.replaceState({}, '', '/forms/test')
  })

  afterEach(() => {
    scopes.splice(0).forEach(scope => scope.stop())
    vi.clearAllTimers()
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
    const scope = effectScope()
    scopes.push(scope)
    const pendingSubmission = scope.run(() => usePendingSubmission(formConfig, computed(() => formData.value)))!

    return {
      formData,
      pendingSubmission
    }
  }

  async function flushAutosave() {
    await nextTick()
    await vi.advanceTimersByTimeAsync(1000)
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

  it('keeps a stored draft intact while server answers hydrate, then autosaves restored answers', async () => {
    const { formData, pendingSubmission } = createPendingSubmission({ auto_save: true })
    formData.value = { choice: true, feedback: 'Saved answer' }
    await flushAutosave()

    pendingSubmission.pauseAutosave()
    formData.value = { choice: false, feedback: 'Server prefill' }
    await flushAutosave()
    expect(pendingSubmission.get()).toEqual({ choice: true, feedback: 'Saved answer' })

    formData.value = pendingSubmission.get()
    pendingSubmission.resumeAutosave()
    await flushAutosave()
    formData.value = { choice: false, feedback: 'Updated answer' }
    await flushAutosave()
    expect(pendingSubmission.get()).toEqual({ choice: false, feedback: 'Updated answer' })
  })

  it('does not recreate a cleared draft from a queued autosave after submission', async () => {
    const { formData, pendingSubmission } = createPendingSubmission({ auto_save: true })
    formData.value = { feedback: 'First answer' }
    await nextTick()
    formData.value = { feedback: 'Submitted answer' }
    await nextTick()
    expect(pendingSubmission.get()).toEqual({ feedback: 'First answer' })

    pendingSubmission.pauseAutosave()
    pendingSubmission.clear()
    await vi.advanceTimersByTimeAsync(1100)

    expect(pendingSubmission.get()).toEqual({})
  })

  it('saves current answers when refilling before an earlier autosave timer expires', async () => {
    const { formData, pendingSubmission } = createPendingSubmission({ auto_save: true })
    formData.value = { feedback: 'First answer' }
    await nextTick()
    formData.value = { feedback: 'Submitted answer' }
    await nextTick()

    pendingSubmission.pauseAutosave()
    pendingSubmission.clear()
    formData.value = { feedback: 'Fresh prefill' }
    pendingSubmission.resumeAutosave()
    await flushAutosave()
    expect(pendingSubmission.get()).toEqual({ feedback: 'Fresh prefill' })

    formData.value = { feedback: 'New answer' }
    await flushAutosave()
    expect(pendingSubmission.get()).toEqual({ feedback: 'New answer' })
  })
})
