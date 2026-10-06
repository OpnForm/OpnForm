import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import IntegrationEventsModal from '~/components/open/integrations/components/IntegrationEventsModal.vue'

const mocks = vi.hoisted(() => ({ events: vi.fn(), retry: vi.fn(), success: vi.fn(), error: vi.fn(), warning: vi.fn() }))
vi.mock('~/api/forms', () => ({ formsApi: { integrations: { events: mocks.events, retryEvent: mocks.retry } } }))
vi.mock('~/composables/useAlert.js', () => ({ useAlert: () => mocks }))
const failure = { id: 10, status: 'Error', data: { submission_id: 123 }, can_retry: true }
const other = { ...failure, id: 11, data: { submission_id: 456 } }

function deferred() {
  let resolve!: (value: any) => void
  const promise = new Promise((done) => { resolve = done })
  return { promise, resolve }
}
function mountModal() {
  return mount(IntegrationEventsModal, {
    props: { show: true, form: { id: 1 }, formIntegrationId: 2 },
    global: { stubs: {
      UModal: { props: ['open', 'title', 'description'], template: '<section v-if="open" :aria-label="title"><h2>{{ title }}</h2><p>{{ description }}</p><slot name="body" /><slot name="footer" /></section>' },
      UTable: { props: ['data', 'loading'], template: '<div><div v-for="item in data" :key="item.id"><slot name="submission-cell" :row="{ original: item }" /><slot name="status-cell" :row="{ original: item }" /><slot name="actions-cell" :row="{ original: item }" /></div></div>' },
      UButton: { props: ['label', 'disabled', 'loading'], emits: ['click'], template: '<button :disabled="disabled || loading" @click="$emit(\'click\')">{{ label }}</button>' },
      UBadge: { props: ['label'], template: '<span>{{ label }}</span>' },
      UAlert: { props: ['title', 'description'], template: '<div role="alert">{{ title }} {{ description }}</div>' },
      IntegrationEmailEvent: true, VueJsonPretty: true,
    } },
  })
}
function button(wrapper: ReturnType<typeof mountModal>, label: string) {
  return wrapper.findAll('button').find((item) => item.text() === label)!
}
async function confirmRetry(wrapper: ReturnType<typeof mountModal>) {
  await button(wrapper, 'Retry').trigger('click')
  await button(wrapper, 'Retry integration').trigger('click')
}

