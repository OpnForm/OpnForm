/**
 * Composable for managing plan-based feature access.
 *
 * The canonical source of truth is the backend config/plans.php.
 * On the client side we fetch the plan manifest once from /plan-manifest
 * and cache it in a module-level ref so every consumer shares the same data.
 *
 * Hardcoded FALLBACK_* maps are kept only as SSR / pre-hydration defaults
 * and must stay in sync with config/plans.php until the manifest loads.
 */

import { opnFetch } from '~/composables/useOpnApi'

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

// ─── Fallback maps (used before manifest loads / during SSR) ────────────────
const FALLBACK_PRICING = {
  free: { monthly: 0, yearly: 0 },
  pro: { monthly: 29, yearly: 25 },
  business: { monthly: 79, yearly: 67 },
  enterprise: { monthly: 250, yearly: 213 },
}

const FALLBACK_FEATURES = {
  'branding.removal': 'pro',
  'branding.advanced': 'business',
  'workspaces.multiple': 'pro',
  'multi_user.roles': 'business',
  'invite_user': 'pro',
  custom_domain: 'pro',
  'custom_domain.wildcard': 'business',
  form_summary: 'pro',
  form_analytics: 'pro',
  custom_smtp: 'pro',
  'security.password_protection': 'pro',
  'security.form_expiration': 'pro',
  'security.captcha': 'pro',
  'integrations.slack': 'pro',
  'integrations.discord': 'pro',
  'integrations.telegram': 'pro',
  'integrations.hubspot': 'business',
  'integrations.salesforce': 'business',
  'integrations.airtable': 'business',
  partial_submissions: 'business',
  enable_partial_submissions: 'business',
  form_versioning: 'business',
  google_address_autocomplete: 'business',
  editable_submissions: 'pro',
  database_fields_update: 'business',
  enable_ip_tracking: 'business',
  'sso.oidc': 'enterprise',
  'sso.saml': 'enterprise',
  'sso.ldap': 'enterprise',
  audit_logs: 'enterprise',
  compliance_features: 'enterprise',
  external_storage: 'enterprise',
  white_label: 'enterprise',
}

const FALLBACK_FORM_FEATURES = {
  no_branding: 'pro',
  redirect_url: 'pro',
  secret_input: 'pro',
  analytics: 'pro',
  custom_css: 'business',
  seo_meta: 'business',
  enable_partial_submissions: 'business',
  editable_submissions: 'business',
  database_fields_update: 'business',
  enable_ip_tracking: 'business',
}

// ─── Module-level cached manifest ───────────────────────────────────────────
let manifestPromise = null
const manifest = ref(null)

function fetchManifest() {
  if (import.meta.server) return // Don't fetch on server
  if (manifestPromise) return manifestPromise
  manifestPromise = opnFetch('/plan-manifest')
    .then((data) => {
      manifest.value = data
    })
    .catch((e) => {
      console.warn('Failed to fetch plan manifest, using fallback', e)
      manifestPromise = null // allow retry
    })
  return manifestPromise
}

// ─── Reactive getters that prefer manifest, fall back to hardcoded ──────────
function getFeatureTiers() {
  return manifest.value?.features ?? FALLBACK_FEATURES
}

function getFormFeatureTiers() {
  return manifest.value?.form_features ?? FALLBACK_FORM_FEATURES
}

function getPricingMap() {
  return manifest.value?.pricing ?? FALLBACK_PRICING
}

// ─── Exported static PLAN_PRICING for components that import it directly ────
export const PLAN_PRICING = FALLBACK_PRICING

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
    PLAN_PRICING: getPricingMap(),

    hasFeature,
    tierHasFeature,
    tierMeetsRequirement,
    needsUpgradeFor,

    getRequiredTier,
    getTierDisplayName,
    getUpgradeMessage,

    getPlanPrice,

    formFeatureNeedsUpgrade,
    getFormFeatureRequiredTier,
  }
}
