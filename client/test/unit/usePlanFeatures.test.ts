import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed } from 'vue'
import { usePlanFeatures } from '../../composables/usePlanFeatures.js'

describe('usePlanFeatures', () => {
  beforeEach(() => {
    const workspace = computed(() => ({
      plan_tier: 'free',
      features: [],
      required_tiers: {
        'branding.removal': 'pro',
      },
    }))

    globalThis.computed = computed
    globalThis.useCurrentWorkspace = () => ({ current: workspace })
    globalThis.useWorkspaceAbilities = () => ({
      can: (feature) => workspace.value.features.includes(feature),
      tierMeetsRequirement: (tier, requiredTier) => {
        const order = { free: 0, pro: 1, business: 2, enterprise: 3 }
        return (order[tier] ?? 0) >= (order[requiredTier] ?? 0)
      },
      requiredTier: (feature) => workspace.value.required_tiers[feature] ?? null,
    })
    globalThis.useBillingUpsell = () => ({
      currentUserTier: computed(() => 'free'),
      getTierDisplayName: (tier) => ({ free: 'Free', pro: 'Pro', business: 'Business', enterprise: 'Enterprise' }[tier] ?? tier),
    })
    globalThis.useAppModals = () => ({
      openSubscriptionModal: vi.fn(),
    })
  })

  afterEach(() => {
    vi.restoreAllMocks()
    delete globalThis.computed
    delete globalThis.useCurrentWorkspace
    delete globalThis.useWorkspaceAbilities
    delete globalThis.useBillingUpsell
    delete globalThis.useAppModals
  })

  it('denies paid features for a free workspace while preserving the required tier for upsell', () => {
    const { hasFeature, getRequiredTier, getUpgradeMessage } = usePlanFeatures()

    expect(hasFeature('branding.removal')).toBe(false)
    expect(getRequiredTier('branding.removal')).toBe('pro')
    expect(getUpgradeMessage('branding.removal')).toBe('Upgrade to Pro to unlock this feature')
  })
})