describe('integration event history and retries', () => {
  beforeEach(() => {
    vi.resetAllMocks()
    vi.stubGlobal('useAlert', () => mocks)
    mocks.events.mockResolvedValue([failure, other])
    mocks.retry.mockResolvedValue({ type: 'success', message: 'Retried.' })
  })
  afterEach(() => vi.unstubAllGlobals())

  it('loads an initially open modal and explains unavailable retries', async () => {
    mocks.events.mockResolvedValue([{ ...failure, can_retry: false, retry_unavailable_reason: 'Activate this integration.' }])
    const wrapper = mountModal()
    await flushPromises()
    expect(mocks.events).toHaveBeenCalledWith(1, 2)
    expect(wrapper.text()).toContain('#123')
    expect(wrapper.text()).toContain('Activate this integration.')
    expect(button(wrapper, 'Retry')).toBeUndefined()
  })

  it('identifies the submission and consequences before confirming and supports cancelling', async () => {
    const wrapper = mountModal()
    await flushPromises()
    await button(wrapper, 'Retry').trigger('click')
    expect(wrapper.text()).toContain('Resend submission #123')
    expect(wrapper.text()).toContain('latest saved answers')
    expect(wrapper.text()).toContain('may create a duplicate')
    await button(wrapper, 'Cancel').trigger('click')
    expect(mocks.retry).not.toHaveBeenCalled()
    expect(wrapper.find('[aria-label="Retry this integration?"]').exists()).toBe(false)
  })

  it('blocks further retries until the refreshed history is loaded', async () => {
    const retry = deferred()
    const refresh = deferred()
    mocks.retry.mockReturnValue(retry.promise)
    mocks.events.mockResolvedValueOnce([failure, other]).mockReturnValueOnce(refresh.promise)
    const wrapper = mountModal()
    await flushPromises()
    await confirmRetry(wrapper)
    expect(mocks.retry).toHaveBeenCalledTimes(1)
    expect(mocks.retry).toHaveBeenCalledWith(1, 2, 10)
    expect(wrapper.findAll('button').filter((item) => item.text() === 'Retry').every((item) => item.attributes('disabled') !== undefined)).toBe(true)
    retry.resolve({ type: 'success', message: 'Retried.' })
    await flushPromises()
    expect(wrapper.text()).toContain('#123')
    expect(button(wrapper, 'Retry').attributes('disabled')).toBeDefined()
    refresh.resolve([{ ...failure, can_retry: false }, other])
    await flushPromises()
    expect(button(wrapper, 'Retry').attributes('disabled')).toBeUndefined()
    expect(mocks.success).toHaveBeenCalledWith('Retried.')
  })

  it.each(['error', 'warning'])('uses the server outcome for %s retries', async (type) => {
    mocks.retry.mockResolvedValue({ type, message: 'Review this event.' })
    const wrapper = mountModal()
    await flushPromises()
    await confirmRetry(wrapper)
    await flushPromises()
    expect(mocks[type]).toHaveBeenCalledWith('Review this event.')
    expect(mocks.success).not.toHaveBeenCalled()
    expect(mocks.events).toHaveBeenCalledTimes(2)
  })

  it('refreshes eligibility after a rejected retry', async () => {
    mocks.retry.mockRejectedValue({ data: { message: 'A newer attempt exists.' } })
    mocks.events.mockResolvedValueOnce([failure]).mockResolvedValueOnce([{ ...failure, can_retry: false }])
    const wrapper = mountModal()
    await flushPromises()
    await confirmRetry(wrapper)
    await flushPromises()
    expect(mocks.error).toHaveBeenCalledWith('A newer attempt exists.')
    expect(mocks.events).toHaveBeenCalledTimes(2)
    expect(button(wrapper, 'Retry')).toBeUndefined()
  })

  it('preserves history on refresh failure and recovers on the next refresh', async () => {
    mocks.events.mockResolvedValueOnce([failure]).mockRejectedValueOnce(new Error('Network')).mockResolvedValueOnce([other])
    const wrapper = mountModal()
    await flushPromises()
    await button(wrapper, 'Refresh').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('#123')
    expect(wrapper.get('[role="alert"]').text()).toContain('Could not load integration events')
    await button(wrapper, 'Refresh').trigger('click')
    await flushPromises()
    expect(wrapper.text()).toContain('#456')
    expect(wrapper.find('[role="alert"]').exists()).toBe(false)
  })

  it('ignores old fetch results after the selected integration changes', async () => {
    const stale = deferred()
    mocks.events.mockReturnValueOnce(stale.promise).mockResolvedValueOnce([other])
    const wrapper = mountModal()
    await wrapper.setProps({ formIntegrationId: 3 })
    await flushPromises()
    stale.resolve([failure])
    await flushPromises()
    expect(wrapper.text()).toContain('#456')
    expect(wrapper.text()).not.toContain('#123')
  })

  it('does not refresh a different integration when an old retry finishes', async () => {
    const retry = deferred()
    mocks.retry.mockReturnValue(retry.promise)
    const wrapper = mountModal()
    await flushPromises()
    await confirmRetry(wrapper)
    await wrapper.setProps({ formIntegrationId: 3 })
    await flushPromises()
    retry.resolve({ type: 'success', message: 'Retried.' })
    await flushPromises()
    expect(mocks.events).toHaveBeenCalledTimes(2)
    expect(mocks.retry).toHaveBeenCalledWith(1, 2, 10)
  })
})
