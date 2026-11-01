// @vitest-environment jsdom
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { useConfetti } from '~/composables/useConfetti'

const state = vi.hoisted(() => ({ moduleDefault: null as any, app: null as any }))
vi.mock('vue-confetti', () => ({ get default() { return state.moduleDefault } }))

const wrappers: ReturnType<typeof mount>[] = []
function fixture() {
  let confetti: ReturnType<typeof useConfetti>
  wrappers.push(mount({ setup() { confetti = useConfetti(); return () => null } }))
  return confetti!
}

beforeEach(() => {
  vi.useFakeTimers()
  vi.stubGlobal('useNuxtApp', () => ({ vueApp: state.app }))
  state.app = {
    config: { globalProperties: {} },
    use: vi.fn(plugin => plugin.install(state.app)),
  }
})
afterEach(() => {
  wrappers.splice(0).forEach(wrapper => wrapper.unmount())
  vi.useRealTimers()
  vi.unstubAllGlobals()
})

describe('lazy confetti loading', () => {
  for (const nested of [false, true]) {
    it(`plays and stops with a ${nested ? 'UMD wrapped' : 'direct'} plugin export`, async () => {
      const confetti = { start: vi.fn(), stop: vi.fn() }
      const plugin = { install: vi.fn(app => { app.config.globalProperties.$confetti = confetti }) }
      state.moduleDefault = nested ? { __esModule: true, default: plugin } : plugin
      const player = fixture()
      expect(state.app.use).not.toHaveBeenCalled()
      await player.play()
      expect(state.app.use).toHaveBeenCalledWith(plugin)
      expect(confetti.start).toHaveBeenCalledWith({ defaultSize: 6 })
      vi.advanceTimersByTime(2999)
      expect(confetti.stop).not.toHaveBeenCalled()
      vi.advanceTimersByTime(1)
      expect(confetti.stop).toHaveBeenCalledOnce()
      await player.play(500)
      expect(state.app.use).toHaveBeenCalledOnce()
      vi.advanceTimersByTime(500)
      expect(confetti.stop).toHaveBeenCalledTimes(2)
    })
  }

  it('reuses an already installed confetti instance', async () => {
    const confetti = { start: vi.fn(), stop: vi.fn() }
    state.app.config.globalProperties.$confetti = confetti
    await fixture().play()
    expect(state.app.use).not.toHaveBeenCalled()
    expect(confetti.start).toHaveBeenCalledOnce()
  })
})
