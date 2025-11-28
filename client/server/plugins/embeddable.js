import { getDomain } from '~/lib/utils'

export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook("render:response", (response, { event }) => {
    const routePath = event.node?.req?.url || event.node?.req?.originalUrl
    const config = useRuntimeConfig()
    
    // Parse allowed domains from private config
    const allowedDomains = config.allowedEmbedDomains
      ? config.allowedEmbedDomains.split(',').map(domain => domain.trim()).filter(Boolean)
      : []

    // Normalize allowed domains to hostnames (supports plain host or full URL)
    const normalizedAllowed = allowedDomains.map((d) => (getDomain(d) || '').toLowerCase()).filter(Boolean)
    
    // Remove legacy header to control framing via CSP
    delete response.headers["X-Frame-Options"]
    delete response.headers["x-frame-options"]

    if (routePath && !routePath.startsWith("/forms/")) {
      // Build frame-ancestors for non-form routes: localhost variants + matching allowlisted domain (if Referer present)
      // Note: CSP frame-ancestors doesn't support port wildcards, so we list common dev ports
      const commonPorts = ['', ':3000', ':3001', ':4200', ':5000', ':5173', ':8000', ':8080', ':8081', ':9000']
      const ancestors = ["'self'"]
      
      commonPorts.forEach(port => {
        ancestors.push(`http://localhost${port}`)
        ancestors.push(`https://localhost${port}`)
        ancestors.push(`http://127.0.0.1${port}`)
        ancestors.push(`https://127.0.0.1${port}`)
      })

      const referer = event.node?.req?.headers?.referer || ''
      const refererHost = referer ? (getDomain(referer) || '').toLowerCase() : ''

      function hostMatchesAllowed(host, allowedHost) {
        if (!host || !allowedHost) return false
        return host === allowedHost || host.endsWith(`.${allowedHost}`)
      }

      const matched = normalizedAllowed.find((allowedHost) => hostMatchesAllowed(refererHost, allowedHost))
      if (matched) {
        ancestors.push(`https://${refererHost}`)
      }

      // Restrict embedding to localhost + (optionally) matched allowlisted domain
      response.headers["Content-Security-Policy"] = `frame-ancestors ${ancestors.join(' ')};`
    } else {
      // Forms: embeddable anywhere, omit CSP directive
      delete response.headers["Content-Security-Policy"]
    }

    // Enable required features within the embedded document
    response.headers["Permissions-Policy"] = [
      "clipboard-read=(self)",
      "clipboard-write=(self)",
      "identity-credentials-get=(self)",
      "fullscreen=(self)"
    ].join(", ")

    delete response.headers["x-powered-by"]
  })
})
