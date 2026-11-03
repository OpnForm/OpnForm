import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { reactive } from 'vue'
import CreateFormBaseModal from '~/components/pages/forms/create/CreateFormBaseModal.vue'

const { post, get, alert } = vi.hoisted(() => ({
  post: vi.fn(),
  get: vi.fn(),
  alert: { success: vi.fn(), error: vi.fn() },
}))

mockNuxtImport('useForm', () => (data: object) => reactive({ ...data, post }))
mockNuxtImport('useFeatureFlag', () => () => true)
mockNuxtImport('useAlert', () => () => alert)
vi.mock('~/api/base', () => ({ apiService: { get } }))

describe('AI form creation access', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    window.sessionStorage.clear()
    vi.stubGlobal('useWorkingFormStore', () => ({}))
  })

  afterEach(() => {
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it.each(['classic', 'focused'])('keeps the guest completion token through %s generation polling', async (style) => {
    const generated = { title: 'Generated contact form', properties: [] }
    post.mockResolvedValue({
      ai_form_completion_id: 42,
      ai_form_completion_token: 'private-completion-token',
      message: 'Generating',
    })
    get.mockImplementation((_url, options) => {
      if (options.headers['X-AI-Completion-Token'] !== 'private-completion-token') {
        return Promise.reject({ data: { message: 'You are not authorized to view this AI completion.' } })
      }
      return Promise.resolve({ ai_form_completion: { status: get.mock.calls.length === 1 ? 'processing' : 'completed', result: generated } })
    })
    const wrapper = mount(CreateFormBaseModal, {
      props: { show: true },
      global: {
        stubs: {
          UModal: { template: '<div><slot name="content" /></div>' },
          SlidingTransition: { template: '<div><slot /></div>' },
          UButton: { props: ['label', 'disabled'], template: '<button :disabled="disabled">{{ label }}</button>' },
          TextareaInput: {
            props: ['form', 'name'],
            template: '<textarea @input="form[name] = $event.target.value" />',
          },
          AIFormLoadingMessages: true,
          FormImportModal: true,
          TrackClick: { template: '<div><slot /></div>' },
          Icon: true,
          UIcon: true,
          NuxtLink: true,
        },
      },
    })

    await wrapper.get(`[data-testid="form-style-${style}"]`).trigger('click')
    await wrapper.get('textarea').setValue('A contact form')
    const generate = wrapper.findAll('button').find(button => button.text() === 'Generate')!
    await generate.trigger('click')
    await flushPromises()
    expect(post).toHaveBeenCalledWith('/forms/ai/generate', {
      body: { generation_params: { presentation_style: style } },
    })

    await vi.advanceTimersByTimeAsync(4000)
    await flushPromises()
    expect(get).toHaveBeenCalledWith('/forms/ai/42', {
      headers: { 'X-AI-Completion-Token': 'private-completion-token' },
    })
    expect(wrapper.emitted('form-generated')).toBeUndefined()

    await vi.advanceTimersByTimeAsync(4000)
    await flushPromises()
    expect(wrapper.emitted('form-generated')).toEqual([[generated]])
    expect(wrapper.emitted('close')).toHaveLength(1)
    expect(alert.error).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
