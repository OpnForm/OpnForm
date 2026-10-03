export const CHUNK_ERROR_RECOVERY_TAG = 'chunk-error-recovery'

export function createChunkErrorRecovery ({ captureException, flush, reload }) {
  let recoveryStarted = false

  return ({ error }) => {
    if (recoveryStarted) {
      return false
    }

    recoveryStarted = true

    try {
      captureException(error, {
        tags: {
          handled_by: CHUNK_ERROR_RECOVERY_TAG,
        },
      })
    } catch {
      // Recovery must not depend on telemetry being available.
    }

    Promise.resolve()
      .then(() => flush(500))
      .catch(() => {})
      .finally(() => {
        reload({
          persistState: true,
          ttl: 60_000,
        })
      })

    return true
  }
}
