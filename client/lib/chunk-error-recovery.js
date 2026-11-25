const chunkErrorsWithReload = new WeakSet()

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
  let recoveryStarted = false
  let blockedCssReported = false

  return ({ error }) => {
    if (recoveryStarted) {
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
    recoveryStarted = true
    if (error instanceof Error) {
      chunkErrorsWithReload.add(error)
    }

    return true
  }
}
