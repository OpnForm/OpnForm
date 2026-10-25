import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { formsApi } from '~/api'
import { usePartialSubmission } from '~/composables/forms/usePartialSubmission'

vi.mock('~/api', () => ({
  formsApi: { submissions: { answer: vi.fn().mockResolvedValue({ submission_hash: 'saved-hash' }) } },
}))

describe('partial submissions during draft hydration', () => {
  const wrappers = []

  beforeEach(() => {
    vi.useFakeTimers()
    vi.clearAllMocks()
  })

  afterEach(() => {
    wrappers.splice(0).forEach(wrapper => wrapper.unmount())
    vi.useRealTimers()
  })

  function createSync() {
    const data = ref({ choice: false, feedback: 'Server defaults' })
    const pending = {
      formPendingSubmissionKey: ref('hydration-draft'),
      getSubmissionHash: () => 'saved-hash',
      setSubmissionHash: vi.fn(),
    }
    let service
    wrappers.push(mount(defineComponent({
      setup() {
        service = usePartialSubmission(ref({ slug: 'feedback', enable_partial_submissions: true }), data, pending)
        return () => h('div')
      },
    })))
    return { data, service, wrapper: wrappers[wrappers.length - 1] }
  }

  it('never sends server defaults on unmount if synchronization has not started', async () => {
    const { wrapper } = createSync()

    await vi.advanceTimersByTimeAsync(5000)
    wrapper.unmount()
    wrappers.pop()

    expect(formsApi.submissions.answer).not.toHaveBeenCalled()
  })

  it('starts with restored answers and the existing partial submission hash', async () => {
    const { data, service } = createSync()
    await vi.advanceTimersByTimeAsync(5000)
    expect(formsApi.submissions.answer).not.toHaveBeenCalled()

    data.value = { choice: true, feedback: 'Saved answer' }
    service.startSync()
    await vi.advanceTimersByTimeAsync(2000)

    expect(formsApi.submissions.answer).toHaveBeenCalledExactlyOnceWith('feedback', {
      choice: true, feedback: 'Saved answer', submission_hash: 'saved-hash', is_partial: true,
    })
  })

  it('still sends a final update when an active partial submission is stopped', async () => {
    const { data, service } = createSync()
    data.value = { choice: true, feedback: 'Saved answer' }
    service.startSync()

    service.stopSync()
    await vi.advanceTimersByTimeAsync(5000)

    expect(formsApi.submissions.answer).toHaveBeenCalledExactlyOnceWith('feedback', {
      choice: true, feedback: 'Saved answer', submission_hash: 'saved-hash', is_partial: true,
    })
  })

  it('does not send a trailing partial update when stopping for a final submission', async () => {
    const { service } = createSync()
    service.startSync()

    service.stopSync({ skipFinalSync: true })
    await vi.advanceTimersByTimeAsync(5000)

    expect(formsApi.submissions.answer).not.toHaveBeenCalled()
  })
})
