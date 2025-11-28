function parseNumber(value, defaultValue = 0) {
  const parsedValue = parseFloat(value)
  return isNaN(parsedValue) ? defaultValue : parsedValue
}

export default {
  // Keys within public, will be also exposed to the client-side
  public: {
    gtm: {
      id: process.env.NUXT_PUBLIC_GTM_CODE || 'GTM-XXXXXX',
      enabled: false,
    },
    apiBase: process.env.NUXT_PUBLIC_API_BASE || '',
    appUrl: process.env.NUXT_PUBLIC_APP_URL || '',
    env: process.env.NUXT_PUBLIC_ENV || 'local',
    hCaptchaSiteKey: process.env.NUXT_PUBLIC_H_CAPTCHA_SITE_KEY || null,
    reCaptchaSiteKey: process.env.NUXT_PUBLIC_RE_CAPTCHA_SITE_KEY || null,
    amplitudeCode: process.env.NUXT_PUBLIC_AMPLITUDE_CODE || null,
    crispWebsiteId: process.env.NUXT_PUBLIC_CRISP_WEBSITE_ID || null,
    rootRedirectUrl: process.env.NUXT_PUBLIC_ROOT_REDIRECT_URL || null,
    
    featureBaseOrganization: process.env.NUXT_PUBLIC_FEATURE_BASE_ORGANISATION || null,

    // Config within public will be also exposed to the client
    SENTRY_DSN_PUBLIC: process.env.SENTRY_DSN_PUBLIC,
    SENTRY_TRACES_SAMPLE_RATE: parseNumber(process.env.SENTRY_TRACES_SAMPLE_RATE),
    SENTRY_REPLAY_SAMPLE_RATE: parseNumber(process.env.SENTRY_REPLAY_SAMPLE_RATE),
    SENTRY_ERROR_REPLAY_SAMPLE_RATE: parseNumber(process.env.SENTRY_ERROR_REPLAY_SAMPLE_RATE),

    clarityProjectId: process.env.NUXT_PUBLIC_CLARITY_PROJECT_ID || null,
  },

  /**
   * Used to authenticate that the requests are coming from the server - not from a client.
   */
  apiSecret: process.env.NUXT_API_SECRET || '',
  privateApiBase: process.env.NUXT_PRIVATE_API_BASE || null,
  // Comma-separated list of domains allowed to embed the entire platform (server-only)
  allowedEmbedDomains: process.env.NUXT_ALLOWED_EMBED_DOMAINS || process.env.NUXT_PUBLIC_ALLOWED_EMBED_DOMAINS || '',
}
