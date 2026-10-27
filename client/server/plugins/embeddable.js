import { mergeFramePolicy, embeddingAncestors } from '~/lib/framePolicy'

export default defineNitroPlugin((nitroApp) => {
  nitroApp.hooks.hook('render:response', (response, { event }) => {
    const routePath = event.node?.req?.url || event.node?.req?.originalUrl || ''
    const config = useRuntimeConfig(event)
    delete response.headers['X-Frame-Options']
    delete response.headers['x-frame-options']

    const isEmbeddableRoute = routePath.startsWith('/forms/') || routePath.startsWith('/agent-drafts/preview')
    const ancestors = isEmbeddableRoute ? ['*'] : embeddingAncestors(config.allowedEmbedDomains, import.meta.dev)
    const existing = response.headers['Content-Security-Policy'] || response.headers['content-security-policy']
    delete response.headers['content-security-policy']
    response.headers['Content-Security-Policy'] = mergeFramePolicy(existing, ancestors)

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
