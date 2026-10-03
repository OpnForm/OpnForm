import { describe, expect, it, vi } from 'vitest'
import {
  CHUNK_ERROR_RECOVERY_TAG,
  createChunkErrorRecovery,
} from '../../lib/chunk-error-recovery.js'

const flushPromises = () => new Promise(resolve => setTimeout(resolve, 0))

describe('createChunkErrorRecovery', () => {
  it('reports the exact chunk error before reloading with persistent loop protection', async () => {
    const captureException = vi.fn()
    const flush = vi.fn(() => Promise.resolve(true))
    const reload = vi.fn()
    const recover = createChunkErrorRecovery({ captureException, flush, reload })
    const error = new Error('Failed to fetch dynamically imported module')

    expect(recover({ error })).toBe(true)
    await flushPromises()

    expect(captureException).toHaveBeenCalledWith(error, {
      tags: {
        handled_by: CHUNK_ERROR_RECOVERY_TAG,
      },
    })
    expect(flush).toHaveBeenCalledWith(500)
    expect(reload).toHaveBeenCalledWith({
      persistState: true,
      ttl: 60_000,
    })
  })

  it('deduplicates chunk errors while recovery is already in progress', async () => {
    let finishFlush
    const flush = vi.fn(() => new Promise(resolve => {
      finishFlush = resolve
    }))
    const captureException = vi.fn()
    const reload = vi.fn()
    const recover = createChunkErrorRecovery({ captureException, flush, reload })

    expect(recover({ error: new Error('first') })).toBe(true)
    expect(recover({ error: new Error('second') })).toBe(false)
    await Promise.resolve()
    finishFlush(true)
    await flushPromises()

    expect(captureException).toHaveBeenCalledTimes(1)
    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('still reloads when telemetry cannot be flushed', async () => {
    const reload = vi.fn()
    const recover = createChunkErrorRecovery({
      captureException: vi.fn(),
      flush: vi.fn(() => Promise.reject(new Error('Sentry unavailable'))),
      reload,
    })

    expect(recover({ error: new Error('chunk failure') })).toBe(true)
    await flushPromises()

    expect(reload).toHaveBeenCalledTimes(1)
  })

  it('still reloads when telemetry capture throws synchronously', async () => {
    const reload = vi.fn()
    const recover = createChunkErrorRecovery({
      captureException: vi.fn(() => {
        throw new Error('Sentry unavailable')
      }),
      flush: vi.fn(() => Promise.resolve(true)),
      reload,
    })

    expect(recover({ error: new Error('chunk failure') })).toBe(true)
    await flushPromises()

    expect(reload).toHaveBeenCalledTimes(1)
  })
})
