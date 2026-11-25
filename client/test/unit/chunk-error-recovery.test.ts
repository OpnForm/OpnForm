import { describe, expect, it, vi } from 'vitest'
import { createChunkErrorRecovery, hasChunkReload } from '../../lib/chunk-error-recovery.js'

describe('createChunkErrorRecovery', () => {
  it('reloads immediately with persistent loop protection', () => {
    const reload = vi.fn()
    const recover = createChunkErrorRecovery({ reload, isReloadBlocked: () => false })

    const error = new TypeError('Importing a module script failed.')
    expect(recover({ error })).toBe(true)
    expect(hasChunkReload(error)).toBe(true)
    expect(hasChunkReload(new Error(error.message, { cause: error }))).toBe(true)
    expect(hasChunkReload(new Error(error.message))).toBe(false)
    expect(reload).toHaveBeenCalledWith({
      persistState: true,
      ttl: 60_000,
    })
  })

  it('deduplicates chunk errors while recovery is already in progress', () => {
    const reload = vi.fn()
    const recover = createChunkErrorRecovery({ reload, isReloadBlocked: () => false })

    expect(recover({ error: new Error('Importing a module script failed.') })).toBe(true)
    const secondError = new Error('Second failure')
    expect(recover({ error: secondError })).toBe(false)
    expect(hasChunkReload(secondError)).toBe(true)
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('reports CSS preload failures once when reload loop protection blocks recovery', () => {
    const reload = vi.fn()
    const reportBlockedCss = vi.fn()
    const recover = createChunkErrorRecovery({ reload, isReloadBlocked: () => true, reportBlockedCss })
    const error = new Error('Unable to preload CSS for /_nuxt/example.css')

    expect(recover({ error })).toBe(false)
    expect(hasChunkReload(error)).toBe(false)
    expect(recover({ error })).toBe(false)
    expect(reportBlockedCss).toHaveBeenCalledExactlyOnceWith(error)
    expect(reload).not.toHaveBeenCalled()
  })
})
