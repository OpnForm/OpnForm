import { beforeEach, afterEach, describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref } from 'vue'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import FeatureBase from '~/components/vendor/FeatureBase.vue'

const state = vi.hoisted(() => ({ user: null }))
mockNuxtImport('useAuth', () => () => ({ user: () => ({ data: state.user }) }))

beforeEach(() => {
  useRuntimeConfig().public.featureBaseOrganization = 'local-test'
  state.user = ref({ id: 1, name: 'QA', email: 'qa@example.test' })
  vi.stubGlobal('useAuthStore', () => ({ isImpersonating: false }))
  vi.stubGlobal('useAppStore', () => ({ featureBaseButtonVisible: true }))
})
afterEach(() => { document.getElementById('featurebase-sdk')?.remove(); delete window.Featurebase })

describe('feedback SDK loading', () => {
  it('loads once on first intent and opens the feedback widget once it is ready', async () => {
    const sdk = vi.fn()
    window.Featurebase = sdk
    const wrapper = mount(FeatureBase, { attachTo: document.body, global: { stubs: { Icon: true } } })
    expect(document.getElementById('featurebase-sdk')).toBeNull()
    await wrapper.get('button').trigger('click')
    const script = document.getElementById('featurebase-sdk')
    await wrapper.get('button').trigger('click')
    expect(document.querySelectorAll('#featurebase-sdk')).toHaveLength(1)
    script.dispatchEvent(new Event('load'))
    const init = sdk.mock.calls.find(call => call[0] === 'initialize_feedback_widget')
    expect(init).toBeTruthy()
    const click = vi.fn()
    wrapper.element.addEventListener('click', click)
    init[2](null, { action: 'widgetReady' })
    expect(click).toHaveBeenCalledTimes(1)
    wrapper.unmount()
  })
  it('reuses the loaded SDK after remount while rebuilding the widgets', async () => {
    const sdk = vi.fn()
    window.Featurebase = sdk
    let wrapper = mount(FeatureBase, { global: { stubs: { Icon: true } } })
    await wrapper.get('button').trigger('click')
    const script = document.getElementById('featurebase-sdk')
    script.dispatchEvent(new Event('load'))
    wrapper.unmount()
    expect(sdk).toHaveBeenCalledWith('destroy_feedback_widget')
    sdk.mockClear()
    wrapper = mount(FeatureBase, { global: { stubs: { Icon: true } } })
    await wrapper.get('button').trigger('click')
    expect(document.getElementById('featurebase-sdk')).toBe(script)
    expect(sdk.mock.calls.some(call => call[0] === 'initialize_feedback_widget')).toBe(true)
    wrapper.unmount()
  })
  it('allows retrying a failed SDK download', async () => {
    const wrapper = mount(FeatureBase, { global: { stubs: { Icon: true } } })
    await wrapper.get('button').trigger('click')
    document.getElementById('featurebase-sdk').dispatchEvent(new Event('error'))
    expect(document.getElementById('featurebase-sdk')).toBeNull()
    await wrapper.get('button').trigger('click')
    expect(document.querySelectorAll('#featurebase-sdk')).toHaveLength(1)
    wrapper.unmount()
  })
})
