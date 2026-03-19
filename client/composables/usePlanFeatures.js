/**
 * Composable for managing plan-based feature access.
 *
 * The canonical source of truth is the backend config/plans.php.
 * On the client side we fetch the plan manifest once from /plan-manifest
 * and cache it in a module-level ref so every consumer shares the same data.
 */

import { opnFetch } from '~/composables/useOpnApi'

const TIER_ORDER = {
  free: 0,
  pro: 1,
  business: 2,
  enterprise: 3,
}

const TIER_NAMES = {
  free: 'Free',
  pro: 'Pro',
  business: 'Business',
  enterprise: 'Enterprise',
}

// ─── Module-level cached manifest ───────────────────────────────────────────
let manifestPromise = null
const manifest = ref(null)

function fetchManifest() {
  if (import.meta.server) return
  if (manifestPromise) return manifestPromise
  manifestPromise = opnFetch('/plan-manifest')
    .then((data) => {
      manifest.value = data
    })
    .catch((e) => {
      console.warn('Failed to fetch plan manifest', e)
      manifestPromise = null
    })
  return manifestPromise
}

function getFeatureTiers() {
  return manifest.value?.features ?? {}
}

function getFormFeatureTiers() {
  return manifest.value?.form_features ?? {}
}

function getPricingMap() {
  return manifest.value?.pricing ?? {}
}


/**
 * Main composable for plan feature checks
 */
export function usePlanFeatures() {
  // Trigger manifest fetch on first client-side usage
  fetchManifest()

  const { data: user } = useAuth().user()
  const { current: workspace } = useCurrentWorkspace()

  /**
   * Get the current user's plan tier
   */
  const currentUserTier = computed(() => {
    return user.value?.plan_tier || 'free'
  })

  /**
   * Get the current workspace's plan tier
   */
  const currentWorkspaceTier = computed(() => {
    return workspace.value?.plan_tier || currentUserTier.value
  })

  /**
   * Check if a tier meets the requirement for another tier
   */
  const tierMeetsRequirement = (tier, requiredTier) => {
    const tierOrder = TIER_ORDER[tier] ?? 0
    const requiredOrder = TIER_ORDER[requiredTier] ?? 0
    return tierOrder >= requiredOrder
  }

  /**
   * Check if the current workspace/user has access to a feature
   */
  const hasFeature = (feature) => {
    const features = getFeatureTiers()
    const requiredTier = features[feature] || false
    if (!requiredTier) return true
    return tierMeetsRequirement(currentWorkspaceTier.value, requiredTier)
  }

  /**
   * Check if a specific tier has access to a feature
   */
  const tierHasFeature = (tier, feature) => {
    const features = getFeatureTiers()
    const requiredTier = features[feature] || false
    if (!requiredTier) return true
    return tierMeetsRequirement(tier, requiredTier)
  }

  /**
   * Get the required tier for a feature
   */
  const getRequiredTier = (feature) => {
    return getFeatureTiers()[feature] || getFormFeatureTiers()[feature] || null
  }

  /**
   * Get the display name for a tier
   */
  const getTierDisplayName = (tier) => {
    return TIER_NAMES[tier] || tier
  }

  /**
   * Check if upgrade is needed for a feature
   */
  const needsUpgradeFor = (feature) => {
    return !hasFeature(feature)
  }

  /**
   * Get upgrade message for a feature
   */
  const getUpgradeMessage = (feature) => {
    const requiredTier = getRequiredTier(feature)
    if (!requiredTier) return null

    const tierName = getTierDisplayName(requiredTier)
    return `Upgrade to ${tierName} to unlock this feature`
  }

  /**
   * Check feature access and open subscription modal if denied.
   * Returns true if the user has the feature, false otherwise.
   */
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

  /**
   * Check if a form feature requires upgrade
   */
  const formFeatureNeedsUpgrade = (feature) => {
    const requiredTier = getFormFeatureTiers()[feature]
    if (!requiredTier) return false
    return !tierMeetsRequirement(currentWorkspaceTier.value, requiredTier)
  }

  /**
   * Get the required tier for a form feature
   */
  const getFormFeatureRequiredTier = (feature) => {
    return getFormFeatureTiers()[feature] || null
  }

  /**
   * Get display price for a plan
   */
  const getPlanPrice = (plan, yearly = true) => {
    const pricing = getPricingMap()[plan]
    if (!pricing) return null
    return yearly ? pricing.yearly : pricing.monthly
  }

  return {
    currentUserTier,
    currentWorkspaceTier,
    TIER_ORDER,
    TIER_NAMES,

    hasFeature,
    tierHasFeature,
    tierMeetsRequirement,
    needsUpgradeFor,
    requireFeature,

    getRequiredTier,
    getTierDisplayName,
    getUpgradeMessage,

    getPlanPrice,

    formFeatureNeedsUpgrade,
    getFormFeatureRequiredTier,
  }
}
