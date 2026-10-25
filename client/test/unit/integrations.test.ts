import { describe, expect, it } from 'vitest'
import { filterPublishedIntegrations, getIntegrationColorClasses, getIntegrationLink, getOtherIntegrations, normalizeIntegration, sortIntegrations } from '../../lib/integrations.js'

describe('integrations helpers', () => {
  it('normalizes integration frontmatter from meta', () => {
    const integration = normalizeIntegration({
      title: 'Slack Notification',
      path: '/integrations/slack',
      meta: {
        slug: 'slack',
        summary: 'Receive Slack alerts',
        order: 10,
        highlights: ['Real-time Slack alerts'],
      },
    })

    expect(integration.slug).toBe('slack')
    expect(integration.summary).toBe('Receive Slack alerts')
    expect(integration.highlights).toEqual(['Real-time Slack alerts'])
  })

  it('sorts integrations by order then title', () => {
    const integrations = [
      { title: 'Zapier', order: 20 },
      { title: 'Email', order: 10 },
      { title: 'Slack', order: 20 },
    ]

    expect(integrations.sort(sortIntegrations).map((integration) => integration.title)).toEqual([
      'Email',
      'Slack',
      'Zapier',
    ])
  })

  it('filters out unpublished integrations', () => {
    const integrations = [
      { title: 'Published', published: true },
      { title: 'Hidden', published: false },
      { title: 'Default', published: undefined },
    ]

    expect(filterPublishedIntegrations(integrations).map((integration) => integration.title)).toEqual([
      'Published',
      'Default',
    ])
  })

  it('builds integration links from path or slug', () => {
    expect(getIntegrationLink({ path: '/integrations/slack' })).toBe('/integrations/slack')
    expect(getIntegrationLink({ slug: 'zapier' })).toBe('/integrations/zapier')
    expect(getIntegrationLink({})).toBe('/integrations')
  })

  it('returns fallback color classes for unknown colors', () => {
    expect(getIntegrationColorClasses('unknown').iconBg).toBe('bg-blue-500')
    expect(getIntegrationColorClasses('emerald').iconBg).toBe('bg-emerald-600')
  })

  it('returns other integrations excluding the current item', () => {
    const integrations = [
      { title: 'Email', slug: 'email', order: 0 },
      { title: 'Slack', slug: 'slack', order: 1 },
      { title: 'Zapier', slug: 'zapier', order: 2 },
    ]

    expect(getOtherIntegrations({ slug: 'email' }, integrations, 2).map((integration) => integration.slug)).toEqual([
      'slack',
      'zapier',
    ])
  })
})
