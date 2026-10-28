import { beforeEach, afterEach, describe, it, expect, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { ref, nextTick } from 'vue'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import FeatureBase from '~/components/vendor/FeatureBase.vue'
import { useSharedNavigation } from '~/composables/components/useSharedNavigation.js'

const state = vi.hoisted(() => ({ user: null }))
mockNuxtImport('useAuth', () => () => ({ user: () => ({ data: state.user }) }))
mockNuxtImport('useCrisp', () => () => null)
mockNuxtImport('useFeatureFlag', () => () => false)

beforeEach(() => {
  useRuntimeConfig().public.featureBaseOrganization = 'local-test'
  state.user = ref({ id: 1, name: 'QA', email: 'qa@example.test' })
  vi.stubGlobal('useAuthStore', () => ({ isImpersonating: false }))
  vi.stubGlobal('useAppStore', () => ({ featureBaseButtonVisible: true, featureBaseEnabled: true }))
  useState('featurebase-changelog-requested').value = false
})
afterEach(() => { document.getElementById('featurebase-sdk')?.remove(); delete window.Featurebase })

function requestChangelog() {
  useNuxtApp().runWithContext(() => {
    const navigation = useSharedNavigation()
    navigation.sharedNavigationSections.value.flatMap(section => section.items)
      .find(item => item.label === "What's new").onClick()
  })
}

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
  it('allows retrying when the component remounts during a failed SDK download', async () => {
    let wrapper = mount(FeatureBase, { global: { stubs: { Icon: true } } })
    await wrapper.get('button').trigger('click')
    const script = document.getElementById('featurebase-sdk')
    wrapper.unmount()
    wrapper = mount(FeatureBase, { global: { stubs: { Icon: true } } })
    await wrapper.get('button').trigger('click')
    expect(document.getElementById('featurebase-sdk')).toBe(script)
    script.dispatchEvent(new Event('error'))
    await wrapper.get('button').trigger('click')
    expect(document.querySelectorAll('#featurebase-sdk')).toHaveLength(1)
    expect(document.getElementById('featurebase-sdk')).not.toBe(script)
    wrapper.unmount()
  })
  it('loads and opens changelog as the first interaction, including intent before vendor mount', async () => {
    requestChangelog()
    expect(document.getElementById('featurebase-sdk')).toBeNull()
    const wrapper = mount(FeatureBase, { global: { stubs: { Icon: true } } })
    const script = document.getElementById('featurebase-sdk')
    expect(script).not.toBeNull()
    const sdk = vi.fn()
    window.Featurebase = sdk
    script.dispatchEvent(new Event('load'))
    const initialize = sdk.mock.calls.find(call => call[0] === 'initialize_changelog_widget')
    expect(initialize).toBeTruthy()
    expect(sdk).not.toHaveBeenCalledWith('manually_open_changelog_popup')
    initialize[2](null, { action: 'unreadChangelogsCountChanged', unreadCount: 0 })
    expect(sdk.mock.calls.filter(call => call[0] === 'manually_open_changelog_popup')).toHaveLength(1)
    requestChangelog()
    await nextTick()
    expect(sdk.mock.calls.filter(call => call[0] === 'manually_open_changelog_popup')).toHaveLength(2)
    wrapper.unmount()
  })
  it('retains changelog intent while the user query is loading', async () => {
    state.user.value = null
    requestChangelog()
    const wrapper = mount(FeatureBase, { global: { stubs: { Icon: true } } })
    expect(document.getElementById('featurebase-sdk')).toBeNull()
    state.user.value = { id: 1, email: 'qa@example.test' }
    await nextTick()
    expect(document.getElementById('featurebase-sdk')).not.toBeNull()
    wrapper.unmount()
  })
  it('retries a failed changelog download on another navigation click', async () => {
    const wrapper = mount(FeatureBase, { global: { stubs: { Icon: true } } })
    requestChangelog()
    await nextTick()
    document.getElementById('featurebase-sdk').dispatchEvent(new Event('error'))
    requestChangelog()
    await nextTick()
    expect(document.querySelectorAll('#featurebase-sdk')).toHaveLength(1)
    wrapper.unmount()
  })
})
