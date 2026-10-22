import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises } from '@vue/test-utils'
import { mountSuspended } from '@nuxt/test-utils/runtime'
import OpenCompleteForm from '~/components/open/forms/OpenCompleteForm.vue'

const { submit, restart, ownerSignedUrl } = vi.hoisted(() => ({
  submit: vi.fn(),
  restart: vi.fn().mockResolvedValue(undefined),
  ownerSignedUrl: vi.fn(),
}))

vi.mock('vue-i18n', () => ({
  useI18n: () => ({ t: (key, fallback) => fallback || key, setLocale: vi.fn() }),
}))
vi.mock('~/composables/useForm', () => ({ useForm: () => ({ password: null }) }))
vi.mock('~/composables/useAlert', () => ({ useAlert: () => ({ error: vi.fn() }) }))
vi.mock('~/pages/index.vue', () => ({ default: { template: '<div />' } }))

vi.mock('~/api', () => ({
  formsApi: { pdfTemplates: { getSubmissionSignedUrl: ownerSignedUrl } },
}))
vi.mock('~/lib/forms/composables/useFormManager', async () => {
  const { ref } = await import('vue')
  return {
    useFormManager: () => ({
      initialize: vi.fn().mockResolvedValue(undefined),
      form: { data: () => ({ name: 'TEST' }) },
      state: { isProcessing: false },
      strategy: ref({ display: { showFormCleanings: false, showFontLink: false }, admin: {} }),
      structure: ref(null),
      pendingSubmission: { enabled: ref(false) },
      submit,
      restart,
      setDarkMode: vi.fn(),
      updateConfig: vi.fn(),
    }),
  }
})
vi.mock('~/components/open/forms/OpenForm.vue', () => ({
  default: {
    emits: ['submit'],
    template: '<div><button data-test="submit" @click="$emit(\'submit\')">Submit</button><slot name="after-submit" /></div>',
  },
}))
vi.mock('~/components/forms/components/FormSubmissionFormatter.js', () => ({ clearFormatterCache: vi.fn() }))
vi.mock('~/lib/forms/mention-parser-loader.js', () => ({ clearLoadedMentionCache: vi.fn() }))

async function mountForm() {
  return mountSuspended(OpenCompleteForm, {
    route: '/__support_pdf_download',
    props: {
      form: { id: 4, slug: 'pdf-test', pdf_download_enabled: true, pdf_template_id: 3, language: 'en', re_fillable: true },
    },
    global: {
      stubs: {
        OpenForm: {
          name: 'OpenForm',
          emits: ['submit'],
          template: '<div><button data-test="submit" @click="$emit(\'submit\')">Submit</button><slot name="after-submit" /></div>',
        },
        UAlert: true,
        OpenFormButton: { template: '<button><slot /></button>' },
        VTransition: { template: '<div><slot /></div>' },
      },
    },
  })
}

describe('respondent PDF download', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.spyOn(window, 'scrollTo').mockImplementation(() => {})
    vi.spyOn(window, 'open').mockImplementation(() => null)
  })

  it('uses the signed URL from the submission response without calling an owner endpoint', async () => {
    const signedUrl = 'https://api.example.test/open/forms/4/pdf-templates/3/submissions/test/download?expires=99&signature=test'
    submit.mockResolvedValue({ submission_id: 'test', pdf_download_url: signedUrl })
    const wrapper = await mountForm()
    await wrapper.get('[data-test="submit"]').trigger('click')
    await flushPromises()

    const button = wrapper.findAll('button').find(button => button.text() === 'Download PDF')
    expect(button).toBeDefined()
    await button!.trigger('click')

    expect(window.open).toHaveBeenCalledExactlyOnceWith(signedUrl, '_blank', 'noopener,noreferrer')
    expect(ownerSignedUrl).not.toHaveBeenCalled()
    wrapper.unmount()
  }, 5000)

  it('does not offer an unusable download when the server supplied no signed URL', async () => {
    submit.mockResolvedValue({ submission_id: 'test', pdf_download_url: null })
    const wrapper = await mountForm()
    await wrapper.get('[data-test="submit"]').trigger('click')
    await flushPromises()

    expect(wrapper.findAll('button').some(button => button.text() === 'Download PDF')).toBe(false)
    expect(ownerSignedUrl).not.toHaveBeenCalled()
    wrapper.unmount()
  }, 5000)
  it('clears the previous respondent download when restarting the form', async () => {
    submit.mockResolvedValue({ submission_id: 'test', pdf_download_url: 'https://api.example.test/download?signature=test' })
    const wrapper = await mountForm()
    await wrapper.get('[data-test="submit"]').trigger('click')
    await flushPromises()
    expect(wrapper.findAll('button').some(button => button.text() === 'Download PDF')).toBe(true)

    await wrapper.findAll('button').find(button => button.text() === 'forms.buttons.re_fill')!.trigger('click')
    await flushPromises()

    expect(restart).toHaveBeenCalledOnce()
    expect(wrapper.findAll('button').some(button => button.text() === 'Download PDF')).toBe(false)
    wrapper.unmount()
  }, 5000)

})
