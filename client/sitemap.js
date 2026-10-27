import { existsSync, readdirSync, readFileSync } from 'node:fs'
import { join } from 'node:path'
import templateIndustries from './data/forms/templates/industries.json'
import templateTypes from './data/forms/templates/types.json'
import { selfHostedMarketingPaths } from './lib/self-hosted-public.js'

const isSelfHostedBuild = process.env.SELF_HOSTED === 'true'
  || process.env.NUXT_PUBLIC_SELF_HOSTED === 'true'

export default {
  exclude: [
    '/subscriptions/**',
    '/templates/my-templates',
    '/setup',
    '/self-hosted/checkout/**',
    ...(isSelfHostedBuild ? ['/', ...selfHostedMarketingPaths, '/integrations/**', /^\/opnform-vs-/, '/privacy-policy', '/terms-conditions', '/report-abuse'] : []),
  ],
  sources: [`${(process.env.NUXT_PUBLIC_API_BASE || '').replace(/\/+$/, '')}/sitemap-urls`],
  cacheMaxAgeSeconds: 60 * 60 * 2, // 2 hours
  xslColumns: [
    { label: 'URL', width: '50%' },
    { label: 'Last Modified', select: 'sitemap:lastmod', width: '25%' },
    { label: 'Priority', select: 'sitemap:priority', width: '12.5%' },
    { label: 'Change Frequency', select: 'sitemap:changefreq', width: '12.5%' }
  ],
  urls: async () => {
    return [
      ...getTemplateIndustriesUrls(),
      ...getTemplateTypesUrls(),
      ...getCloudMarketingUrls(),
      ...getIntegrationsPages(),
    ]
  }
}

function getCloudMarketingUrls () {
  if (isSelfHostedBuild) return []

  return [
    {
      url: '/mcp',
      changefreq: 'monthly',
      priority: 0.9
    },
    {
      url: '/self-hosted/license',
      changefreq: 'monthly',
      priority: 0.7
    }
  ]
}

function getMarkdownFrontmatter (filePath) {
  const content = readFileSync(filePath, 'utf8')
  const match = content.match(/^---\n([\s\S]*?)\n---/)
  if (!match) return {}

  return match[1].split('\n').reduce((frontmatter, line) => {
    const separatorIndex = line.indexOf(':')
    if (separatorIndex === -1) return frontmatter

    const key = line.slice(0, separatorIndex).trim()
    const rawValue = line.slice(separatorIndex + 1).trim()

    if (!key) return frontmatter

    frontmatter[key] = parseFrontmatterValue(rawValue)
    return frontmatter
  }, {})
}

function parseFrontmatterValue (value) {
  if (value === 'true') return true
  if (value === 'false') return false

  return value.replace(/^['"]|['"]$/g, '')
}

function getTemplateTypesUrls () {
  return Object.values(templateTypes).map((feature) => {
    return {
      url: `/templates/types/${feature.slug}`,
      changefreq: 'monthly',
      priority: 0.8
    }
  })
}

function getTemplateIndustriesUrls () {
  return Object.values(templateIndustries).map((feature) => {
    return {
      url: `/templates/industries/${feature.slug}`,
      changefreq: 'monthly',
      priority: 0.8
    }
  })
}

function getIntegrationsPages () {
  if (isSelfHostedBuild) return []
  const integrationsDir = join(process.cwd(), 'content/integrations')

  if (!existsSync(integrationsDir)) return []

  return readdirSync(integrationsDir)
    .filter((fileName) => fileName.endsWith('.md'))
    .map((fileName) => {
      const frontmatter = getMarkdownFrontmatter(join(integrationsDir, fileName))
      if (frontmatter.published === false) return null

      const fallbackSlug = fileName.replace(/\.md$/, '')
      const slug = frontmatter.slug || fallbackSlug

      return {
        url: `/integrations/${slug}`,
        changefreq: 'monthly',
        priority: 0.9
      }
    })
    .filter((page) => page)
}
