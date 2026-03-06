/**
 * Composable for self-hosted license status.
 * Reads license data from feature flags (loaded at SSR, no extra API call needed).
 */

const LICENSE_FEATURES_MAPPING = {
  sso: ['sso.oidc', 'sso.saml', 'sso.ldap'],
  multiOrg: ['workspaces.multiple', 'multi_user.roles'],
  whitelabel: ['branding.removal', 'branding.advanced', 'white_label'],
  custom_smtp: ['custom_smtp'],
  audit_logs: ['audit_logs', 'compliance_features'],
  external_storage: ['external_storage'],
}

function getFlag(flags, path, defaultValue = null) {
  if (!flags || typeof flags !== 'object') return defaultValue
  return path.split('.').reduce((acc, part) => {
    if (acc === undefined || acc === null) return defaultValue
    return acc && acc[part] !== undefined ? acc[part] : defaultValue
  }, flags)
}

export function useInstanceLicense() {
  const featureFlags = useState('featureFlags', () => ({}))

  const isSelfHosted = computed(() => !!getFlag(featureFlags.value, 'self_hosted', false))
  const licenseData = computed(() => getFlag(featureFlags.value, 'license', null))

  const licenseStatus = computed(() => {
    if (!isSelfHosted.value || !licenseData.value) return null
    return licenseData.value.status || 'invalid'
  })

  const licenseFeatures = computed(() => {
    if (!isSelfHosted.value || !licenseData.value) return null
    return licenseData.value.features || null
  })

  const expiresAt = computed(() => {
    if (!isSelfHosted.value || !licenseData.value) return null
    return licenseData.value.expires_at || null
  })

  const canAccessEnterprise = computed(() => {
    if (!isSelfHosted.value) return false
    return licenseStatus.value === 'active' || licenseStatus.value === 'grace'
  })

  const isGracePeriod = computed(() => {
    return licenseStatus.value === 'grace'
  })

  const isExpired = computed(() => {
    return licenseStatus.value === 'expired'
  })

  const hasLicense = computed(() => {
    return licenseStatus.value !== null && licenseStatus.value !== 'invalid'
  })

  /**
   * Check if the license grants a specific license-level feature (e.g. 'sso', 'multiOrg').
   */
  const hasLicenseFeature = (licenseFeatureKey) => {
    if (!canAccessEnterprise.value || !licenseFeatures.value) return false
    return !!licenseFeatures.value[licenseFeatureKey]
  }

  /**
   * Check if the license grants a specific application feature (e.g. 'sso.oidc', 'custom_smtp').
   * Uses the same mapping as the backend license_features_mapping config.
   */
  const hasAppFeature = (appFeature) => {
    if (!canAccessEnterprise.value || !licenseFeatures.value) return false

    for (const [licenseKey, appFeatures] of Object.entries(LICENSE_FEATURES_MAPPING)) {
      if (appFeatures.includes(appFeature) && licenseFeatures.value[licenseKey]) {
        return true
      }
    }
    return false
  }

  return {
    isSelfHosted,
    licenseStatus,
    licenseFeatures,
    expiresAt,
    canAccessEnterprise,
    isGracePeriod,
    isExpired,
    hasLicense,
    hasLicenseFeature,
    hasAppFeature,
  }
}
