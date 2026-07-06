import { describe, expect, it } from 'vitest'
import { categoryToSlug, filterPublishedFeatures, getFeatureColorClasses, getFeatureLink, getRelatedFeatures, groupFeaturesByCategory, normalizeFeature, sortFeatures } from '../../lib/features.js'

describe('features helpers', () => {
  it('sorts features by order then title', () => {
    const features = [
      { title: 'Beta', order: 20 },
      { title: 'Alpha', order: 10 },
      { title: 'Gamma', order: 20 },
    ]

    expect(features.sort(sortFeatures).map((feature) => feature.title)).toEqual([
      'Alpha',
      'Beta',
      'Gamma',
    ])
  })

  it('filters out unpublished features', () => {
    const features = [
      { title: 'Published', published: true },
      { title: 'Hidden', published: false },
      { title: 'Default', published: undefined },
    ]

    expect(filterPublishedFeatures(features).map((feature) => feature.title)).toEqual([
      'Published',
      'Default',
    ])
  })

  it('normalizes custom frontmatter from meta', () => {
    const feature = normalizeFeature({
      title: 'Custom Domains',
      path: '/features/custom-domains',
      meta: {
        slug: 'custom-domains',
        summary: 'Branded domains',
        category: 'Branding',
        order: 10,
      },
    })

    expect(feature.slug).toBe('custom-domains')
    expect(feature.summary).toBe('Branded domains')
    expect(feature.category).toBe('Branding')
  })

  it('normalizes feature color and returns color classes', () => {
    const feature = normalizeFeature({
      title: 'Custom Domains',
      meta: {
        color: 'emerald',
      },
    })

    expect(feature.color).toBe('emerald')
    expect(getFeatureColorClasses(feature.color).iconBg).toBe('bg-emerald-600')
    expect(getFeatureColorClasses('unknown').iconBg).toBe('bg-blue-500')
  })

  it('normalizes plan from frontmatter', () => {
    const feature = normalizeFeature({
      title: 'Custom SMTP',
      meta: {
        plan: 'Pro',
      },
    })

    expect(feature.plan).toBe('Pro')
  })

  it('normalizes hero image from frontmatter', () => {
    const feature = normalizeFeature({
      title: 'Custom Domains',
      meta: {
        heroImage: '/img/pages/welcome/share-1.png',
      },
    })

    expect(feature.heroImage).toBe('/img/pages/welcome/share-1.png')
  })

  it('builds feature links from path or slug', () => {
    expect(getFeatureLink({ path: '/features/custom-domains' })).toBe('/features/custom-domains')
    expect(getFeatureLink({ slug: 'custom-smtp' })).toBe('/features/custom-smtp')
    expect(getFeatureLink({})).toBe('/features')
  })

  it('groups features by category for section navigation', () => {
    const sections = groupFeaturesByCategory([
      { title: 'Custom Domains', category: 'Branding & Control', order: 1, slug: 'custom-domains' },
      { title: 'Branding Removal', category: 'Branding & Control', order: 0, slug: 'branding-removal' },
      { title: 'Custom SMTP', category: 'Notifications & Delivery', order: 2, slug: 'custom-smtp' },
    ])

    expect(sections.map((section) => section.category)).toEqual([
      'Branding & Control',
      'Notifications & Delivery',
    ])
    expect(sections[0].features.map((feature) => feature.slug)).toEqual([
      'branding-removal',
      'custom-domains',
    ])
    expect(categoryToSlug('Branding & Control')).toBe('branding-control')
  })
})
