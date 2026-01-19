import { describe, it, expect, beforeEach } from 'vitest'
import { 
  FormSubmissionFormatter, 
  getCachedFormatter, 
  clearFormatterCache 
} from '../../components/forms/components/FormSubmissionFormatter.js'

/**
 * Test suite for FormSubmissionFormatter
 * Tests formatting, caching, and configuration reset behavior
 */
describe('FormSubmissionFormatter', () => {
  const createMockForm = (properties: any[] = []) => ({
    slug: 'test-form',
    properties
  })

  const createMockFormData = (data: Record<string, any> = {}) => data

  beforeEach(() => {
    clearFormatterCache()
  })

  describe('Basic Formatting', () => {
    it('should format text field values', () => {
      const form = createMockForm([
        { id: 'name', name: 'Name', type: 'text' }
      ])
      const formData = { name: 'John Doe' }
      
      const formatter = new FormSubmissionFormatter(form, formData)
      const result = formatter.getFormattedData()
      
      expect(result.name).toBe('John Doe')
    })

    it('should format checkbox as Yes when true', () => {
      const form = createMockForm([
        { id: 'agree', name: 'Agree', type: 'checkbox' }
      ])
      
      const formatterTrue = new FormSubmissionFormatter(form, { agree: true })
      expect(formatterTrue.getFormattedData().agree).toBe('Yes')
    })

    it('should skip checkbox field when value is false (falsy)', () => {
      // Note: This is existing behavior - falsy values are skipped in getFormattedData
      const form = createMockForm([
        { id: 'agree', name: 'Agree', type: 'checkbox' }
      ])
      
      const formatterFalse = new FormSubmissionFormatter(form, { agree: false })
      expect(formatterFalse.getFormattedData().agree).toBeUndefined()
    })

    it('should format multi_select as comma-separated string when outputStringsOnly', () => {
      const form = createMockForm([
        { id: 'tags', name: 'Tags', type: 'multi_select' }
      ])
      const formData = { tags: ['tag1', 'tag2', 'tag3'] }
      
      const formatter = new FormSubmissionFormatter(form, formData).setOutputStringsOnly()
      const result = formatter.getFormattedData()
      
      expect(result.tags).toBe('tag1, tag2, tag3')
    })

    it('should return array for multi_select when not outputStringsOnly', () => {
      const form = createMockForm([
        { id: 'tags', name: 'Tags', type: 'multi_select' }
      ])
      const formData = { tags: ['tag1', 'tag2', 'tag3'] }
      
      const formatter = new FormSubmissionFormatter(form, formData)
      const result = formatter.getFormattedData()
      
      expect(result.tags).toEqual(['tag1', 'tag2', 'tag3'])
    })

    it('should skip fields with no value', () => {
      const form = createMockForm([
        { id: 'name', name: 'Name', type: 'text' },
        { id: 'email', name: 'Email', type: 'email' }
      ])
      const formData = { name: 'John' } // email not provided
      
      const formatter = new FormSubmissionFormatter(form, formData)
      const result = formatter.getFormattedData()
      
      expect(result.name).toBe('John')
      expect(result.email).toBeUndefined()
    })
  })

  describe('URL and Email Formatting', () => {
    it('should create links for URL fields when createLinks enabled', () => {
      const form = createMockForm([
        { id: 'website', name: 'Website', type: 'url' }
      ])
      const formData = { website: 'https://example.com' }
      
      const formatter = new FormSubmissionFormatter(form, formData).setCreateLinks()
      const result = formatter.getFormattedData()
      
      expect(result.website).toBe('<a href="https://example.com">https://example.com</a>')
    })

    it('should create mailto links for email fields when createLinks enabled', () => {
      const form = createMockForm([
        { id: 'email', name: 'Email', type: 'email' }
      ])
      const formData = { email: 'test@example.com' }
      
      const formatter = new FormSubmissionFormatter(form, formData).setCreateLinks()
      const result = formatter.getFormattedData()
      
      expect(result.email).toBe('<a href="mailto:test@example.com">test@example.com</a>')
    })

    it('should not create links when createLinks disabled', () => {
      const form = createMockForm([
        { id: 'website', name: 'Website', type: 'url' }
      ])
      const formData = { website: 'https://example.com' }
      
      const formatter = new FormSubmissionFormatter(form, formData)
      const result = formatter.getFormattedData()
      
      expect(result.website).toBe('https://example.com')
    })
  })

  describe('Configuration Methods', () => {
    it('should chain configuration methods', () => {
      const form = createMockForm([])
      const formData = {}
      
      const formatter = new FormSubmissionFormatter(form, formData)
        .setCreateLinks()
        .setOutputStringsOnly()
        .setShowGeneratedIds()
        .setUseIsoFormatForDates()
      
      expect(formatter).toBeInstanceOf(FormSubmissionFormatter)
    })
  })

  describe('updateData Method', () => {
    it('should update form and formData', () => {
      const form1 = createMockForm([{ id: 'field1', name: 'Field 1', type: 'text' }])
      const form2 = createMockForm([{ id: 'field2', name: 'Field 2', type: 'text' }])
      
      const formatter = new FormSubmissionFormatter(form1, { field1: 'value1' })
      expect(formatter.getFormattedData().field1).toBe('value1')
      
      formatter.updateData(form2, { field2: 'value2' })
      expect(formatter.getFormattedData().field2).toBe('value2')
      expect(formatter.getFormattedData().field1).toBeUndefined()
    })

    it('should reset configuration flags on updateData', () => {
      const form = createMockForm([
        { id: 'url', name: 'URL', type: 'url' }
      ])
      
      const formatter = new FormSubmissionFormatter(form, { url: 'https://test.com' })
      formatter.setCreateLinks()
      
      // Verify links are created
      expect(formatter.getFormattedData().url).toContain('<a href')
      
      // Update data (should reset flags)
      formatter.updateData(form, { url: 'https://new.com' })
      
      // Links should NOT be created anymore
      expect(formatter.getFormattedData().url).toBe('https://new.com')
    })

    it('should reset outputStringsOnly flag on updateData', () => {
      const form = createMockForm([
        { id: 'tags', name: 'Tags', type: 'multi_select' }
      ])
      
      const formatter = new FormSubmissionFormatter(form, { tags: ['a', 'b'] })
      formatter.setOutputStringsOnly()
      
      // Verify strings only
      expect(formatter.getFormattedData().tags).toBe('a, b')
      
      // Update data (should reset flags)
      formatter.updateData(form, { tags: ['c', 'd'] })
      
      // Should return array now
      expect(formatter.getFormattedData().tags).toEqual(['c', 'd'])
    })
  })
})

