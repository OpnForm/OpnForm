import { getDomain } from '~/lib/utils'

export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook("render:response", (response, { event }) => {
    const rawRoutePath = event.node?.req?.url || event.node?.req?.originalUrl
    const routePath = (rawRoutePath || '').split('?')[0]
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

    const isFormPublicRoute = routePath.startsWith("/forms/")
    const isChatGptPreviewRoute = /^\/gpt\/drafts\/[^/]+\/preview\/?$/.test(routePath)

    if (routePath && !isFormPublicRoute && !isChatGptPreviewRoute) {
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

      normalizedAllowed.forEach((allowedHost) => {
        ancestors.push(`https://${allowedHost}`)
        ancestors.push(`https://*.${allowedHost}`)
      })

      // Restrict embedding to localhost + allowlisted domains
      response.headers["Content-Security-Policy"] = `frame-ancestors ${ancestors.join(' ')};`
    } else {
      // Public forms and GPT preview: embeddable anywhere, omit CSP directive
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
