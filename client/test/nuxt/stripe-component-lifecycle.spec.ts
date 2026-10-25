import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { reactive, ref } from 'vue'
import PaymentInput from '~/components/forms/heavy/PaymentInput.client.vue'
import { useFormPayment } from '~/lib/forms/composables/useFormPayment'
import { formsApi } from '~/api'
import { loadStripe } from '@stripe/stripe-js'

const sdk = vi.hoisted(() => ({
  confirmCardPayment: vi.fn(), createToken: vi.fn(),
  instances: [] as any[], cards: [] as any[],
}))
vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('~/api', () => ({ formsApi: { stripe: { getAccount: vi.fn(), createPaymentIntent: vi.fn() } } }))
vi.mock('~/composables/useFeatureFlag', () => ({ useFeatureFlag: () => 'pk_test_contract_only' }))
vi.mock('~/composables/useAlert', () => ({ useAlert: () => ({ error: vi.fn() }) }))
vi.mock('@stripe/stripe-js', () => ({ loadStripe: vi.fn() }))
mockNuxtImport('useRoute', () => () => ({ params: { slug: 'payment-contract' }, query: {} }))

const mounted: ReturnType<typeof mount>[] = []
const componentGlobals = {
  mocks: { $t: (key: string) => key },
  stubs: {
    InputWrapper: { template: '<div><slot/><slot name="error"/></div>' },
    TextInput: { name: 'TextInput', props: ['modelValue', 'name'], emits: ['update:modelValue'], template: '<input :name="name" :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)" />' },
    Icon: true, Loader: true,
  },
}
function fixture(props = {}) {
  const block = { id: 'payment', type: 'payment', provider: 'stripe', stripe_account_id: '123' }
  const form = reactive({ payment: null, errors: { set: vi.fn(), has: () => false, clear: vi.fn() },
    data: () => ({ answer: 0, checkbox: false }), update(data) { Object.assign(this, data) },
  })
  const config = ref({ slug: 'payment-contract' })
  const handler = useFormPayment(config, form)
  const paymentData = handler.getPaymentData(block)
  const wrapper = mount(PaymentInput, {
    props: { name: 'payment', form, oauthProviderId: '123', paymentData, ...props },
    global: componentGlobals,
  })
  mounted.push(wrapper)
  return { wrapper, form, config, handler, block, elements: paymentData.stripeElements }
}

beforeEach(() => {
  vi.clearAllMocks()
  sdk.instances.length = 0
  sdk.cards.length = 0
  ;(window as any).Stripe = vi.fn((_key, options) => {
    const instance = {
      confirmCardPayment: sdk.confirmCardPayment, createToken: sdk.createToken,
      elements: () => ({
        update: vi.fn(),
        create: () => {
          const card = {
            _empty: false, destroyed: false,
            mount: vi.fn(), unmount: vi.fn(), update: vi.fn(),
            on(event, callback) { if (event === 'ready') queueMicrotask(() => callback(card)) },
            destroy() { card.destroyed = true },
          }
          sdk.cards.push(card)
          return card
        },
      }),
    }
    sdk.instances.push({ account: options.stripeAccount, instance })
    return instance
  })
  vi.mocked(formsApi.stripe.getAccount).mockResolvedValue({ type: 'success', stripeAccount: 'acct_first' })
  vi.mocked(formsApi.stripe.createPaymentIntent).mockResolvedValue({ type: 'success', intent: { id: 'pi_confirmed', secret: 'contract_secret' } })
  sdk.confirmCardPayment.mockResolvedValue({ paymentIntent: { id: 'pi_confirmed', status: 'succeeded' } })
})
afterEach(() => mounted.splice(0).forEach(wrapper => wrapper.unmount()))