describe('Formatter Caching', () => {
  const createMockForm = (slug: string) => ({
    slug,
    properties: [{ id: 'field1', name: 'Field 1', type: 'text' }]
  })

  beforeEach(() => {
    clearFormatterCache()
  })

  describe('getCachedFormatter', () => {
    it('should return a formatter instance', () => {
      const form = createMockForm('test-form')
      const formatter = getCachedFormatter(form, { field1: 'value' })
      
      expect(formatter).toBeInstanceOf(FormSubmissionFormatter)
    })

    it('should return same instance for same form slug', () => {
      const form = createMockForm('test-form')
      
      const formatter1 = getCachedFormatter(form, { field1: 'value1' })
      const formatter2 = getCachedFormatter(form, { field1: 'value2' })
      
      // Same instance (cached)
      expect(formatter1).toBe(formatter2)
    })

    it('should return different instances for different form slugs', () => {
      const form1 = createMockForm('form-1')
      const form2 = createMockForm('form-2')
      
      const formatter1 = getCachedFormatter(form1, { field1: 'value' })
      const formatter2 = getCachedFormatter(form2, { field1: 'value' })
      
      expect(formatter1).not.toBe(formatter2)
    })

    it('should create new instance when form has no slug', () => {
      const formWithoutSlug = { properties: [] } as any
      
      const formatter1 = getCachedFormatter(formWithoutSlug, {})
      const formatter2 = getCachedFormatter(formWithoutSlug, {})
      
      // Different instances (not cached without slug)
      expect(formatter1).not.toBe(formatter2)
    })

    it('should update formData in cached formatter', () => {
      const form = createMockForm('test-form')
      
      getCachedFormatter(form, { field1: 'initial' })
      const formatter = getCachedFormatter(form, { field1: 'updated' })
      
      // The formatter should have the updated data
      const result = formatter.getFormattedData()
      expect(result.field1).toBe('updated')
    })
  })

  describe('clearFormatterCache', () => {
    it('should clear specific form from cache', () => {
      const form1 = createMockForm('form-1')
      const form2 = createMockForm('form-2')
      
      const formatter1a = getCachedFormatter(form1, { field1: 'v1' })
      const formatter2a = getCachedFormatter(form2, { field1: 'v2' })
      
      clearFormatterCache('form-1')
      
      const formatter1b = getCachedFormatter(form1, { field1: 'v1' })
      const formatter2b = getCachedFormatter(form2, { field1: 'v2' })
      
      // form-1 should have new instance
      expect(formatter1a).not.toBe(formatter1b)
      // form-2 should have same instance (still cached)
      expect(formatter2a).toBe(formatter2b)
    })

    it('should clear all forms from cache when no slug provided', () => {
      const form1 = createMockForm('form-1')
      const form2 = createMockForm('form-2')
      
      const formatter1a = getCachedFormatter(form1, { field1: 'v1' })
      const formatter2a = getCachedFormatter(form2, { field1: 'v2' })
      
      clearFormatterCache()
      
      const formatter1b = getCachedFormatter(form1, { field1: 'v1' })
      const formatter2b = getCachedFormatter(form2, { field1: 'v2' })
      
      // Both should have new instances
      expect(formatter1a).not.toBe(formatter1b)
      expect(formatter2a).not.toBe(formatter2b)
    })
  })

  describe('Cache with Configuration Reset', () => {
    it('should reset config flags when reusing cached formatter', () => {
      const form = {
        slug: 'test-form',
        properties: [{ id: 'url', name: 'URL', type: 'url' }]
      }
      
      // First call - set createLinks
      const formatter1 = getCachedFormatter(form, { url: 'https://a.com' }).setCreateLinks()
      expect(formatter1.getFormattedData().url).toContain('<a href')
      
      // Second call - should have reset flags
      const formatter2 = getCachedFormatter(form, { url: 'https://b.com' })
      // Don't set createLinks this time
      expect(formatter2.getFormattedData().url).toBe('https://b.com')
    })
  })
})

