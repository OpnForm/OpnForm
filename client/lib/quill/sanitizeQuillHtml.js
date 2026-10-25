import DOMPurify from 'dompurify'

// Quill's formula/video exporters interpolate Delta values into HTML. Sanitize
// before the exported string reaches modelValue, preserving mention metadata
// and escaped literal text without decoding it into markup.
export function sanitizeQuillHtml(html) {
  const sanitized = DOMPurify.sanitize(html, {
    USE_PROFILES: { html: true },
    ADD_ATTR: ['mention', 'mention-field-id', 'mention-field-name', 'mention-fallback', 'contenteditable', 'target'],
    FORBID_TAGS: ['iframe', 'object', 'embed'],
    RETURN_DOM: true,
  })
  // Quill exports links in a new tab so respondents keep their current form.
  // Limit this permission to _blank and prevent access to the opener window.
  sanitized.querySelectorAll('[target]').forEach((link) => {
    if (link.tagName === 'A' && link.getAttribute('target') === '_blank') {
      link.setAttribute('rel', 'noopener noreferrer')
    } else {
      link.removeAttribute('target')
    }
  })
  return sanitized.innerHTML
}
