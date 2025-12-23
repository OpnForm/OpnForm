import { getCachedFormatter } from '~/components/forms/components/FormSubmissionFormatter'

export function useParseMention(content, mentionsAllowed, form, formData) {
  if (!mentionsAllowed || !form || !formData) {
    return content
  }

  // DOMParser is only available in browser, skip on server
  if (!import.meta.client || typeof DOMParser === 'undefined') {
    return content
  }

  const formatter = getCachedFormatter(form, formData).setOutputStringsOnly()
  const formattedData = formatter.getFormattedData()

  // Create a new DOMParser
  const parser = new DOMParser()
  // Parse the content as HTML
  const doc = parser.parseFromString(content, 'text/html')

  // Find all elements with mention attribute
  const mentionElements = doc.querySelectorAll('[mention], [mention=""]')

  mentionElements.forEach(element => {
    const fieldId = element.getAttribute('mention-field-id')
    const fallback = element.getAttribute('mention-fallback')
    const value = formattedData[fieldId]

    if (value) {
      if (Array.isArray(value)) {
        element.textContent = value.join(', ')
      } else {
        element.textContent = value
      }
    } else if (fallback) {
      element.textContent = fallback
    } else {
      element.remove()
    }
  })

  // Return the processed HTML content
  return doc.body.innerHTML
}
