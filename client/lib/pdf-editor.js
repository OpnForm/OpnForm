export function resolvePdfDownloadUrl(endpoint, baseUrl, origin) {
  const normalizedBaseUrl = (baseUrl || '').replace(/\/+$/, '')
  return new URL(normalizedBaseUrl + endpoint, origin).toString()
}

export function getPdfRenderPages(pageList, currentPage, isNewPage, zoomOnlyVisible = false) {
  const physicalPages = pageList.filter((pageNum) => !isNewPage(pageNum))
  if (!zoomOnlyVisible) {
    return { physicalPages, targetPages: physicalPages }
  }

  const current = Number(currentPage)
  const priorityPages = [current - 1, current, current + 1]
  const targetPages = priorityPages.filter((pageNum) => physicalPages.includes(pageNum))

  return { physicalPages, targetPages }
}
