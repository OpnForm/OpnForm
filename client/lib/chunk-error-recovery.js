const chunkErrorsWithReload = new WeakSet()
const RELOAD_DEDUPLICATION_MS = 1_000

export function hasChunkReload (error) {
  const seen = new Set()
  while (error instanceof Error && !seen.has(error)) {
    if (chunkErrorsWithReload.has(error)) {
      return true
    }
    // Nuxt can wrap a route failure in an application error before reporting it.
    seen.add(error)
    error = error.cause
  }

  return false
}

export function createChunkErrorRecovery ({ reload, isReloadBlocked, reportBlockedCss }) {
  let deduplicateUntil = 0
  let blockedCssReported = false

  return ({ error }) => {
    const now = Date.now()
    if (now < deduplicateUntil) {
      if (error instanceof Error) {
        chunkErrorsWithReload.add(error)
      }
      return false
    }

    if (isReloadBlocked()) {
      // Nuxt prevents Vite from throwing these CSS errors, even when reload is blocked.
      if (!blockedCssReported && error?.message?.startsWith('Unable to preload CSS for ')) {
        blockedCssReported = true
        reportBlockedCss(error)
      }
      return false
    }

    reload({
      persistState: true,
      ttl: 60_000,
    })
    // A beforeunload prompt can cancel the reload and leave this document active.
    deduplicateUntil = now + RELOAD_DEDUPLICATION_MS
    blockedCssReported = false
    if (error instanceof Error) {
      chunkErrorsWithReload.add(error)
    }

    return true
  }
}
