import { beforeEach, describe, expect, it, vi } from 'vitest'
import { useFormPayment } from '~/lib/forms/composables/useFormPayment'
import { formsApi } from '~/api'

vi.mock('vue-i18n', () => ({ useI18n: () => ({ t: (key: string) => key }) }))
vi.mock('~/api', () => ({ formsApi: { stripe: { getAccount: vi.fn() } } }))

describe('Stripe provider selection', () => {
  beforeEach(() => vi.clearAllMocks())

  function payment() {
    const block = { type: 'payment', provider: 'stripe', stripe_account_id: '123' }
    const handler = useFormPayment({ slug: 'test-form' }, {})
    return { block, handler, elements: handler.getPaymentData(block).stripeElements }
  }

  it('preserves the connected account and entered card when the same block renders again', async () => {
    vi.mocked(formsApi.stripe.getAccount).mockResolvedValue({ type: 'success', stripeAccount: 'acct_connected' })
    const { block, handler, elements } = payment()
    await elements.prepareStripeState('test-form', '123')
    elements.setStripeInstance({ confirmCardPayment: vi.fn(), createToken: vi.fn() })
    elements.setCardElement({ _empty: false })
    elements.setIntentId('pi_current')
    const card = elements.state.card

    handler.getPaymentData(block)

    expect(elements.state.stripeAccountId).toBe('acct_connected')
    expect(elements.state.card).toBe(card)
    expect(elements.state.intentId).toBe('pi_current')
    expect(elements.isReadyForPayment).toBeTruthy()
  })

  it.each(['456', null])('clears payment state when the selected provider changes to %s', async providerId => {
    vi.mocked(formsApi.stripe.getAccount).mockResolvedValue({ type: 'success', stripeAccount: 'acct_connected' })
    const { block, handler, elements } = payment()
    await elements.prepareStripeState('test-form', '123')
    elements.setCardElement({ _empty: false })
    elements.setIntentId('pi_current')

    handler.getPaymentData({ ...block, stripe_account_id: providerId })

    expect(elements.state.stripeAccountId).toBeNull()
    expect(elements.state.card).toBeNull()
    expect(elements.state.intentId).toBeNull()
  })

  it.each(['success', 'error'])('ignores an old account request completing with %s after a provider change', async outcome => {
    let finish: (value: unknown) => void = () => {}
    let fail: (reason: unknown) => void = () => {}
    vi.mocked(formsApi.stripe.getAccount).mockReturnValueOnce(new Promise((resolve, reject) => { finish = resolve; fail = reject }))
    const { block, handler, elements } = payment()
    const pending = elements.prepareStripeState('test-form', '123')
    handler.getPaymentData({ ...block, stripe_account_id: '456' })
    vi.mocked(formsApi.stripe.getAccount).mockResolvedValueOnce({ type: 'success', stripeAccount: 'acct_new' })
    await elements.prepareStripeState('test-form', '456')

    if (outcome === 'success') finish({ type: 'success', stripeAccount: 'acct_old' })
    else fail(new Error('Old request failed'))
    await pending

    expect(elements.state.stripeAccountId).toBe('acct_new')
    expect(elements.state.hasAccountLoadingError).toBe(false)
    expect(elements.state.isLoadingAccount).toBe(false)
  })
})
