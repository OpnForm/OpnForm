const PUBLIC_TIER_CATALOG = {
  free: { order: 0, name: 'Free', price_monthly: 0, price_yearly_per_month: 0 },
  pro: { order: 1, name: 'Pro', price_monthly: 29, price_yearly_per_month: 25 },
  business: { order: 2, name: 'Business', price_monthly: 79, price_yearly_per_month: 67 },
  enterprise: { order: 3, name: 'Enterprise', price_monthly: 250, price_yearly_per_month: 213 },
}

export function useBillingUpsell() {
  const { data: user } = useAuth().user()
  const { current: workspace } = useCurrentWorkspace()

  const currentUserTier = computed(() => user.value?.plan_tier || 'free')
  const currentWorkspaceTier = computed(() => workspace.value?.plan_tier || currentUserTier.value)

  const tierMeetsRequirement = (tier, requiredTier) => {
    return (PUBLIC_TIER_CATALOG[tier]?.order ?? 0) >= (PUBLIC_TIER_CATALOG[requiredTier]?.order ?? 0)
  }

  const getTierDisplayName = (tier) => PUBLIC_TIER_CATALOG[tier]?.name ?? tier

  const getPlanPrice = (plan, yearly = true) => {
    const tier = PUBLIC_TIER_CATALOG[plan]
    if (!tier) return null
    return yearly ? tier.price_yearly_per_month : tier.price_monthly
  }

  const userCanAccessTier = (tier) => tierMeetsRequirement(currentUserTier.value, tier)
  const workspaceCanAccessTier = (tier) => tierMeetsRequirement(currentWorkspaceTier.value, tier)
  const userIsSubscribed = computed(() => userCanAccessTier('pro'))
  const workspaceIsPaid = computed(() => workspaceCanAccessTier('pro'))

  return {
    currentUserTier,
    currentWorkspaceTier,
    tierMeetsRequirement,
    getTierDisplayName,
    getPlanPrice,
    userCanAccessTier,
    workspaceCanAccessTier,
    userIsSubscribed,
    workspaceIsPaid,
  }
}
