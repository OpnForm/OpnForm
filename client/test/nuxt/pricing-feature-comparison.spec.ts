import { mount } from '@vue/test-utils'
import { describe, expect, it, vi } from 'vitest'

vi.mock('~/composables/useBillingUpsell.js', () => ({
  useBillingUpsell: () => ({
    getTierDisplayName: (plan: string) => plan,
    getPlanPrice: (plan: string, yearly: boolean) => {
      const prices = {
        free: [0, 0],
        pro: [29, 25],
        business: [79, 67],
        enterprise: [250, 220],
      }
      return prices[plan][yearly ? 1 : 0]
    },
  }),
}))

import FeatureComparison from '~/components/pages/pricing/FeatureComparison.vue'

describe('pricing feature comparison', () => {
  it('shows yearly monthly equivalents by default and follows billing changes in both directions', async () => {
    const wrapper = mount(FeatureComparison, {
      global: { stubs: { Icon: true } },
    })
    const prices = () => wrapper.findAll('thead th').slice(1).map(header => header.text())

    expect(prices()).toEqual(['free ($0)', 'pro ($25/mo)', 'business ($67/mo)', 'enterprise ($220/mo)'])
    expect(wrapper.text()).toContain('Prices per month, billed annually.')

    await wrapper.setProps({ yearly: false })
    expect(prices()).toEqual(['free ($0)', 'pro ($29/mo)', 'business ($79/mo)', 'enterprise ($250/mo)'])
    expect(wrapper.text()).toContain('Prices per month, billed monthly.')

    await wrapper.setProps({ yearly: true })
    expect(prices()).toEqual(['free ($0)', 'pro ($25/mo)', 'business ($67/mo)', 'enterprise ($220/mo)'])
    expect(wrapper.text()).toContain('Prices per month, billed annually.')
    wrapper.unmount()
  })
})
