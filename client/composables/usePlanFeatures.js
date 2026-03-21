export function usePlanFeatures() {
  const { current: workspace } = useCurrentWorkspace()
  const { can, tierMeetsRequirement, requiredTier: workspaceRequiredTier } = useWorkspaceAbilities()
  const { currentUserTier, getTierDisplayName } = useBillingUpsell()

  const hasFeature = (feature) => {
    if (workspace.value) {
      return can(feature)
    }

    const requiredTier = workspaceRequiredTier(feature)
    if (!requiredTier) return true
    return tierMeetsRequirement(currentUserTier.value, requiredTier)
  }

  const getRequiredTier = (feature) => workspaceRequiredTier(feature)
  const needsUpgradeFor = (feature) => !hasFeature(feature)

  const getUpgradeMessage = (feature) => {
    const requiredTier = getRequiredTier(feature)
    if (!requiredTier) return null

    const tierName = getTierDisplayName(requiredTier)
    return `Upgrade to ${tierName} to unlock this feature`
  }

  const requireFeature = (feature, modalTitle) => {
    if (hasFeature(feature)) return true
    if (import.meta.client) {
      const requiredTier = getRequiredTier(feature) || 'pro'
      useAppModals().openSubscriptionModal({
        plan: requiredTier,
        modal_title: modalTitle || `Upgrade to ${getTierDisplayName(requiredTier)} to unlock this feature`,
      })
    }
    return false
  }

  return {
    hasFeature,
    needsUpgradeFor,
    requireFeature,
    getRequiredTier,
    getTierDisplayName,
    getUpgradeMessage,
  }
}
