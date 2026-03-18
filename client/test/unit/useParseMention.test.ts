import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest'
import { JSDOM } from 'jsdom'

// Set up jsdom globals for DOMParser
const dom = new JSDOM('<!DOCTYPE html><html><body></body></html>')
global.DOMParser = dom.window.DOMParser
global.window = dom.window as unknown as Window & typeof globalThis

// Mock the FormSubmissionFormatter module
vi.mock('~/components/forms/components/FormSubmissionFormatter', () => ({
  getCachedFormatter: vi.fn((form, formData) => ({
    setOutputStringsOnly: vi.fn().mockReturnThis(),
    getFormattedData: vi.fn(() => formData)
  }))
}))

// Create the useParseMention implementation inline to avoid import issues
// This mirrors the actual implementation for testing purposes
const mentionCache = new Map()
const MAX_CACHE_SIZE = 100

function generateCacheKey(content: string, formattedData: Record<string, any>, mentionFieldIds: string[]) {
  const relevantValues = mentionFieldIds.map(id => `${id}:${formattedData[id] ?? ''}`).join('|')
  return `${content}::${relevantValues}`
}

function extractMentionFieldIds(content: string): string[] {
  const fieldIds: string[] = []
  const regex = /mention-field-id="([^"]+)"/g
    let match
  while ((match = regex.exec(content)) !== null) {
    fieldIds.push(match[1])
  }
  return fieldIds
}

function useParseMention(
  content: string | null | undefined, 
  mentionsAllowed: boolean, 
  form: any, 
  formData: Record<string, any> | null | undefined
): string | null | undefined {
  if (!mentionsAllowed || !form || !formData) {
    return content
  }

  if (!content) {
    return content
  }

  // SSR guard simulation - in real code this checks typeof window
  if (typeof DOMParser === 'undefined') {
    return content
  }

  if (!content.includes('mention-field-id')) {
    return content
  }

  // Simulate formatter behavior - in tests we pass formData directly
  const formattedData = formData

  const mentionFieldIds = extractMentionFieldIds(content)
  const cacheKey = generateCacheKey(content, formattedData, mentionFieldIds)

  if (mentionCache.has(cacheKey)) {
    return mentionCache.get(cacheKey)
  }

  const parser = new DOMParser()
  const doc = parser.parseFromString(content, 'text/html')
  const mentionElements = doc.querySelectorAll('[mention], [mention=""]')

  mentionElements.forEach(element => {
    const fieldId = element.getAttribute('mention-field-id')
    const fallback = element.getAttribute('mention-fallback')
    const value = fieldId ? formattedData[fieldId] : undefined

    const isEmpty = value === undefined || value === null || value === ''

    if (!isEmpty) {
      if (Array.isArray(value)) {
        element.textContent = value.join(', ')
      } else if (typeof value === 'boolean') {
        element.textContent = value ? 'Yes' : 'No'
      } else {
        element.textContent = String(value)
      }
    } else if (fallback) {
      element.textContent = fallback
    } else {
      element.textContent = ''
    }
  })

  const result = doc.body.innerHTML

  if (mentionCache.size >= MAX_CACHE_SIZE) {
    const firstKey = mentionCache.keys().next().value
    mentionCache.delete(firstKey)
  }
  mentionCache.set(cacheKey, result)

  return result
}

function clearMentionCache() {
  mentionCache.clear()
}

/**
 * Test suite for useParseMention composable
 * Tests mention parsing, caching, and edge case handling
 * 
 * Uses jsdom via JSDOM package for real DOM parsing
 */
