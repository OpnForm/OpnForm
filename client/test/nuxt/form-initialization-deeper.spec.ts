// @vitest-environment node
import { beforeEach, describe, expect, it, vi } from 'vitest'
import { reactive, ref } from 'vue'
import Form from '~/composables/lib/vForm/Form.js'
import { useFormInitialization } from '~/lib/forms/composables/useFormInitialization'

vi.mock('~/api', () => ({ apiService: {}, formsApi: { submissions: { get: vi.fn() } } }))
vi.mock('#app', async () => ({ createError: (await import('h3')).createError }))

function fixture(overrides = {}, draft = null) {
  const config = ref({ slug: 'initialization-contract', properties: [
    { id: 'amount', type: 'number', prefill: 9 },
    { id: 'consent', type: 'checkbox', prefill: true },
    { id: 'matrix', type: 'matrix', prefill: { row: 'configured' } },
    { id: 'choices', type: 'multi_select', multi_select: { options: [{ id: 'a', name: 'Alpha' }, { id: 'b', name: 'Beta' }] } },
    { id: 'title', type: 'text', prefill: 'Configured title' },
  ], ...overrides })
  const form = reactive(new Form())
  const initialization = useFormInitialization(config, form, { enabled: ref(true), get: () => draft })
  return { config, form, initialization }
}

beforeEach(() => vi.clearAllMocks())
describe('initialization source priority with real form state', () => {
  it('keeps URL zero, false, matrix and repeated options before explicit defaults without accepting internal keys', async () => {
    const { form, initialization } = fixture()
    const defaults = { amount: 12, consent: true, title: 'Record title', matrix: { row: 'default' } }
    const params = new URLSearchParams('amount=0&consent=false&matrix=%7B%22row%22%3A%22url%22%7D&choices%5B%5D=a&choices%5B%5D=b&errors=replace&busy=true&unknown=ignored')
    await initialization.initialize({ defaultData: defaults, urlParams: params })

    expect(form.data()).toMatchObject({ amount: '0', consent: false, matrix: { row: 'url' }, choices: ['Alpha', 'Beta'], title: 'Record title' })
    expect(form.errors.clear).toBeTypeOf('function')
    expect(form.busy).toBe(false)
    expect(form.unknown).toBeUndefined()
    expect(defaults).toEqual({ amount: 12, consent: true, title: 'Record title', matrix: { row: 'default' } })
  })

  it('restores zero, false and empty choices from a draft before URL/defaults and fills an omitted configured field', async () => {
    const draft = { amount: 0, consent: false, choices: [], matrix: { row: 'draft' } }
    const { form, initialization } = fixture({}, draft)
    await initialization.initialize({ defaultData: { amount: 5, consent: true }, urlParams: new URLSearchParams('amount=8&consent=true') })

    expect(form.data()).toMatchObject({ amount: 0, consent: false, choices: [], matrix: { row: 'draft' }, title: 'Configured title' })
    form.matrix.row = 'edited'
    expect(draft.matrix.row).toBe('draft')
  })

  it('honors skip options and uses explicit defaults without restoring or parsing the ignored sources', async () => {
    const { form, initialization } = fixture({}, { amount: 10, consent: true })
    await initialization.initialize({ skipPendingSubmission: true, skipUrlParams: true, defaultData: { amount: 0, consent: false }, urlParams: new URLSearchParams('amount=8&consent=true') })
    expect(form.amount).toBe(0)
    expect(form.consent).toBe(false)
  })

  it('applies numeric zero and false field prefills in a focused form before controls mount', async () => {
    const { form, initialization } = fixture({ presentation_style: 'focused', properties: [
      { id: 'amount', type: 'number', prefill: 0 },
      { id: 'consent', type: 'checkbox', prefill: false },
    ] })
    await initialization.initialize()
    expect(form.amount).toBe(0)
    expect(form.consent).toBe(false)
  })
})
