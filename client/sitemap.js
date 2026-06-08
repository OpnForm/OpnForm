import templateIndustries from './data/forms/templates/industries.json'
import templateTypes from './data/forms/templates/types.json'
import opnformConfig from './opnform.config.js'

const apiBaseUrl = process.env.NUXT_PUBLIC_API_BASE || process.env.NUXT_PRIVATE_API_BASE || ''

export default {
  exclude: [
    '/admin',
    '/forms/create',
    '/forms/create/guest',
    '/home',
    '/login',
    '/password/**',
    '/redirect/**',
    '/register',
    '/self-hosted/checkout/**',
    '/setup',
    '/subscriptions/**',
    '/templates/my-templates',
  ],
  sources: apiBaseUrl ? [joinUrl(apiBaseUrl, 'sitemap-urls')] : [],
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
      ...(await getIntegrationsPages().catch(() => [])),
    ]
  }
}

function joinUrl (baseUrl, path) {
  return `${baseUrl.replace(/\/+$/, '')}/${path.replace(/^\/+/, '')}`
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

async function getIntegrationsPages () {
  try {
    const databaseId = '1eda631bec208005bd8ed9988b380263'
    const apiUrl = opnformConfig.notion.worker
    if (!apiUrl) return []
    
    const response = await fetch(`${apiUrl}/table/${databaseId}`, {
      timeout: 5000,
      headers: { 'Cache-Control': 'no-cache' }
    })
    
    if (!response.ok) return []
    
    const pages = await response.json()
    return pages.map((page) => {
      const slug = page.Slug ?? page.slug ?? null
      const published = page.Published ?? page.published ?? false
      if (!slug || !published) return null
      return {
        url: `/integrations/${slug}`,
        changefreq: 'monthly',
        priority: 0.9
      }
    }).filter((page) => page)
  } catch (error) {
    console.warn('Error fetching integrations pages for sitemap:', error.message)
    return []
  }
}