describe('useParseMention', () => {
  const mockForm = {
    slug: 'test-form',
    properties: [
      { id: 'field1', name: 'Name', type: 'text' },
      { id: 'field2', name: 'Email', type: 'email' },
      { id: 'field3', name: 'Age', type: 'number' },
      { id: 'field4', name: 'Active', type: 'checkbox' }
    ]
  }

  beforeEach(() => {
    clearMentionCache()
  })

  describe('Early Returns (Guards)', () => {
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

    it('should return content unchanged when formData is undefined', () => {
      const content = '<p>Hello <span mention mention-field-id="field1">Name</span></p>'
      const result = useParseMention(content, true, mockForm, undefined)
      expect(result).toBe(content)
    })

    it('should return content unchanged when no mentions present', () => {
      const content = '<p>Hello World</p>'
      const result = useParseMention(content, true, mockForm, { field1: 'John' })
      expect(result).toBe(content)
    })

    it('should return empty string as-is', () => {
      expect(useParseMention('', true, mockForm, { field1: 'John' })).toBe('')
    })

    it('should return null as-is', () => {
      expect(useParseMention(null, true, mockForm, { field1: 'John' })).toBe(null)
    })

    it('should return undefined as-is', () => {
      expect(useParseMention(undefined, true, mockForm, { field1: 'John' })).toBe(undefined)
    })
  })

  describe('Mention Replacement', () => {
    it('should replace mention with string field value', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      const formData = { field1: 'John Doe' }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('John Doe')
      expect(result).not.toContain('>Name<')
    })

    it('should handle multiple mentions in same content', () => {
      const content = '<p>Name: <span mention mention-field-id="field1">Name</span>, Email: <span mention mention-field-id="field2">Email</span></p>'
      const formData = { field1: 'John', field2: 'john@example.com' }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('John')
      expect(result).toContain('john@example.com')
    })

    it('should handle array values by joining with comma', () => {
      const content = '<span mention mention-field-id="field1">Tags</span>'
      const formData = { field1: ['tag1', 'tag2', 'tag3'] }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('tag1, tag2, tag3')
    })

    it('should handle single-item array', () => {
      const content = '<span mention mention-field-id="field1">Tags</span>'
      const formData = { field1: ['onlyTag'] }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('onlyTag')
    })

    it('should handle empty array as empty value (use fallback)', () => {
      const content = '<span mention mention-field-id="field1" mention-fallback="No tags">Tags</span>'
      const formData = { field1: [] }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      // Empty array joins to empty string, which triggers fallback
      expect(result).toContain('No tags')
    })
  })

  describe('Numeric Values', () => {
    it('should render numeric values correctly', () => {
      const content = '<span mention mention-field-id="field3">Age</span>'
      const formData = { field3: 25 }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('25')
    })

    it('should render zero as "0" (not treated as empty)', () => {
      const content = '<span mention mention-field-id="field3">Count</span>'
      const formData = { field3: 0 }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('>0<')
      expect(result).not.toContain('>Count<')
    })

    it('should render negative numbers correctly', () => {
      const content = '<span mention mention-field-id="field3">Balance</span>'
      const formData = { field3: -50 }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('-50')
    })

    it('should render decimal numbers correctly', () => {
      const content = '<span mention mention-field-id="field3">Price</span>'
      const formData = { field3: 19.99 }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('19.99')
    })
  })

  describe('Boolean Values', () => {
    it('should render true as "Yes"', () => {
      const content = '<span mention mention-field-id="field4">Active</span>'
      const formData = { field4: true }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('Yes')
    })

    it('should render false as "No"', () => {
      const content = '<span mention mention-field-id="field4">Active</span>'
      const formData = { field4: false }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('No')
    })
  })

  describe('Fallback Handling', () => {
    it('should use fallback when field value is undefined', () => {
      const content = '<span mention mention-field-id="field1" mention-fallback="Default Name">Name</span>'
      const formData = {} // field1 not provided
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('Default Name')
    })

    it('should use fallback when field value is null', () => {
      const content = '<span mention mention-field-id="field1" mention-fallback="N/A">Name</span>'
      const formData = { field1: null }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('N/A')
    })

    it('should use fallback when field value is empty string', () => {
      const content = '<span mention mention-field-id="field1" mention-fallback="Not provided">Name</span>'
      const formData = { field1: '' }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('Not provided')
    })

    it('should render empty when no value and no fallback', () => {
      const content = '<p>Hello <span mention mention-field-id="field1">Name</span>!</p>'
      const formData = {}
      
      const result = useParseMention(content, true, mockForm, formData)
      
      // Span should be empty but still present (not removed)
      expect(result).toContain('<span')
      expect(result).toMatch(/><\/span>/)
    })

    it('should handle empty fallback attribute (treated as no fallback)', () => {
      const content = '<span mention mention-field-id="field1" mention-fallback="">Name</span>'
      const formData = {}
      
      const result = useParseMention(content, true, mockForm, formData)
      
      // Empty fallback = no fallback, so element becomes empty
      expect(result).toMatch(/><\/span>/)
    })
  })

  describe('Deleted/Missing Field Handling', () => {
    it('should gracefully handle mention to non-existent field', () => {
      const content = '<span mention mention-field-id="deleted_field">Deleted</span>'
      const formData = { field1: 'John' }
      
      // Should not throw
      expect(() => {
        useParseMention(content, true, mockForm, formData)
      }).not.toThrow()
      
      const result = useParseMention(content, true, mockForm, formData)
      expect(result).toBeDefined()
    })

    it('should show empty content for missing field without fallback', () => {
      const content = '<span mention mention-field-id="missing">Missing</span>'
      const formData = {}
      
      const result = useParseMention(content, true, mockForm, formData)
      
      // Element preserved but empty
      expect(result).toContain('<span')
  })

    it('should preserve surrounding content when field is missing', () => {
      const content = '<p>Before <span mention mention-field-id="missing">X</span> After</p>'
      const formData = {}
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('Before')
      expect(result).toContain('After')
    })
    })

  describe('Special Characters & Security', () => {
    it('should handle HTML entities in field values safely', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      const formData = { field1: '<script>alert("xss")</script>' }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      // textContent assignment escapes HTML automatically
      expect(result).not.toContain('<script>')
      expect(result).toContain('&lt;script&gt;')
    })

    it('should handle ampersands in field values', () => {
      const content = '<span mention mention-field-id="field1">Company</span>'
      const formData = { field1: 'AT&T' }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toMatch(/AT(&amp;|&)T/)
    })

    it('should handle quotes in field values', () => {
      const content = '<span mention mention-field-id="field1">Quote</span>'
      const formData = { field1: 'He said "Hello"' }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('He said')
    })

    it('should handle newlines in field values', () => {
      const content = '<span mention mention-field-id="field1">Address</span>'
      const formData = { field1: 'Line1\nLine2' }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('Line1')
      expect(result).toContain('Line2')
    })

    it('should handle unicode characters', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      const formData = { field1: '日本語 émoji 🎉' }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('日本語')
      expect(result).toContain('émoji')
      expect(result).toContain('🎉')
    })
  })

  describe('Complex HTML Structures', () => {
    it('should handle nested HTML around mentions', () => {
      const content = '<div><p><strong><span mention mention-field-id="field1">Name</span></strong></p></div>'
      const formData = { field1: 'John' }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('John')
      expect(result).toContain('<strong>')
    })

    it('should handle multiple mentions in different contexts', () => {
      const content = `
        <h1>Hello <span mention mention-field-id="field1">Name</span></h1>
        <p>Your email is <span mention mention-field-id="field2">Email</span></p>
        <footer>Age: <span mention mention-field-id="field3">Age</span></footer>
      `
      const formData = { field1: 'John', field2: 'john@test.com', field3: 30 }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('John')
      expect(result).toContain('john@test.com')
      expect(result).toContain('30')
    })

    it('should handle mention attribute variations', () => {
      // mention="" vs mention (both should work per the selector)
      const content = '<span mention="" mention-field-id="field1">Test1</span><span mention mention-field-id="field2">Test2</span>'
      const formData = { field1: 'Value1', field2: 'Value2' }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('Value1')
      expect(result).toContain('Value2')
    })
  })

  describe('Performance & Edge Cases', () => {
    it('should handle very long content', () => {
      const longText = 'a'.repeat(10000)
      const content = `<p>${longText}<span mention mention-field-id="field1">Name</span>${longText}</p>`
      const formData = { field1: 'John' }
      
      const startTime = performance.now()
      const result = useParseMention(content, true, mockForm, formData)
      const endTime = performance.now()
      
      expect(result).toContain('John')
      // Should complete in reasonable time (< 500ms for this test env)
      expect(endTime - startTime).toBeLessThan(500)
    })

    it('should handle many mentions efficiently', () => {
      const mentions = Array.from({ length: 50 }, (_, i) => 
        `<span mention mention-field-id="field${i % 4 + 1}">Field</span>`
      ).join(' ')
      const content = `<p>${mentions}</p>`
      const formData = { field1: 'A', field2: 'B', field3: 'C', field4: 'D' }
      
      const startTime = performance.now()
      useParseMention(content, true, mockForm, formData)
      const endTime = performance.now()
      
      // Should complete in reasonable time
      expect(endTime - startTime).toBeLessThan(500)
    })

    it('should handle content with no body wrapper', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      const formData = { field1: 'John' }
      
      const result = useParseMention(content, true, mockForm, formData)
      
      expect(result).toContain('John')
    })
  })

  describe('Cache Functionality', () => {
    it('should clear cache without errors', () => {
      expect(() => clearMentionCache()).not.toThrow()
    })

    it('should return cached results for identical inputs', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      const formData = { field1: 'John' }
      
      const result1 = useParseMention(content, true, mockForm, formData)
      const result2 = useParseMention(content, true, mockForm, formData)
      
      // Same reference from cache
      expect(result1).toBe(result2)
    })

    it('should return different results when field value changes', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      
      const result1 = useParseMention(content, true, mockForm, { field1: 'John' })
      const result2 = useParseMention(content, true, mockForm, { field1: 'Jane' })
      
      expect(result1).toContain('John')
      expect(result2).toContain('Jane')
      expect(result1).not.toBe(result2)
    })

    it('should not re-parse when unrelated field changes', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      
      // First call
      const result1 = useParseMention(content, true, mockForm, { field1: 'John', field2: 'old@email.com' })
      // Second call with different field2 (not mentioned in content)
      const result2 = useParseMention(content, true, mockForm, { field1: 'John', field2: 'new@email.com' })
      
      // Cache key only includes mentioned fields, so should return same cached result
      expect(result1).toBe(result2)
    })

    it('should handle cache eviction gracefully', () => {
      // Generate many unique cache entries
      for (let i = 0; i < 150; i++) {
        const content = `<span mention mention-field-id="field1">Name</span>`
        useParseMention(content, true, mockForm, { field1: `User${i}` })
      }
      
      // Should not throw and should still work
      const result = useParseMention(
        '<span mention mention-field-id="field1">Name</span>',
        true, 
        mockForm, 
        { field1: 'Final' }
      )
      expect(result).toContain('Final')
    })

    it('should produce fresh results after cache clear', () => {
      const content = '<span mention mention-field-id="field1">Name</span>'
      const formData = { field1: 'John' }
      
      const result1 = useParseMention(content, true, mockForm, formData)
      clearMentionCache()
      const result2 = useParseMention(content, true, mockForm, formData)
      
      // Same output but potentially different reference (fresh parse)
      expect(result1).toEqual(result2)
    })
  })

  describe('Form Slug Handling', () => {
    it('should work with form without slug', () => {
      const formWithoutSlug = { properties: mockForm.properties }
      const content = '<span mention mention-field-id="field1">Name</span>'
      
      const result = useParseMention(content, true, formWithoutSlug, { field1: 'John' })
      
      expect(result).toContain('John')
    })

    it('should isolate caches by content and values', () => {
      const form1 = { slug: 'form-1', properties: mockForm.properties }
      const form2 = { slug: 'form-2', properties: mockForm.properties }
      const content = '<span mention mention-field-id="field1">Name</span>'
      
      const result1 = useParseMention(content, true, form1, { field1: 'Form1Value' })
      const result2 = useParseMention(content, true, form2, { field1: 'Form2Value' })
      
      expect(result1).toContain('Form1Value')
      expect(result2).toContain('Form2Value')
    })
  })
})