describe('Date Formatting', () => {
  const createDateForm = () => ({
    slug: 'date-form',
    properties: [
      { id: 'date', name: 'Date', type: 'date', date_format: 'dd/MM/yyyy' }
    ]
  })

  beforeEach(() => {
    clearFormatterCache()
  })

  it('should format single date', () => {
    const form = createDateForm()
    const formatter = new FormSubmissionFormatter(form, { date: '2024-01-15' })
    const result = formatter.getFormattedData()
    
    expect(result.date).toBe('15/01/2024')
  })

  it('should format date range', () => {
    const form = createDateForm()
    const formatter = new FormSubmissionFormatter(form, { date: ['2024-01-15', '2024-01-20'] })
    const result = formatter.getFormattedData()
    
    expect(result.date).toBe('15/01/2024 - 20/01/2024')
  })

  it('should return ISO format when useIsoFormat enabled', () => {
    const form = createDateForm()
    const formatter = new FormSubmissionFormatter(form, { date: '2024-01-15' })
      .setUseIsoFormatForDates()
    const result = formatter.getFormattedData()
    
    expect(result.date).toBe('2024-01-15')
  })

  it('should return ISO object for date range when useIsoFormat enabled', () => {
    const form = createDateForm()
    const formatter = new FormSubmissionFormatter(form, { date: ['2024-01-15', '2024-01-20'] })
      .setUseIsoFormatForDates()
    const result = formatter.getFormattedData()
    
    expect(result.date).toEqual({ start_date: '2024-01-15', end_date: '2024-01-20' })
  })
})

