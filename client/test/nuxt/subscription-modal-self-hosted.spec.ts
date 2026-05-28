import { flushPromises, mount } from '@vue/test-utils'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { nextTick } from 'vue'

const mocks = vi.hoisted(() => ({
  fetch: vi.fn(),
  routerPush: vi.fn(),
  alertError: vi.fn(),
  stripeCheckout: vi.fn(),
  isSelfHosted: true,
}))

vi.mock('#imports', async () => {
  const vue = await import('vue')

  return {
    ref: vue.ref,
    watch: vue.watch,
    useFeatureFlag: (key: string) => key === 'self_hosted' && mocks.isSelfHosted,
    useRouter: () => ({ push: mocks.routerPush }),
    useIsAuthenticated: () => ({ isAuthenticated: vue.ref(true) }),
    useAuth: () => ({
      user: () => ({ data: vue.ref({ email: 'admin@example.com' }) }),
    }),
    useBillingUpsell: () => ({
      getPlanPrice: () => 99,
      userIsSubscribed: vue.ref(false),
      currentUserTier: vue.ref('free'),
      tierMeetsRequirement: (plan: string, requiredPlan: string) => plan === requiredPlan,
      getTierDisplayName: (plan: string) => plan === 'self_hosted' ? 'Self Hosted' : plan,
    }),
    useStripeCheckout: () => ({
      startCheckout: mocks.stripeCheckout,
      isLoading: vue.ref(false),
      isPlanLoading: () => false,
    }),
    useRuntimeConfig: () => ({
      public: {
        licenseApiEndpoint: 'https://api.opnform.com',
      },
    }),
    useAlert: () => ({
      error: mocks.alertError,
    }),
    $fetch: (...args: unknown[]) => mocks.fetch(...args),
  }
})

vi.mock('~/composables/query/useAuth.js', async () => {
  const vue = await import('vue')

  return {
    useAuth: () => ({
      user: () => ({ data: vue.ref({ email: 'admin@example.com' }) }),
    }),
  }
})

vi.mock('~/composables/useAuthFlow.js', async () => {
  const vue = await import('vue')

  return {
    useIsAuthenticated: () => ({ isAuthenticated: vue.ref(true) }),
  }
})

vi.mock('~/composables/useBillingUpsell.js', async () => {
  const vue = await import('vue')

  return {
    useBillingUpsell: () => ({
      getPlanPrice: () => 99,
      userIsSubscribed: vue.ref(false),
      currentUserTier: vue.ref('free'),
      tierMeetsRequirement: (plan: string, requiredPlan: string) => plan === requiredPlan,
      getTierDisplayName: (plan: string) => plan === 'self_hosted' ? 'Self Hosted' : plan,
    }),
  }
})

vi.mock('~/composables/useFeatureFlag.js', () => ({
  useFeatureFlag: (key: string) => key === 'self_hosted' && mocks.isSelfHosted,
}))

vi.mock('~/composables/useStripeCheckout.js', async () => {
  const vue = await import('vue')

  return {
    useStripeCheckout: () => ({
      startCheckout: mocks.stripeCheckout,
      isLoading: vue.ref(false),
      isPlanLoading: () => false,
    }),
  }
})

import SubscriptionModal from '~/components/pages/pricing/SubscriptionModal.vue'

describe('SubscriptionModal self-hosted checkout', () => {
  let openMock: ReturnType<typeof vi.fn>
  let originalOpen: typeof window.open

  const mountModal = (props = {}) => mount(SubscriptionModal, {
    props: {
      modelValue: true,
      plan: 'self_hosted',
      ...props,
    },
    global: {
      stubs: {
        UModal: {
          template: '<div><slot name="body" /></div>',
          props: ['open', 'ui', 'title', 'close'],
        },
        UButton: {
          template: '<button :disabled="disabled" @click="$emit(\'click\', $event)"><slot /></button>',
          props: ['block', 'color', 'disabled', 'loading', 'size', 'to', 'target', 'trailingIcon', 'variant'],
          emits: ['click'],
        },
        UIcon: {
          template: '<span />',
          props: ['name'],
        },
        ULink: {
          template: '<a><slot /></a>',
          props: ['to', 'target'],
        },
        MonthlyYearlySelector: {
          template: '<div />',
          props: ['modelValue'],
          emits: ['update:modelValue'],
        },
        TrackClick: {
          template: '<div><slot /></div>',
          props: ['name', 'properties'],
        },
      },
    },
  })

  beforeEach(() => {
    mocks.fetch.mockReset()
    mocks.routerPush.mockReset()
    mocks.alertError.mockReset()
    mocks.stripeCheckout.mockReset()
    mocks.isSelfHosted = true
    openMock = vi.fn()
    originalOpen = window.open
    window.open = openMock
    globalThis.$fetch = mocks.fetch
  })

  afterEach(() => {
    window.open = originalOpen
    delete globalThis.$fetch
  })

  it('prevents duplicate self-hosted checkout sessions while the first checkout is loading', async () => {
    let resolveCheckout: (value: { checkoutUrl: string }) => void
    mocks.fetch.mockReturnValue(new Promise((resolve) => {
      resolveCheckout = resolve
    }))

    const wrapper = mountModal()
    const vm = wrapper.vm as unknown as {
      startCheckout: (planName: string) => Promise<void>
    }

    const button = wrapper.findAll('button').find((candidate) => candidate.text().includes('Purchase license'))
    expect(button?.attributes('disabled')).toBeUndefined()

    await vm.startCheckout('self_hosted')
    await vm.startCheckout('self_hosted')
    await nextTick()

    expect(mocks.fetch).toHaveBeenCalledTimes(1)
    expect(mocks.fetch).toHaveBeenCalledWith('https://api.opnform.com/licenses/create', {
      method: 'POST',
      body: {
        billingEmail: 'admin@example.com',
        plan: 'self_hosted',
        period: 'yearly',
      },
    })
    expect(button?.attributes('disabled')).toBeDefined()

    resolveCheckout!({ checkoutUrl: 'https://checkout.stripe.test/session' })
    await flushPromises()

    expect(openMock).toHaveBeenCalledWith('https://checkout.stripe.test/session', '_blank')
  })

  it('does not render the self-hosted license plan on hosted upgrade modals', () => {
    mocks.isSelfHosted = false

    const wrapper = mountModal({ plan: 'pro' })

    expect(wrapper.findAll('article')).toHaveLength(3)
    expect(wrapper.text()).not.toContain('Self Hosted')
    expect(wrapper.text()).not.toContain('Purchase license')
  })
})
