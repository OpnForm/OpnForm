const FEATURE_COLORS = {
  blue: {
    iconBg: 'bg-blue-500',
    iconText: 'text-white',
    glow: 'bg-blue-400/25',
    ring: 'ring-blue-200/80',
    accent: 'text-blue-600',
    softBg: 'bg-blue-50',
  },
  emerald: {
    iconBg: 'bg-emerald-600',
    iconText: 'text-white',
    glow: 'bg-emerald-400/25',
    ring: 'ring-emerald-200/80',
    accent: 'text-emerald-600',
    softBg: 'bg-emerald-50',
  },
  violet: {
    iconBg: 'bg-violet-600',
    iconText: 'text-white',
    glow: 'bg-violet-400/25',
    ring: 'ring-violet-200/80',
    accent: 'text-violet-600',
    softBg: 'bg-violet-50',
  },
  amber: {
    iconBg: 'bg-amber-500',
    iconText: 'text-white',
    glow: 'bg-amber-400/25',
    ring: 'ring-amber-200/80',
    accent: 'text-amber-600',
    softBg: 'bg-amber-50',
  },
  orange: {
    iconBg: 'bg-orange-500',
    iconText: 'text-white',
    glow: 'bg-orange-400/25',
    ring: 'ring-orange-200/80',
    accent: 'text-orange-600',
    softBg: 'bg-orange-50',
  },
  purple: {
    iconBg: 'bg-purple-600',
    iconText: 'text-white',
    glow: 'bg-purple-400/25',
    ring: 'ring-purple-200/80',
    accent: 'text-purple-600',
    softBg: 'bg-purple-50',
  },
  rose: {
    iconBg: 'bg-rose-500',
    iconText: 'text-white',
    glow: 'bg-rose-400/25',
    ring: 'ring-rose-200/80',
    accent: 'text-rose-600',
    softBg: 'bg-rose-50',
  },
  neutral: {
    iconBg: 'bg-neutral-950',
    iconText: 'text-white',
    glow: 'bg-neutral-400/20',
    ring: 'ring-neutral-200/80',
    accent: 'text-neutral-600',
    softBg: 'bg-neutral-50',
  },
}

export function getFeatureColorClasses (color) {
  const key = String(color ?? 'blue').toLowerCase()

  return FEATURE_COLORS[key] ?? FEATURE_COLORS.blue
}

export function normalizeFeature (feature) {
  if (!feature) return null

  const meta = feature.meta ?? {}

  const slug = feature.slug
    ?? meta.slug
    ?? feature.stem
    ?? feature.path?.split('/').filter(Boolean).pop()
    ?? null

  return {
    ...meta,
    ...feature,
    slug,
    summary: feature.summary ?? meta.summary ?? feature.description ?? '',
    category: feature.category ?? meta.category ?? 'Features',
    icon: feature.icon ?? meta.icon ?? 'i-heroicons-sparkles',
    color: feature.color ?? meta.color ?? 'blue',
    order: Number(feature.order ?? meta.order ?? 999),
    featured: feature.featured ?? meta.featured ?? false,
    published: feature.published ?? meta.published ?? true,
    seoTitle: feature.seoTitle ?? meta.seoTitle ?? feature.title ?? '',
    seoDescription: feature.seoDescription ?? meta.seoDescription ?? feature.summary ?? meta.summary ?? '',
    heroImage: feature.heroImage ?? meta.heroImage ?? null,
    plan: feature.plan ?? meta.plan ?? null,
  }
}

export function sortFeatures (featureA, featureB) {
  if (featureA.order !== featureB.order) {
    return featureA.order - featureB.order
  }

  return featureA.title.localeCompare(featureB.title)
}

export function filterPublishedFeatures (features) {
  return (features ?? [])
    .map(normalizeFeature)
    .filter((feature) => feature && feature.published !== false)
}

export function getFeatureLink (feature) {
  if (feature?.path) return feature.path
  if (feature?.slug) return `/features/${feature.slug}`
  return '/features'
}

export function getRelatedFeatures (currentFeature, allFeatures) {
  if (!currentFeature?.category) return []

  return (allFeatures ?? [])
    .filter((item) => item.slug !== currentFeature.slug && item.category === currentFeature.category)
    .sort(sortFeatures)
}

export const FEATURE_CATEGORY_DESCRIPTIONS = {
  'Forms & Submissions': 'Collect responses, track progress, and manage submission workflows.',
  'Branding & Control': 'Customize branding, domains, and the respondent experience.',
  'Notifications & Delivery': 'Control how forms notify your team and users.',
}

export function categoryToSlug (category) {
  return String(category ?? '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')
}

export function getCategoryDescription (category) {
  return FEATURE_CATEGORY_DESCRIPTIONS[category]
    ?? `Explore OpnForm capabilities in ${category}.`
}

export function groupFeaturesByCategory (features) {
  const grouped = (features ?? []).reduce((groups, feature) => {
    const category = feature.category ?? 'Features'

    if (!groups[category]) {
      groups[category] = []
    }

    groups[category].push(feature)
    return groups
  }, {})

  return Object.entries(grouped)
    .map(([category, categoryFeatures]) => ({
      category,
      slug: categoryToSlug(category),
      description: getCategoryDescription(category),
      features: [...categoryFeatures].sort(sortFeatures),
      order: Math.min(...categoryFeatures.map((feature) => feature.order ?? 999)),
    }))
    .sort((groupA, groupB) => {
      if (groupA.order !== groupB.order) {
        return groupA.order - groupB.order
      }

      return groupA.category.localeCompare(groupB.category)
    })
}
