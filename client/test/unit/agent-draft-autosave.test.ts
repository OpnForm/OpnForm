import { afterEach, describe, expect, it, vi } from 'vitest'
import { createAgentDraftAutosave } from '../../lib/forms/agent-draft-autosave.js'

describe('agent draft autosave', () => {
  afterEach(() => {
    vi.useRealTimers()
  })

  it('runs a scheduled save after the debounce', async () => {
    vi.useFakeTimers()
    const sync = vi.fn().mockResolvedValue(undefined)
    const autosave = createAgentDraftAutosave(sync, 900)

    autosave.schedule()
    await vi.advanceTimersByTimeAsync(900)

    expect(sync).toHaveBeenCalledOnce()
    expect(sync).toHaveBeenCalledWith({ keepalive: false })
  })

  it('flushes a pending save with keepalive and cancels the debounce', async () => {
    vi.useFakeTimers()
    const sync = vi.fn().mockResolvedValue(undefined)
    const autosave = createAgentDraftAutosave(sync, 900)

    autosave.schedule()
    await autosave.flush()
    await vi.advanceTimersByTimeAsync(900)

    expect(sync).toHaveBeenCalledOnce()
    expect(sync).toHaveBeenCalledWith({ keepalive: true })
  })
})