describe('PaymentInput with real payment state and a simulated Stripe SDK contract', () => {
  it('uses initial billing prefills when the user submits without retyping them', async () => {
    const { handler, block, elements } = fixture({ prefillName: 'Ada Lovelace', prefillEmail: 'ada@example.test' })
    await flushPromises()

    expect(elements.state.card).toBeTruthy()
    expect(elements.state.cardHolderName).toBe('Ada Lovelace')
    expect(elements.state.cardHolderEmail).toBe('ada@example.test')
    expect(await handler.processPayment(block)).toMatchObject({ success: true, intentId: 'pi_confirmed' })
    expect(formsApi.stripe.createPaymentIntent).toHaveBeenCalledWith('payment-contract', { submission_data: { answer: 0, checkbox: false } })
    expect(sdk.confirmCardPayment).toHaveBeenCalledWith('contract_secret', expect.objectContaining({ payment_method: expect.objectContaining({ billing_details: { name: 'Ada Lovelace', email: 'ada@example.test' } }) }))
  })

  it('remounts the SDK for the new provider and destroys the previous card element', async () => {
    const { wrapper, handler, block, elements } = fixture()
    await flushPromises()
    const oldCard = elements.state.card
    const changed = { ...block, stripe_account_id: '456' }
    vi.mocked(formsApi.stripe.getAccount).mockResolvedValueOnce({ type: 'success', stripeAccount: 'acct_second' })

    await wrapper.setProps({ oauthProviderId: '456', paymentData: handler.getPaymentData(changed) })
    await flushPromises()

    expect(oldCard.destroyed).toBe(true)
    expect(elements.state.card).not.toBe(oldCard)
    expect(elements.state.stripeAccountId).toBe('acct_second')
    expect(sdk.instances.map(instance => instance.account)).toEqual(['acct_first', 'acct_second'])
  })

  it('shows the preview save hint without initializing the Stripe SDK', async () => {
    vi.mocked(formsApi.stripe.getAccount).mockResolvedValueOnce({ type: 'error', message: 'Please save the form and try again' })
    const { wrapper, elements } = fixture({ isAdminPreview: true })
    await flushPromises()

    expect(wrapper.text()).toContain('Please save the form to activate the payment preview.')
    expect(formsApi.stripe.getAccount).toHaveBeenCalledWith('payment-contract', { query: { oauth_provider_id: '123' } })
    expect(elements.state.showPreviewMessage).toBe(true)
    expect(sdk.instances).toHaveLength(0)
  })

  it('retains entered card and billing details after an intent creation failure and permits retry', async () => {
    const { wrapper, handler, block, elements, form } = fixture()
    await flushPromises()
    await wrapper.get('input[name="cardholder_name"]').setValue('Ada')
    await wrapper.get('input[name="billing_email"]').setValue('ada@example.test')
    const card = elements.state.card
    vi.mocked(formsApi.stripe.createPaymentIntent).mockRejectedValueOnce(new Error('Temporary contract failure'))

    expect(await handler.processPayment(block)).toEqual({ success: false, error: 'Temporary contract failure' })
    expect(sdk.confirmCardPayment).not.toHaveBeenCalled()
    expect(form.payment).toBeNull()
    expect(elements.state.card).toBe(card)
    expect(await handler.processPayment(block)).toMatchObject({ success: true, intentId: 'pi_confirmed' })
    expect(sdk.confirmCardPayment).toHaveBeenCalledOnce()
  })

  it('invalidates an account lookup when the payment component unmounts', async () => {
    let finish: (value: unknown) => void = () => {}
    vi.mocked(formsApi.stripe.getAccount).mockReturnValueOnce(new Promise(resolve => { finish = resolve }))
    const { wrapper, elements } = fixture()
    await flushPromises()
    expect(elements.state.isLoadingAccount).toBe(true)

    wrapper.unmount()
    finish({ type: 'success', stripeAccount: 'acct_late' })
    await flushPromises()

    expect(elements.state.isLoadingAccount).toBe(false)
    expect(elements.state.stripeAccountId).toBeNull()
    expect(elements.state.card).toBeNull()
  })

  it('shows an actionable error instead of a permanent spinner when loading Stripe.js fails', async () => {
    delete (window as any).Stripe
    vi.mocked(loadStripe).mockRejectedValueOnce(new Error('Simulated SDK load failure'))
    const { wrapper, elements } = fixture()
    await flushPromises()

    expect(elements.state.hasAccountLoadingError).toBe(true)
    expect(wrapper.text()).toContain('Failed to initialize Stripe. Please refresh and try again.')
    expect(formsApi.stripe.getAccount).not.toHaveBeenCalled()
  })

  it('remounts the card on its DOM mount point when changing locale', async () => {
    const { wrapper, elements } = fixture()
    await flushPromises()
    const card = elements.state.card
    card.mount.mockImplementation(target => {
      if (!(target instanceof HTMLElement)) throw new Error('Card mount requires a DOM element')
    })

    await wrapper.setProps({ locale: 'fr' })
    await flushPromises()

    expect(card.unmount).toHaveBeenCalledOnce()
    expect(card.mount.mock.calls.at(-1)?.[0]).toBeInstanceOf(HTMLElement)
    expect(elements.state.card).toBe(card)
  })

  it('does not confirm an old intent against a different provider selected while the intent request was pending', async () => {
    const { wrapper, handler, block } = fixture({ prefillName: 'Ada', prefillEmail: 'ada@example.test' })
    await flushPromises()
    let finish: (value: unknown) => void = () => {}
    vi.mocked(formsApi.stripe.createPaymentIntent).mockReturnValueOnce(new Promise(resolve => { finish = resolve }))
    const payment = handler.processPayment(block)
    const changed = { ...block, stripe_account_id: '456' }
    vi.mocked(formsApi.stripe.getAccount).mockResolvedValueOnce({ type: 'success', stripeAccount: 'acct_second' })
    await wrapper.setProps({ oauthProviderId: '456', paymentData: handler.getPaymentData(changed) })
    await flushPromises()

    finish({ type: 'success', intent: { id: 'pi_old', secret: 'old_account_secret' } })
    const result = await payment

    expect(result.success).toBe(false)
    expect(sdk.confirmCardPayment).not.toHaveBeenCalled()
  })

  it('does not publish a confirmation into a new provider after the old confirmation resolves', async () => {
    const { wrapper, handler, block, form } = fixture({ prefillName: 'Ada', prefillEmail: 'ada@example.test' })
    await flushPromises()
    let finish: (value: unknown) => void = () => {}
    sdk.confirmCardPayment.mockReturnValueOnce(new Promise(resolve => { finish = resolve }))
    const payment = handler.processPayment(block)
    await flushPromises()
    expect(sdk.confirmCardPayment).toHaveBeenCalledOnce()
    const changed = { ...block, stripe_account_id: '456' }
    vi.mocked(formsApi.stripe.getAccount).mockResolvedValueOnce({ type: 'success', stripeAccount: 'acct_second' })
    await wrapper.setProps({ oauthProviderId: '456', paymentData: handler.getPaymentData(changed) })
    await flushPromises()

    finish({ paymentIntent: { id: 'pi_old', status: 'succeeded' } })

    expect((await payment).success).toBe(false)
    expect(form.payment).toBeNull()
  })

  it('clears a completed payment when selecting a different provider', async () => {
    const { wrapper, handler, block, form } = fixture({ prefillName: 'Ada', prefillEmail: 'ada@example.test' })
    await flushPromises()
    expect((await handler.processPayment(block)).success).toBe(true)
    await flushPromises()
    expect(form.payment).toBe('pi_confirmed')
    const changed = { ...block, stripe_account_id: '456' }
    vi.mocked(formsApi.stripe.getAccount).mockResolvedValueOnce({ type: 'success', stripeAccount: 'acct_second' })

    await wrapper.setProps({ oauthProviderId: '456', paymentData: handler.getPaymentData(changed) })
    await flushPromises()

    expect(form.payment).toBeNull()
    expect(form.stripe_payment_intent_id).toBeNull()
    expect(sdk.instances.at(-1).account).toBe('acct_second')
    expect(sdk.cards.at(-1).destroyed).toBe(false)
  })

  it('preserves a successful payment across normal step unmount and remount without charging again', async () => {
    const { wrapper, handler, block, form } = fixture({ prefillName: 'Ada', prefillEmail: 'ada@example.test' })
    await flushPromises()
    expect((await handler.processPayment(block)).success).toBe(true)
    await flushPromises()
    const props = wrapper.props()
    wrapper.unmount()

    const remounted = mount(PaymentInput, { props, global: componentGlobals })
    mounted.push(remounted)
    await flushPromises()

    expect(form.payment).toBe('pi_confirmed')
    expect(remounted.text()).toContain('forms.payment.success')
    expect(await handler.processPayment(block)).toEqual({ success: true, intentId: 'pi_confirmed' })
    expect(formsApi.stripe.createPaymentIntent).toHaveBeenCalledOnce()
    expect(sdk.confirmCardPayment).toHaveBeenCalledOnce()
  })
})
