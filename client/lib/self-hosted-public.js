export const selfHostedMarketingPaths = [
  '/pricing',
  '/enterprise',
  '/industry',
  '/ai-form-builder',
  '/mcp',
  '/self-hosted/license',
  '/self-hosted/checkout/success',
  '/self-hosted/checkout/canceled',
]

const marketingPaths = new Set(selfHostedMarketingPaths)

export function httpUrl(value) {
  if (!value) return null
  try {
    const url = new URL(value)
    return ['http:', 'https:'].includes(url.protocol) ? url.href : null
  } catch {
    return null
  }
}

export function getSelfHostedPublicRedirect(path, config = {}) {
  const normalizedPath = path.replace(/\/+$/, '') || '/'

  if (normalizedPath === '/privacy-policy') return httpUrl(config.privacyPolicyUrl) || '/'
  if (normalizedPath === '/terms-conditions') return httpUrl(config.termsUrl) || '/'

  const isMarketingPage = marketingPaths.has(normalizedPath)
    || /^\/integrations(?:\/|$)/.test(normalizedPath)
    || /^\/opnform-vs-[a-z0-9-]+$/.test(normalizedPath)

  if (!isMarketingPage) return null
  if (httpUrl(config.rootRedirectUrl)) return httpUrl(config.rootRedirectUrl)

  // Only carry the known page path. Instance queries can contain private data.
  return `https://opnform.com${normalizedPath}?utm_source=self_hosted&utm_medium=instance&utm_campaign=public_pages`
}

export function filterSelfHostedSitemapUrls(urls) {
  return urls.filter(({ loc }) => {
    const path = new URL(loc, 'https://opnform.com').pathname.replace(/\/+$/, '') || '/'
    return path !== '/' && path !== '/report-abuse' && !getSelfHostedPublicRedirect(path)
  })
}
