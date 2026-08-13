import { describe, expect, it } from 'vitest'
import {
  ATTRIBUTION_MAX_VALUE_LENGTH,
  ATTRIBUTION_PARAMETERS,
  attributionColumnId,
  extractAttribution,
  mergeAttribution,
  sanitizeAttribution,
} from '../../lib/forms/submissionAttribution.js'

describe('submission attribution', () => {
  it('captures the complete supported parameter contract', () => {
    expect(ATTRIBUTION_PARAMETERS).toEqual([
      'utm_source', 'utm_medium', 'utm_campaign', 'utm_id', 'utm_term', 'utm_content',
      'utm_source_platform', 'utm_creative_format', 'utm_marketing_tactic',
      'gclid', 'gbraid', 'wbraid', 'dclid', 'fbclid', 'ttclid', 'msclkid',
    ])
  })

  it('extracts supported values and ignores arbitrary query parameters', () => {
    const attribution = extractAttribution('?email=secret@example.test&utm_source=google&gclid=click-id')

    expect(attribution).toEqual({ utm_source: 'google', gclid: 'click-id' })
  })

  it('uses the first non-empty occurrence', () => {
    expect(extractAttribution('?utm_campaign=&utm_campaign=first&utm_campaign=second'))
      .toEqual({ utm_campaign: 'first' })
  })

  it('drops empty, non-string, and oversized values', () => {
    expect(sanitizeAttribution({
      utm_source: ' ',
      utm_medium: ['cpc'],
      gclid: 'x'.repeat(ATTRIBUTION_MAX_VALUE_LENGTH + 1),
      fbclid: 'valid',
      secret: 'ignored',
    })).toEqual({ fbclid: 'valid' })
  })

  it('gives explicit iframe parameters precedence over parent parameters', () => {
    const iframeAttribution = { utm_source: 'partner', utm_medium: 'embed' }
    const parentAttribution = { utm_source: 'facebook', utm_campaign: 'summer' }

    expect(mergeAttribution(iframeAttribution, parentAttribution)).toEqual({
      utm_source: 'partner',
      utm_medium: 'embed',
      utm_campaign: 'summer',
    })
    expect(iframeAttribution).toEqual({ utm_source: 'partner', utm_medium: 'embed' })
    expect(parentAttribution).toEqual({ utm_source: 'facebook', utm_campaign: 'summer' })
  })

  it('uses namespaced table column identifiers', () => {
    expect(attributionColumnId('utm_source')).toBe('meta.attribution.utm_source')
  })
})
