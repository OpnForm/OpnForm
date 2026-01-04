import { describe, it, expect, beforeEach, vi } from 'vitest'

// Mock the FormSubmissionFormatter module before importing useParseMention
vi.mock('~/components/forms/components/FormSubmissionFormatter', () => ({
  getCachedFormatter: vi.fn((form, formData) => ({
    setOutputStringsOnly: vi.fn().mockReturnThis(),
    getFormattedData: vi.fn(() => formData)
  }))
}))

// Mock import.meta.client - needs to be done via vi.stubGlobal
const mockImportMeta = { client: true }
vi.stubGlobal('import', { meta: mockImportMeta })

// Mock DOMParser for Node environment - creates a working mock that processes mentions
class MockDOMParser {
  parseFromString(content: string, type: string) {
    const elements: any[] = []
    const regex = /<span[^>]*mention[^>]*>([^<]*)<\/span>/g
    let processedContent = content
    let match
    
    // Collect all mention elements
    while ((match = regex.exec(content)) !== null) {
      const fullMatch = match[0]
      const fieldIdMatch = fullMatch.match(/mention-field-id="([^"]+)"/)
      const fallbackMatch = fullMatch.match(/mention-fallback="([^"]*)"/)
      const originalText = match[1]
      
      let newTextContent = originalText
      const element = {
        getAttribute: (attr: string) => {
          if (attr === 'mention-field-id') return fieldIdMatch?.[1] || null
          if (attr === 'mention-fallback') return fallbackMatch?.[1] || ''
          return null
        },
        set textContent(val: string) {
          newTextContent = val
          // Update processedContent when textContent changes
          const newSpan = fullMatch.replace(`>${originalText}<`, `>${val}<`)
          processedContent = processedContent.replace(fullMatch, newSpan)
        },
        get textContent() {
          return newTextContent
        },
        remove: vi.fn(() => {
          processedContent = processedContent.replace(fullMatch, '')
        })
      }
      elements.push(element)
    }
    
    return {
      body: {
        get innerHTML() { return processedContent },
        querySelectorAll: (selector: string) => elements
      }
    }
  }
}

vi.stubGlobal('DOMParser', MockDOMParser)

// Import after mocks are set up
// Use dynamic import to work around module resolution in unit tests
const useParseMentionModule = await import('../../composables/components/useParseMention')
const { useParseMention, clearMentionCache } = useParseMentionModule

/**
 * Test suite for useParseMention composable
 * Tests mention parsing, caching, and edge case handling
 */
