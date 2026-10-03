export function mergeFramePolicy(existing, ancestors) {
  const directives = String(existing || '').split(';').map(value => value.trim())
    .filter(value => value && !/^frame-ancestors(?:\s|$)/i.test(value))
  directives.push(`frame-ancestors ${ancestors.join(' ')}`)
  return directives.join('; ') + ';'
}

export function embeddingAncestors(allowedDomains, development = false) {
  const origins = ["'self'"]
  if (development) {
    for (const port of ['', ':3000', ':3001', ':4200', ':5000', ':5173', ':8000', ':8080', ':8081', ':9000']) {
      for (const host of ['localhost', '127.0.0.1']) {
        origins.push(`http://${host}${port}`, `https://${host}${port}`)
      }
    }
  }
  for (const value of String(allowedDomains || '').split(',')) {
    const domain = value.trim()
    if (!domain) continue
    try {
      const url = new URL(domain.includes('://') ? domain : `https://${domain}`)
      if (['https:', 'http:'].includes(url.protocol) && !url.username && !url.password) origins.push(url.origin)
    } catch { /* Ignore malformed configured origins. */ }
  }
  return [...new Set(origins)]
}
