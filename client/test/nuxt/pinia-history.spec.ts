import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { createApp, nextTick } from 'vue'
import { createPinia, defineStore } from 'pinia'
import historyPlugin from '../../plugins/pinia-history.js'

let sequence = 0
const apps: ReturnType<typeof createApp>[] = []

function createStore() {
  const pinia = createPinia()
  const app = createApp({ render: () => null })
  app.use(pinia)
  apps.push(app)
  historyPlugin({ $pinia: pinia } as never)
  const useStore = defineStore(`history-${sequence++}`, {
    state: () => ({ title: 'Initial', cursor: 0 }),
    history: { debounceWait: 300, ignoreKeys: ['cursor'] },
  })
  return useStore(pinia)
}

beforeEach(() => vi.useFakeTimers())
afterEach(() => {
  apps.splice(0).forEach(app => app.unmount())
  vi.clearAllTimers()
  vi.useRealTimers()
})

async function settleHistory() {
  await nextTick()
  await vi.advanceTimersByTimeAsync(301)
  await nextTick()
}

describe('Pinia editor history with the installed debounce package', () => {
  it('coalesces a burst of edits into one undo step and restores it through redo', async () => {
    const store = createStore()
    store.title = 'First edit'
    await nextTick()
    store.title = 'Latest edit'
    await settleHistory()
    expect(store.canUndo).toBe(true)
    store.undo()
    await nextTick()
    expect(store.title).toBe('Initial')
    expect(store.canUndo).toBe(false)
    expect(store.canRedo).toBe(true)
    store.redo()
    await settleHistory()
    expect(store.title).toBe('Latest edit')
    expect(store.canUndo).toBe(true)
    expect(store.canRedo).toBe(false)
  })

  it('cancels pending history writes when undo is selected before the debounce fires', async () => {
    const store = createStore()
    store.title = 'Saved edit'
    await settleHistory()
    store.title = 'Pending edit'
    await nextTick()
    store.undo()
    await settleHistory()
    expect(store.title).toBe('Initial')
    expect(store.canUndo).toBe(false)
    store.redo()
    await settleHistory()
    expect(store.title).toBe('Saved edit')
  })

  it('leaves ignored editor state intact when restoring tracked fields', async () => {
    const store = createStore()
    store.cursor = 4
    await settleHistory()
    expect(store.canUndo).toBe(false)
    store.title = 'Edited'
    await settleHistory()
    store.cursor = 9
    await nextTick()
    store.undo()
    await settleHistory()
    expect(store.title).toBe('Initial')
    expect(store.cursor).toBe(9)
  })
})
