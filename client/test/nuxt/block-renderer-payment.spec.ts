import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, h, ref } from 'vue'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import BlockRenderer from '~/components/open/forms/BlockRenderer.vue'
import { useParseMention } from '~/composables/components/useParseMention'

const mocks = vi.hoisted(() => ({ loadMentionParser: vi.fn() }))
vi.mock('~/lib/forms/mention-parser-loader.js', () => mocks)
mockNuxtImport('useNuxtApp', () => () => ({ runWithContext: (fn) => fn() }))

vi.mock('~/composables/components/useComponentRegistry', () => ({
  useComponentRegistry: () => ({
    getFormComponent: () => ({ component: 'PaymentInput', clientOnly: true }),
  }),
}))

const PaymentInput = defineComponent({
  props: ['amount'],
  setup: (props) => () => h('div', { 'data-testid': 'payment-amount' }, String(props.amount)),
})

function renderPayment(amount, extra = {}) {
  const block = { id: 'payment', type: 'payment', name: 'Payment', currency: 'USD', amount, ...extra }
  return mount(BlockRenderer, {
    props: {
      block,
      formManager: {
        config: ref({ properties: [block] }),
        form: { data: () => ({}) },
        strategy: ref({ admin: { showAdminControls: true } }),
      },
    },
    global: {
      components: { PaymentInput },
      stubs: {
        ClientOnlyWrapper: { template: '<div><slot /></div>' },
        USkeleton: true,
        LazyEmbedMedia: true,
      },
    },
  })
}

describe('BlockRenderer payment amounts', () => {
  beforeEach(() => {
    mocks.loadMentionParser.mockReset()
  })

  it.each([5, 10, 12.5, 0, '5', null, undefined])('renders amount %s without loading the mention parser', (amount) => {
    const wrapper = renderPayment(amount)

    expect(wrapper.get('[data-testid="payment-amount"]').text()).toBe(String(amount ?? 0))
    expect(mocks.loadMentionParser).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('renders a numeric amount while the parser loads for a different field property', async () => {
    let resolveParser
    mocks.loadMentionParser.mockImplementation(() => new Promise(resolve => { resolveParser = resolve }))
    const wrapper = renderPayment(5, {
      help: '<span mention-field-id="missing" mention-fallback="Order">Order</span>',
    })

    expect(wrapper.get('[data-testid="payment-amount"]').text()).toBe('5')
    expect(mocks.loadMentionParser).toHaveBeenCalledOnce()
    resolveParser(useParseMention)
    await flushPromises()
    expect(wrapper.get('[data-testid="payment-amount"]').text()).toBe('5')
    wrapper.unmount()
  })

  it('resolves a mention amount after the parser loads', async () => {
    mocks.loadMentionParser.mockResolvedValue(useParseMention)
    const wrapper = renderPayment('<span mention="true" mention-field-id="missing" mention-fallback="25">Order total</span>')

    await flushPromises()

    expect(wrapper.get('[data-testid="payment-amount"]').text()).toBe('25')
    wrapper.unmount()
  })
})
