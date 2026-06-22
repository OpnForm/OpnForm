import { useSubdomainRedirect } from '~/composables/useSubdomainRedirect'

const nonIndexablePathPatterns = [
  /^\/admin(?:\/|$)/,
  /^\/forms\/create(?:\/|$)/,
  /^\/forms\/[^/]+\/(?:edit|pdf-editor|show)(?:\/|$)/,
  /^\/home(?:\/|$)/,
  /^\/login(?:\/|$)/,
  /^\/password(?:\/|$)/,
  /^\/redirect(?:\/|$)/,
  /^\/register(?:\/|$)/,
  /^\/self-hosted\/checkout(?:\/|$)/,
  /^\/setup(?:\/|$)/,
  /^\/subscriptions(?:\/|$)/,
  /^\/templates\/my-templates(?:\/|$)/,
]

export const useOpnSeoMeta = (meta, alwaysEnabled = false) => {
  const { shouldRedirect } = useSubdomainRedirect()
  const route = useRoute()
  const canonicalBaseUrl = resolveCanonicalBaseUrl()

  if (!alwaysEnabled && shouldRedirect()) {
    return
  }

  const seoMeta = { ...meta }
  delete seoMeta.canonical

  useHead(() => {
    const canonicalUrl = resolveCanonicalUrl(meta.canonical, route, canonicalBaseUrl)

    return {
      link: canonicalUrl
        ? [
            {
              key: 'canonical',
              rel: 'canonical',
              href: canonicalUrl,
            },
          ]
        : [],
    }
  })

  return useSeoMeta({
    ...(seoMeta.title
      ? {
          ogTitle: seoMeta.title,
          twitterTitle: seoMeta.title,
        }
      : {}),
    ...(seoMeta.description
      ? {
          ogDescription: seoMeta.description,
          twitterDescription: seoMeta.description,
        }
      : {}),
    ...(seoMeta.ogImage
      ? {
          twitterImage: seoMeta.ogImage,
        }
      : {}),
    ...seoMeta,
    robots: () => resolveRobots(seoMeta.robots, route),
  })
}

function resolveRobots (robots, route) {
  const resolvedRobots = resolveMetaValue(robots)
  if (resolvedRobots) {
    return resolvedRobots
  }

  return isNonIndexablePath(route.path) ? 'noindex, nofollow' : null
}

function resolveCanonicalUrl (canonical, route, canonicalBaseUrl) {
  const resolvedCanonical = resolveMetaValue(canonical)
  if (resolvedCanonical === false) {
    return null
  }

  if (typeof resolvedCanonical === 'string' && resolvedCanonical) {
    return normalizeCanonicalUrl(resolvedCanonical, canonicalBaseUrl)
  }

  return joinCanonicalUrl(canonicalBaseUrl, route.path)
}

function resolveCanonicalBaseUrl () {
  const configuredAppUrl = useRuntimeConfig().public.appUrl
  if (configuredAppUrl && configuredAppUrl !== '/') {
    return configuredAppUrl
  }

  if (import.meta.server) {
    const event = useRequestEvent()
    const forwardedHost = event?.node.req.headers['x-forwarded-host']
    const host = forwardedHost || event?.node.req.headers.host
    const protocol = event?.node.req.headers['x-forwarded-proto'] || 'https'

    return host ? `${protocol}://${host}` : ''
  }

  return import.meta.client ? window.location.origin : ''
}

function normalizeCanonicalUrl (url, canonicalBaseUrl) {
  if (/^https?:\/\//.test(url)) {
    return url
  }

  return joinCanonicalUrl(canonicalBaseUrl, url)
}

function joinCanonicalUrl (baseUrl, path) {
  if (!baseUrl) {
    return null
  }

  const normalizedBaseUrl = baseUrl.replace(/\/+$/, '')
  const normalizedPath = path === '/' ? '/' : `/${path.replace(/^\/+|\/+$/g, '')}`

  return `${normalizedBaseUrl}${normalizedPath}`
}

function isNonIndexablePath (path) {
  return nonIndexablePathPatterns.some((pattern) => pattern.test(path))
}

function resolveMetaValue (value) {
  return typeof value === 'function' ? value() : value
}
