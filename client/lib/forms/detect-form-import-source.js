export function normalizeImportUrl(url) {
  const trimmedUrl = url?.trim() || ''

  if (!trimmedUrl) {
    return ''
  }

  if (/^https?:\/\//i.test(trimmedUrl)) {
    return trimmedUrl
  }

  return `https://${trimmedUrl}`
}

export function detectFormImportSource(url) {
  const normalizedUrl = normalizeImportUrl(url)

  if (!normalizedUrl) {
    return { source: null, normalizedUrl: '', reason: null }
  }

  let parsedUrl
  try {
    parsedUrl = new URL(normalizedUrl)
  } catch {
    return { source: null, normalizedUrl, reason: 'invalid_url' }
  }

  const host = parsedUrl.hostname.toLowerCase()
  const path = parsedUrl.pathname

  if (host === 'tally.so') {
    return { source: 'tally', normalizedUrl, reason: null }
  }

  if (host === 'fillout.com' || host.endsWith('.fillout.com')) {
    return { source: 'fillout', normalizedUrl, reason: null }
  }

  if (host === 'docs.google.com' && path.startsWith('/forms/')) {
    if (/\/forms\/d\/e\//.test(path)) {
      return { source: 'google_forms', normalizedUrl, reason: 'google_published_url' }
    }

    if (!/\/forms\/d\/[a-zA-Z0-9_-]+/.test(path)) {
      return { source: 'google_forms', normalizedUrl, reason: 'google_edit_url' }
    }

    return { source: 'google_forms', normalizedUrl, reason: null }
  }

  if (host === 'typeform.com' || host.endsWith('.typeform.com')) {
    if (!/\/to\/[a-zA-Z0-9]+/.test(path)) {
      return { source: 'typeform', normalizedUrl, reason: 'typeform_form_id' }
    }

    return { source: 'typeform', normalizedUrl, reason: null }
  }

  return { source: null, normalizedUrl, reason: 'unsupported_provider' }
}
