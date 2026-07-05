export function normalizePublicPageAssetUrl (assetUrl, pageUrl) {
  if (!assetUrl || !pageUrl) return assetUrl

  try {
    const page = new URL(pageUrl)
    const asset = new URL(assetUrl, page)

    if (page.protocol === 'https:' && asset.protocol === 'http:' && asset.host === page.host) {
      asset.protocol = 'https:'
      return asset.toString()
    }
  } catch {
    return assetUrl
  }

  return assetUrl
}
