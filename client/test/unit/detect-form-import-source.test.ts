import { describe, expect, it } from 'vitest'
import { detectFormImportSource, normalizeImportUrl } from '../../lib/forms/detect-form-import-source.js'

describe('normalizeImportUrl', () => {
  it('adds https when the scheme is omitted', () => {
    expect(normalizeImportUrl('tally.so/r/mBGjOq')).toBe('https://tally.so/r/mBGjOq')
  })

  it('keeps existing http schemes', () => {
    expect(normalizeImportUrl('https://example.typeform.com/to/abc123')).toBe('https://example.typeform.com/to/abc123')
  })
})

describe('detectFormImportSource', () => {
  it('detects Typeform URLs with a form id', () => {
    expect(detectFormImportSource('example.typeform.com/to/abc123')).toEqual({
      source: 'typeform',
      normalizedUrl: 'https://example.typeform.com/to/abc123',
      reason: null,
    })
  })

  it('flags Typeform URLs missing the form id path', () => {
    expect(detectFormImportSource('https://example.typeform.com/forms/abc123')).toEqual({
      source: 'typeform',
      normalizedUrl: 'https://example.typeform.com/forms/abc123',
      reason: 'typeform_form_id',
    })
  })

  it('detects Tally URLs', () => {
    expect(detectFormImportSource('https://tally.so/r/mBGjOq').source).toBe('tally')
  })

  it('keeps exact-domain providers aligned with backend validation', () => {
    expect(detectFormImportSource('https://www.tally.so/r/mBGjOq')).toEqual({
      source: null,
      normalizedUrl: 'https://www.tally.so/r/mBGjOq',
      reason: 'unsupported_provider',
    })
  })

  it('detects Fillout URLs and subdomains', () => {
    expect(detectFormImportSource('https://example.fillout.com/t/abc123').source).toBe('fillout')
    expect(detectFormImportSource('https://fillout.com/t/abc123').source).toBe('fillout')
  })

  it('detects Google Forms edit URLs', () => {
    expect(detectFormImportSource('https://docs.google.com/forms/d/1abc123/edit')).toEqual({
      source: 'google_forms',
      normalizedUrl: 'https://docs.google.com/forms/d/1abc123/edit',
      reason: null,
    })
  })

  it('flags published Google Forms URLs', () => {
    expect(detectFormImportSource('https://docs.google.com/forms/d/e/published123/viewform')).toEqual({
      source: 'google_forms',
      normalizedUrl: 'https://docs.google.com/forms/d/e/published123/viewform',
      reason: 'google_published_url',
    })
  })

  it('flags invalid URL text', () => {
    expect(detectFormImportSource('not a url')).toEqual({
      source: null,
      normalizedUrl: 'https://not a url',
      reason: 'invalid_url',
    })
  })

  it('returns unsupported provider for unknown hosts', () => {
    expect(detectFormImportSource('https://example.com/form')).toEqual({
      source: null,
      normalizedUrl: 'https://example.com/form',
      reason: 'unsupported_provider',
    })
  })
})
