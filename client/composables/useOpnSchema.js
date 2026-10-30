const DEFAULT_SITE_URL = "https://opnform.com"
const ORGANIZATION_ID = "/#organization"
const WEBSITE_ID = "/#website"
const SOFTWARE_ID = "/#software"

function getBaseUrl() {
  return DEFAULT_SITE_URL
}

export function resolveSchemaUrl(path = "/") {
  if (!path) return `${getBaseUrl()}/`
  if (path.startsWith("http")) return path

  const normalizedPath = `/${path.replace(/^\/+/, "")}`
  return normalizedPath === "/" ? `${getBaseUrl()}/` : `${getBaseUrl()}${normalizedPath}`
}

function schemaId(path) {
  if (path.startsWith("http")) return path
  return resolveSchemaUrl(path)
}

export function stripSchemaText(value) {
  if (value === null || value === undefined) return ""

  return String(value)
    .replace(/<[^>]*>/g, " ")
    .replace(/&nbsp;/g, " ")
    .replace(/&amp;/g, "&")
    .replace(/&quot;/g, '"')
    .replace(/&#39;|&#x27;/g, "'")
    .replace(/\s+/g, " ")
    .trim()
}

export function buildSchemaGraph(nodes) {
  const graph = nodes.filter(Boolean)

  if (graph.length === 0) return null

  return {
    "@context": "https://schema.org",
    "@graph": graph,
  }
}

export function buildOrganizationSchema() {
  return {
    "@type": "Organization",
    "@id": schemaId(ORGANIZATION_ID),
    name: "OpnForm",
    url: resolveSchemaUrl("/"),
    logo: resolveSchemaUrl("/img/logo.svg"),
    sameAs: [
      "https://github.com/OpnForm/OpnForm",
      "https://twitter.com/OpnForm",
    ],
  }
}

export function buildWebsiteSchema() {
  return {
    "@type": "WebSite",
    "@id": schemaId(WEBSITE_ID),
    name: "OpnForm",
    url: resolveSchemaUrl("/"),
    publisher: {
      "@id": schemaId(ORGANIZATION_ID),
    },
  }
}

export function buildSoftwareApplicationSchema(overrides = {}) {
  return {
    "@type": "SoftwareApplication",
    "@id": schemaId(SOFTWARE_ID),
    name: "OpnForm",
    url: resolveSchemaUrl("/"),
    description:
      "OpnForm is an open-source form builder for creating online forms, collecting unlimited submissions, and automating data collection workflows.",
    applicationCategory: "BusinessApplication",
    operatingSystem: "Web",
    provider: {
      "@id": schemaId(ORGANIZATION_ID),
    },
    offers: {
      "@type": "Offer",
      name: "Free plan",
      price: "0",
      priceCurrency: "USD",
      availability: "https://schema.org/InStock",
      url: resolveSchemaUrl("/pricing"),
    },
    ...overrides,
  }
}

export function buildWebPageSchema({ name, description, path = "/", url } = {}) {
  const pageUrl = url || resolveSchemaUrl(path)

  return {
    "@type": "WebPage",
    "@id": `${pageUrl}#webpage`,
    url: pageUrl,
    name: stripSchemaText(name),
    description: stripSchemaText(description),
    isPartOf: {
      "@id": schemaId(WEBSITE_ID),
    },
    about: {
      "@id": schemaId(SOFTWARE_ID),
    },
  }
}

export function buildCollectionPageSchema({ name, description, path = "/", url } = {}) {
  const pageUrl = url || resolveSchemaUrl(path)

  return {
    "@type": "CollectionPage",
    "@id": `${pageUrl}#webpage`,
    url: pageUrl,
    name: stripSchemaText(name),
    description: stripSchemaText(description),
    isPartOf: {
      "@id": schemaId(WEBSITE_ID),
    },
    about: {
      "@id": schemaId(SOFTWARE_ID),
    },
  }
}

export function buildCreativeWorkSchema({ name, description, path = "/", url, image } = {}) {
  const pageUrl = url || resolveSchemaUrl(path)
  const schema = {
    "@type": "CreativeWork",
    "@id": `${pageUrl}#creative-work`,
    url: pageUrl,
    name: stripSchemaText(name),
    description: stripSchemaText(description),
    publisher: {
      "@id": schemaId(ORGANIZATION_ID),
    },
  }

  if (image) {
    schema.image = resolveSchemaUrl(image)
  }

  return schema
}

export function buildFaqSchema(faqs = []) {
  const mainEntity = faqs
    .map((faq) => ({
      "@type": "Question",
      name: stripSchemaText(faq.question),
      acceptedAnswer: {
        "@type": "Answer",
        text: stripSchemaText(faq.answer),
      },
    }))
    .filter((faq) => faq.name && faq.acceptedAnswer.text)

  if (mainEntity.length === 0) return null

  return {
    "@type": "FAQPage",
    mainEntity,
  }
}

export function buildBreadcrumbSchema(items = []) {
  const itemListElement = items
    .map((item, index) => {
      const name = stripSchemaText(item.name || item.label)
      const itemUrl = item.url || item.path

      if (!name || !itemUrl) return null

      return {
        "@type": "ListItem",
        position: index + 1,
        name,
        item: resolveSchemaUrl(itemUrl),
      }
    })
    .filter(Boolean)

  if (itemListElement.length === 0) return null

  return {
    "@type": "BreadcrumbList",
    itemListElement,
  }
}

export function buildItemListSchema(items = [], { path = "/", name = "Items" } = {}) {
  const itemListElement = items
    .map((item, index) => {
      const itemName = stripSchemaText(item.name || item.title)
      const itemUrl = item.url || item.path

      if (!itemName || !itemUrl) return null

      return {
        "@type": "ListItem",
        position: index + 1,
        name: itemName,
        url: resolveSchemaUrl(itemUrl),
      }
    })
    .filter(Boolean)

  if (itemListElement.length === 0) return null

  return {
    "@type": "ItemList",
    "@id": `${resolveSchemaUrl(path)}#item-list`,
    name,
    itemListElement,
  }
}

export function buildHowToSchema({ name, description, steps = [], path = "/", url } = {}) {
  const cleanedSteps = steps.map(stripSchemaText).filter(Boolean)

  if (cleanedSteps.length < 2) return null

  return {
    "@type": "HowTo",
    "@id": `${url || resolveSchemaUrl(path)}#how-to`,
    name: stripSchemaText(name),
    description: stripSchemaText(description),
    step: cleanedSteps.map((step, index) => ({
      "@type": "HowToStep",
      position: index + 1,
      text: step,
    })),
  }
}

export function buildOfferCatalogSchema(offers = []) {
  const itemListElement = offers
    .map((offer) => {
      if (!offer.name || offer.price === null || offer.price === undefined) return null

      return {
        "@type": "Offer",
        name: offer.name,
        price: String(offer.price),
        priceCurrency: offer.priceCurrency || "USD",
        url: resolveSchemaUrl(offer.url || "/pricing"),
      }
    })
    .filter(Boolean)

  if (itemListElement.length === 0) return null

  return {
    "@type": "OfferCatalog",
    name: "OpnForm plans",
    itemListElement,
  }
}
