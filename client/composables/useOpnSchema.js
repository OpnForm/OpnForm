const DEFAULT_SITE_URL = "https://opnform.com"
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
