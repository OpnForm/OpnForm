/**
 * Composable for self-hosted license status.
 * Reads license data from feature flags (loaded at SSR, no extra API call needed).
 */

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

  const isActivationLimitReached = computed(() => {
    return licenseStatus.value === 'activation_limit_reached'
  })

  const hasLicense = computed(() => {
    return licenseStatus.value !== null && licenseStatus.value !== 'invalid'
  })

  return {
    isSelfHosted,
    licenseStatus,
    licenseFeatures,
    expiresAt,
    canAccessEnterprise,
    isGracePeriod,
    isExpired,
    isActivationLimitReached,
    hasLicense,
  }
}
