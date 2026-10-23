import DOMPurify from 'dompurify'

// Quill's formula/video exporters interpolate Delta values into HTML. Sanitize
// before the exported string reaches modelValue, preserving mention metadata
// and escaped literal text without decoding it into markup.
export function sanitizeQuillHtml(html) {
  return DOMPurify.sanitize(html, {
    USE_PROFILES: { html: true },
    ADD_ATTR: ['mention', 'mention-field-id', 'mention-field-name', 'mention-fallback', 'contenteditable'],
    FORBID_TAGS: ['iframe', 'object', 'embed'],
  })
}