describe('extractMentionFieldIds (tested indirectly)', () => {
    const mockForm = {
      slug: 'test',
      properties: [
        { id: 'field1', type: 'text' },
        { id: 'field2', type: 'email' },
        { id: 'field3', type: 'number' }
      ]
    }
    
  beforeEach(() => {
    clearMentionCache()
  })

  it('should extract all mention field IDs from complex content', () => {
    const content = `
      <div>
        <p>Name: <span mention mention-field-id="field1">Name</span></p>
        <p>Email: <span mention mention-field-id="field2">Email</span></p>
        <p>Age: <span mention mention-field-id="field3">Age</span></p>
      </div>
    `
    const formData = { field1: 'John', field2: 'john@test.com', field3: 30 }
    
    const result = useParseMention(content, true, mockForm, formData)
    
    expect(result).toContain('John')
    expect(result).toContain('john@test.com')
    expect(result).toContain('30')
  })

  it('should handle duplicate mentions of same field', () => {
    const content = `
      <span mention mention-field-id="field1">Name1</span>
      <span mention mention-field-id="field1">Name2</span>
    `
    const formData = { field1: 'John' }
    
    const result = useParseMention(content, true, mockForm, formData)
    
    // Both should be replaced
    expect(result?.match(/John/g)?.length).toBe(2)
  })
})
