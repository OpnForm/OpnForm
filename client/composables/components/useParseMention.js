import { getCachedFormatter } from '~/components/forms/components/FormSubmissionFormatter'

// Cache for parsed mention results keyed by content + formData hash
const mentionCache = new Map()
const MAX_CACHE_SIZE = 100

/**
 * Generate a simple hash for cache key based on content and relevant form data values
 */
function generateCacheKey(content, formattedData, mentionFieldIds) {
  // Only include values for fields that are actually mentioned in the content
  const relevantValues = mentionFieldIds.map(id => `${id}:${formattedData[id] ?? ''}`).join('|')
  return `${content}::${relevantValues}`
}

/**
 * Extract mention field IDs from content without full DOM parsing
 * Uses regex for quick extraction - faster than DOMParser for cache key generation
 */
function extractMentionFieldIds(content) {
  const fieldIds = []
  const regex = /mention-field-id="([^"]+)"/g
  let match
  while ((match = regex.exec(content)) !== null) {
    fieldIds.push(match[1])
  }
  return fieldIds
}

/**
 * Parse content and replace mention placeholders with actual form values.
 * Uses caching to avoid re-parsing unchanged content.
 */
export function useParseMention(content, mentionsAllowed, form, formData) {
  if (!mentionsAllowed || !form || !formData) {
    return content
  }

  // DOMParser is only available in browser, skip on server
  if (!import.meta.client || typeof DOMParser === 'undefined') {
    return content
  }

  // Quick check: if no mentions in content, return as-is
  if (!content || !content.includes('mention-field-id')) {
    return content
  }

  const formatter = getCachedFormatter(form, formData).setOutputStringsOnly()
  const formattedData = formatter.getFormattedData()

  // Generate cache key based on content and only the relevant field values
  const mentionFieldIds = extractMentionFieldIds(content)
  const cacheKey = generateCacheKey(content, formattedData, mentionFieldIds)

  // Return cached result if available
  if (mentionCache.has(cacheKey)) {
    return mentionCache.get(cacheKey)
  }

  // Parse and process mentions
  const parser = new DOMParser()
  const doc = parser.parseFromString(content, 'text/html')
  const mentionElements = doc.querySelectorAll('[mention], [mention=""]')

  mentionElements.forEach(element => {
    const fieldId = element.getAttribute('mention-field-id')
    const fallback = element.getAttribute('mention-fallback')
    const value = formattedData[fieldId]

    if (value !== undefined && value !== null && value !== '') {
      if (Array.isArray(value)) {
        element.textContent = value.join(', ')
      } else {
        element.textContent = value
      }
    } else if (fallback) {
      element.textContent = fallback
    } else {
      // Show empty string instead of removing to avoid layout shifts
      // and gracefully handle deleted fields
      element.textContent = ''
    }
  })

  const result = doc.body.innerHTML

  // Cache the result with LRU-style eviction
  if (mentionCache.size >= MAX_CACHE_SIZE) {
    // Remove oldest entry (first key in Map iteration order)
    const firstKey = mentionCache.keys().next().value
    mentionCache.delete(firstKey)
  }
  mentionCache.set(cacheKey, result)

  return result
}

/**
 * Clear the mention parsing cache.
 * Called when navigating away from forms to prevent memory leaks.
 */
export function clearMentionCache() {
  mentionCache.clear()
}