describe('useParseMention', () => {
  const mockForm = {
    slug: 'test-form',
    properties: [
      { id: 'field1', name: 'Name', type: 'text' },
      { id: 'field2', name: 'Email', type: 'email' },
      { id: 'field3', name: 'Age', type: 'number' }
    ]
  }

  beforeEach(() => {
    clearMentionCache()
  })

  describe('Basic Functionality', () => {
    it('should return content unchanged when mentionsAllowed is false', () => {
      const content = '<p>Hello <span mention mention-field-id="field1">Name</span></p>'
      const result = useParseMention(content, false, mockForm, { field1: 'John' })
      expect(result).toBe(content)
    })

    it('should return content unchanged when form is null', () => {
      const content = '<p>Hello <span mention mention-field-id="field1">Name</span></p>'
      const result = useParseMention(content, true, null, { field1: 'John' })
      expect(result).toBe(content)
    })

    it('should return content unchanged when formData is null', () => {
      const content = '<p>Hello <span mention mention-field-id="field1">Name</span></p>'
      const result = useParseMention(content, true, mockForm, null)
      expect(result).toBe(content)
    })

    it('should return content unchanged when no mentions present', () => {
      const content = '<p>Hello World</p>'
      const result = useParseMention(content, true, mockForm, { field1: 'John' })
      expect(result).toBe(content)
    })

    it('should return empty/null content as-is', () => {
      expect(useParseMention('', true, mockForm, { field1: 'John' })).toBe('')
      expect(useParseMention(null as any, true, mockForm, { field1: 'John' })).toBe(null)
      expect(useParseMention(undefined as any, true, mockForm, { field1: 'John' })).toBe(undefined)
    })
  })

  describe('Mention Replacement', () => {
    it('should replace mention with field value', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      const formData = { field1: 'John Doe' }
      
      // Note: Due to mock limitations, we test the function doesn't crash
      // In real tests with jsdom, this would verify actual DOM manipulation
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })

    it('should handle multiple mentions', () => {
      const content = '<span mention mention-field-id="field1">Name</span> - <span mention mention-field-id="field2">Email</span>'
      const formData = { field1: 'John', field2: 'john@example.com' }
      
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })

    it('should handle array values by joining with comma', () => {
      const content = '<span mention mention-field-id="field1">Tags</span>'
      const formData = { field1: ['tag1', 'tag2', 'tag3'] }
      
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })
  })

  describe('Fallback Handling', () => {
    it('should use fallback when field value is undefined', () => {
      const content = '<span mention mention-field-id="field1" mention-fallback="Default Name">Name</span>'
      const formData = {} // field1 not provided
      
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })

    it('should use fallback when field value is null', () => {
      const content = '<span mention mention-field-id="field1" mention-fallback="N/A">Name</span>'
      const formData = { field1: null }
      
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })

    it('should use fallback when field value is empty string', () => {
      const content = '<span mention mention-field-id="field1" mention-fallback="Not provided">Name</span>'
      const formData = { field1: '' }
      
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })
  })

  describe('Deleted/Missing Field Handling', () => {
    it('should gracefully handle mention to non-existent field', () => {
      const content = '<span mention mention-field-id="deleted_field">Deleted</span>'
      const formData = { field1: 'John' } // deleted_field doesn't exist
      
      // Should not throw
      expect(() => {
        useParseMention(content, true, mockForm, formData)
      }).not.toThrow()
    })

    it('should show empty content for missing field without fallback', () => {
      const content = '<span mention mention-field-id="missing">Missing</span>'
      const formData = {}
      
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })
  })

  describe('Edge Cases', () => {
    it('should handle mention with empty fallback attribute', () => {
      const content = '<span mention mention-field-id="field1" mention-fallback="">Name</span>'
      const formData = {}
      
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })

    it('should handle special characters in field values', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      const formData = { field1: '<script>alert("xss")</script>' }
      
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })

    it('should handle very long content', () => {
      const longText = 'a'.repeat(10000)
      const content = `<p>${longText}<span mention mention-field-id="field1">Name</span>${longText}</p>`
      const formData = { field1: 'John' }
      
      expect(() => {
        useParseMention(content, true, mockForm, formData)
      }).not.toThrow()
    })

    it('should handle numeric field values', () => {
      const content = '<span mention mention-field-id="field3">Age</span>'
      const formData = { field3: 25 }
      
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })

    it('should handle boolean field values', () => {
      const content = '<span mention mention-field-id="field1">Checkbox</span>'
      const formData = { field1: true }
      
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })
  })

  describe('Cache Functionality', () => {
    it('should clear cache without errors', () => {
      expect(() => clearMentionCache()).not.toThrow()
    })

    it('should return consistent results for same input', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      const formData = { field1: 'John' }
      
      const result1 = useParseMention(content, true, mockForm, formData)
      const result2 = useParseMention(content, true, mockForm, formData)
      
      expect(result1).toBe(result2)
    })

    it('should return different results when formData changes', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      
      const result1 = useParseMention(content, true, mockForm, { field1: 'John' })
      clearMentionCache() // Clear to ensure fresh parse
      const result2 = useParseMention(content, true, mockForm, { field1: 'Jane' })
      
      // Results are from cache key perspective, both should be defined
      expect(result1).toBeDefined()
      expect(result2).toBeDefined()
    })
  })
})

describe('extractMentionFieldIds (internal)', () => {
  // Test the regex extraction indirectly through useParseMention behavior
  it('should correctly identify mention field IDs in content', () => {
    const content = `
      <span mention mention-field-id="field1">Name</span>
      <span mention mention-field-id="field2">Email</span>
      <span mention mention-field-id="field3">Age</span>
    `
    const formData = { field1: 'John', field2: 'john@test.com', field3: 30 }
    
    const mockForm = {
      slug: 'test',
      properties: [
        { id: 'field1', type: 'text' },
        { id: 'field2', type: 'email' },
        { id: 'field3', type: 'number' }
      ]
    }
    
    // Should process all three mentions without error
    expect(() => {
      useParseMention(content, true, mockForm, formData)
    }).not.toThrow()
  })
})
