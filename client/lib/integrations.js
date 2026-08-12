const INTEGRATION_COLORS = {
  blue: {
    iconBg: 'bg-blue-500',
    iconText: 'text-white',
    ring: 'ring-blue-200/80',
  },
  emerald: {
    iconBg: 'bg-emerald-600',
    iconText: 'text-white',
    ring: 'ring-emerald-200/80',
  },
  violet: {
    iconBg: 'bg-violet-600',
    iconText: 'text-white',
    ring: 'ring-violet-200/80',
  },
  amber: {
    iconBg: 'bg-amber-500',
    iconText: 'text-white',
    ring: 'ring-amber-200/80',
  },
  orange: {
    iconBg: 'bg-orange-500',
    iconText: 'text-white',
    ring: 'ring-orange-200/80',
  },
  rose: {
    iconBg: 'bg-rose-500',
    iconText: 'text-white',
    ring: 'ring-rose-200/80',
  },
  neutral: {
    iconBg: 'bg-neutral-950',
    iconText: 'text-white',
    ring: 'ring-neutral-200/80',
  },
}

export function getIntegrationColorClasses (color) {
  const key = String(color ?? 'blue').toLowerCase()

  return INTEGRATION_COLORS[key] ?? INTEGRATION_COLORS.blue
}

export function normalizeIntegration (integration) {
  if (!integration) return null

  const meta = integration.meta ?? {}
  const slug = integration.slug
    ?? meta.slug
    ?? integration.stem
    ?? integration.path?.split('/').filter(Boolean).pop()
    ?? null

  return {
    ...meta,
    ...integration,
    slug,
    summary: integration.summary ?? meta.summary ?? integration.description ?? '',
    icon: integration.icon ?? meta.icon ?? 'i-heroicons-squares-plus',
    color: integration.color ?? meta.color ?? 'blue',
    order: Number(integration.order ?? meta.order ?? 999),
    featured: integration.featured ?? meta.featured ?? false,
    published: integration.published ?? meta.published ?? true,
    highlights: integration.highlights ?? meta.highlights ?? [],
    seoTitle: integration.seoTitle ?? meta.seoTitle ?? integration.title ?? '',
    seoDescription: integration.seoDescription ?? meta.seoDescription ?? integration.summary ?? meta.summary ?? '',
  }
}

export function sortIntegrations (integrationA, integrationB) {
  if (integrationA.order !== integrationB.order) {
    return integrationA.order - integrationB.order
  }

  return integrationA.title.localeCompare(integrationB.title)
}

export function filterPublishedIntegrations (integrations) {
  return (integrations ?? [])
    .map(normalizeIntegration)
    .filter((integration) => integration && integration.published !== false)
}

export function getIntegrationLink (integration) {
  if (integration?.path) return integration.path
  if (integration?.slug) return `/integrations/${integration.slug}`
  return '/integrations'
}

export function getOtherIntegrations (currentIntegration, allIntegrations, limit = 3) {
  return (allIntegrations ?? [])
    .filter((integration) => integration.slug !== currentIntegration?.slug)
    .sort(sortIntegrations)
    .slice(0, limit)
}
