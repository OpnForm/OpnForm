/**
 * Composable for managing plan-based feature access
 *
 * Mirrors the backend PlanService logic for client-side checks.
 * Single source of truth for plan tiers and features on the frontend.
 */

// Tier ordering (higher = more features)
const TIER_ORDER = {
  free: 0,
  pro: 1,
  business: 2,
  enterprise: 3,
}

// Tier display names
const TIER_NAMES = {
  free: 'Free',
  pro: 'Pro',
  business: 'Business',
  enterprise: 'Enterprise',
}

/**
 * Plan pricing - single source of truth for all frontend pricing display.
 * Mirrors backend config/plans.php tiers pricing.
 */
export const PLAN_PRICING = {
  free: { monthly: 0, yearly: 0 },
  pro: { monthly: 29, yearly: 25 },
  business: { monthly: 79, yearly: 67 },
  enterprise: { monthly: 250, yearly: 213 },
}

// Feature to minimum tier mapping (mirrors config/plans.php)
const FEATURE_TIERS = {
  // Branding
  'branding.removal': 'pro',
  'branding.advanced': 'business',

  // Workspaces
  'workspaces.multiple': 'pro',

  // Multi-user
  'multi_user.roles': 'business',

  // Domains
  custom_domain: 'pro',
  'custom_domain.wildcard': 'business',

  // Email/SMTP
  custom_smtp: 'pro',

  // Security
  'security.password_protection': 'pro',
  'security.form_expiration': 'pro',
  'security.captcha': 'pro',

  // Integrations
  'integrations.slack': 'pro',
  'integrations.discord': 'pro',
  'integrations.telegram': 'pro',
  'integrations.hubspot': 'business',
  'integrations.salesforce': 'business',
  'integrations.airtable': 'business',

  // Form Features
  partial_submissions: 'business',
  form_versioning: 'business',
  analytics_dashboard: 'business',
  google_address_autocomplete: 'business',
  editable_submissions: 'business',

  // Enterprise
  'sso.oidc': 'enterprise',
  'sso.saml': 'enterprise',
  'sso.ldap': 'enterprise',
  audit_logs: 'enterprise',
  compliance_features: 'enterprise',
  external_storage: 'enterprise',
  white_label: 'enterprise',
}

// Form feature to tier mapping (for form editing context)
const FORM_FEATURE_TIERS = {
  no_branding: 'pro',
  redirect_url: 'pro',
  secret_input: 'pro',
  custom_css: 'business',
  seo_meta: 'business',
  analytics: 'business',
  enable_partial_submissions: 'business',
  editable_submissions: 'business',
  database_fields_update: 'business',
  enable_ip_tracking: 'enterprise',
}

/**
 * Main composable for plan feature checks
 */
export function usePlanFeatures() {
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
    const requiredTier = FEATURE_TIERS[feature]
    if (!requiredTier) return true // Feature not defined = available to all

    return tierMeetsRequirement(currentWorkspaceTier.value, requiredTier)
  }

  /**
   * Check if a specific tier has access to a feature
   */
  const tierHasFeature = (tier, feature) => {
    const requiredTier = FEATURE_TIERS[feature]
    if (!requiredTier) return true
    return tierMeetsRequirement(tier, requiredTier)
  }

  /**
   * Get the required tier for a feature
   */
  const getRequiredTier = (feature) => {
    return FEATURE_TIERS[feature] || FORM_FEATURE_TIERS[feature] || null
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
   * Check if a form feature requires upgrade
   */
  const formFeatureNeedsUpgrade = (feature) => {
    const requiredTier = FORM_FEATURE_TIERS[feature]
    if (!requiredTier) return false

    return !tierMeetsRequirement(currentWorkspaceTier.value, requiredTier)
  }

  /**
   * Get the required tier for a form feature
   */
  const getFormFeatureRequiredTier = (feature) => {
    return FORM_FEATURE_TIERS[feature] || null
  }

  /**
   * Get display price for a plan
   */
  const getPlanPrice = (plan, yearly = true) => {
    const pricing = PLAN_PRICING[plan]
    if (!pricing) return null
    return yearly ? pricing.yearly : pricing.monthly
  }

  return {
    // Tier info
    currentUserTier,
    currentWorkspaceTier,
    TIER_ORDER,
    TIER_NAMES,
    PLAN_PRICING,

    // Feature checks
    hasFeature,
    tierHasFeature,
    tierMeetsRequirement,
    needsUpgradeFor,

    // Tier info
    getRequiredTier,
    getTierDisplayName,
    getUpgradeMessage,

    // Pricing
    getPlanPrice,

    // Form-specific
    formFeatureNeedsUpgrade,
    getFormFeatureRequiredTier,
  }
}

/**
 * Simple helper to check if user has pro or higher
 * @deprecated Use usePlanFeatures().hasFeature() instead
 */
export function useIsPro() {
  const { currentWorkspaceTier, tierMeetsRequirement } = usePlanFeatures()

  return computed(() => tierMeetsRequirement(currentWorkspaceTier.value, 'pro'))
}
