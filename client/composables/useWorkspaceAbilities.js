const TIER_ORDER = {
  free: 0,
  pro: 1,
  business: 2,
  enterprise: 3,
}

export function useWorkspaceAbilities() {
  const { current: workspace } = useCurrentWorkspace()

  const features = computed(() => workspace.value?.features ?? [])
  const limits = computed(() => workspace.value?.limits ?? {})
  const requiredTiers = computed(() => workspace.value?.required_tiers ?? {})
  const currentWorkspaceTier = computed(() => workspace.value?.plan_tier ?? 'free')

  const can = (feature) => features.value.includes(feature)
  const cannot = (feature) => !can(feature)
  const limit = (key) => limits.value?.[key] ?? null
  const requiredTier = (feature) => requiredTiers.value?.[feature] ?? null
  const tierMeetsRequirement = (tier, requiredTier) => {
    return (TIER_ORDER[tier] ?? 0) >= (TIER_ORDER[requiredTier] ?? 0)
  }

  return {
    workspace,
    features,
    limits,
    requiredTiers,
    currentWorkspaceTier,
    can,
    cannot,
    limit,
    requiredTier,
    tierMeetsRequirement,
  }
}
