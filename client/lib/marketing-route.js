// This allowlist never includes private app routes, auth callbacks or setup.
export function isMarketingRoute(route) {
  return /^\/(?:$|pricing\/?$|features\/?$|enterprise\/?$|industry(?:\/|$)|integrations(?:\/|$)|opnform-vs-[a-z0-9-]+\/?$|ai-form-builder\/?$|mcp\/?$)/.test(route.path)
}
