export function useBillingUpsell() {
  const { data: user } = useAuth().user()
  const { current: workspace } = useCurrentWorkspace()
  const { tiers } = usePlanCatalog()

  const currentUserTier = computed(() => user.value?.plan_tier || 'free')
  const currentWorkspaceTier = computed(() => workspace.value?.plan_tier || currentUserTier.value)

  const tierMeetsRequirement = (tier, requiredTier) => {
    return (tiers.value[tier]?.order ?? 0) >= (tiers.value[requiredTier]?.order ?? 0)
  }

  const getTierDisplayName = (tier) => tiers.value[tier]?.name ?? tier

  const getPlanPrice = (plan, yearly = true) => {
    const tier = tiers.value[plan]
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
