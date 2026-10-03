import { describe, expect, it } from 'vitest'
import {
  ATTRIBUTION_MAX_VALUE_LENGTH,
  ATTRIBUTION_PARAMETER_LABELS,
  ATTRIBUTION_PARAMETERS,
  attributionColumnAccessor,
  attributionColumnId,
  attributionParameterFromColumnId,
  detectedAttributionParameters,
  extractAttribution,
  isColumnVisibilityTransition,
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

  it('reads namespaced attribution columns from flat table rows', () => {
    const row = { 'meta.attribution.utm_source': 'newsletter' }

    expect(attributionColumnAccessor('utm_source')(row)).toBe('newsletter')
  })

  it('identifies attribution columns and provides readable labels', () => {
    expect(attributionParameterFromColumnId('meta.attribution.utm_campaign')).toBe('utm_campaign')
    expect(attributionParameterFromColumnId('utm_campaign')).toBeNull()
    expect(attributionParameterFromColumnId('default_email')).toBeNull()
    expect(ATTRIBUTION_PARAMETER_LABELS.utm_campaign).toBe('Campaign')
  })

  it('detects only parameters containing submission values', () => {
    expect(detectedAttributionParameters([
      {
        'meta.attribution.utm_source': 'newsletter',
        'meta.attribution.utm_medium': '',
      },
      {
        'meta.attribution.gclid': 'google-click',
      },
    ])).toEqual(['utm_source', 'gclid'])
  })

  it('changes visibility only when dragging between visible and hidden sections', () => {
    expect(isColumnVisibilityTransition('hidden', 'visible')).toBe(true)
    expect(isColumnVisibilityTransition('visible', 'hidden')).toBe(true)
    expect(isColumnVisibilityTransition('hidden', 'hidden')).toBe(false)
    expect(isColumnVisibilityTransition(undefined, 'hidden')).toBe(false)
  })
})
