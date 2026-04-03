import { afterEach, beforeEach, describe, expect, it } from 'vitest'
import { computed, ref } from 'vue'
import { useBillingUpsell } from '../../composables/useBillingUpsell.js'

describe('useBillingUpsell', () => {
  beforeEach(() => {
    const user = ref({ plan_tier: 'free' })
    const workspace = ref({ plan_tier: 'free' })

    globalThis.computed = computed
    globalThis.useAuth = () => ({
      user: () => ({ data: user }),
    })
    globalThis.useCurrentWorkspace = () => ({ current: workspace })
    globalThis.usePlanCatalog = () => ({
      tiers: computed(() => ({
        free: { order: 0, name: 'Free', price_monthly: 0, price_yearly_per_month: 0 },
        pro: { order: 1, name: 'Pro', price_monthly: 29, price_yearly_per_month: 25 },
        business: { order: 2, name: 'Business', price_monthly: 79, price_yearly_per_month: 67 },
        enterprise: { order: 3, name: 'Enterprise', price_monthly: 250, price_yearly_per_month: 220 },
      })),
    })
  })

  afterEach(() => {
    delete globalThis.computed
    delete globalThis.useAuth
    delete globalThis.useCurrentWorkspace
    delete globalThis.usePlanCatalog
  })

  it('reads enterprise yearly pricing from the loaded plan catalog', () => {
    const { getPlanPrice, getTierDisplayName } = useBillingUpsell()

    expect(getPlanPrice('enterprise', true)).toBe(220)
    expect(getTierDisplayName('enterprise')).toBe('Enterprise')
  })
})