describe('People Field Formatting', () => {
  const createPeopleForm = () => ({
    slug: 'people-form',
    properties: [
      { id: 'person', name: 'Person', type: 'people' }
    ]
  })

  beforeEach(() => {
    clearFormatterCache()
  })

  it('should format single person', () => {
    const form = createPeopleForm()
    const formatter = new FormSubmissionFormatter(form, { person: { name: 'John Doe' } })
      .setOutputStringsOnly()
    const result = formatter.getFormattedData()
    
    expect(result.person).toBe('John Doe')
  })

  it('should format multiple people as comma-separated', () => {
    const form = createPeopleForm()
    const formatter = new FormSubmissionFormatter(form, { 
      person: [{ name: 'John' }, { name: 'Jane' }] 
    }).setOutputStringsOnly()
    const result = formatter.getFormattedData()
    
    expect(result.person).toBe('John, Jane')
  })

  it('should return array when not outputStringsOnly', () => {
    const form = createPeopleForm()
    const formatter = new FormSubmissionFormatter(form, { 
      person: [{ name: 'John' }, { name: 'Jane' }] 
    })
    const result = formatter.getFormattedData()
    
    expect(result.person).toEqual([{ name: 'John' }, { name: 'Jane' }])
  })

  it('should handle null people value', () => {
    const form = createPeopleForm()
    const formatter = new FormSubmissionFormatter(form, { person: null })
    const result = formatter.getFormattedData()
    
    // null is falsy, so field is skipped
    expect(result.person).toBeUndefined()
  })
})

describe('Relation Field Formatting', () => {
  const createRelationForm = () => ({
    slug: 'relation-form',
    properties: [
      { id: 'related', name: 'Related', type: 'relation' }
    ]
  })

  beforeEach(() => {
    clearFormatterCache()
  })

  it('should format single relation', () => {
    const form = createRelationForm()
    const formatter = new FormSubmissionFormatter(form, { 
      related: { title: 'Related Item' } 
    }).setOutputStringsOnly()
    const result = formatter.getFormattedData()
    
    expect(result.related).toBe('Related Item')
  })

  it('should format multiple relations as comma-separated', () => {
    const form = createRelationForm()
    const formatter = new FormSubmissionFormatter(form, { 
      related: [{ title: 'Item 1' }, { title: 'Item 2' }] 
    }).setOutputStringsOnly()
    const result = formatter.getFormattedData()
    
    expect(result.related).toBe('Item 1, Item 2')
  })

  it('should use "Untitled" for relations without title', () => {
    const form = createRelationForm()
    const formatter = new FormSubmissionFormatter(form, { 
      related: [{ id: '123' }] // No title
    }).setOutputStringsOnly()
    const result = formatter.getFormattedData()
    
    expect(result.related).toBe('Untitled')
  })
})

describe('Generated IDs', () => {
  it('should include field with generates_uuid when showGeneratedIds enabled', () => {
    const form = {
      slug: 'uuid-form',
      properties: [
        { id: 'uuid_field', name: 'UUID', type: 'text', generates_uuid: true }
      ]
    }
    
    const formatter = new FormSubmissionFormatter(form, {}).setShowGeneratedIds()
    const result = formatter.getFormattedData()
    
    // Field should be included even without a value
    expect('uuid_field' in result).toBe(true)
  })

  it('should include field with generates_auto_increment_id when showGeneratedIds enabled', () => {
    const form = {
      slug: 'auto-id-form',
      properties: [
        { id: 'auto_field', name: 'Auto ID', type: 'text', generates_auto_increment_id: true }
      ]
    }
    
    const formatter = new FormSubmissionFormatter(form, {}).setShowGeneratedIds()
    const result = formatter.getFormattedData()
    
    expect('auto_field' in result).toBe(true)
  })

  it('should not include generated ID fields when showGeneratedIds disabled', () => {
    const form = {
      slug: 'no-gen-form',
      properties: [
        { id: 'uuid_field', name: 'UUID', type: 'text', generates_uuid: true }
      ]
    }
    
    const formatter = new FormSubmissionFormatter(form, {})
    const result = formatter.getFormattedData()
    
    expect('uuid_field' in result).toBe(false)
  })
})
