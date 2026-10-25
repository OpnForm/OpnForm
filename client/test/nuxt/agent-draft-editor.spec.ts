import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { computed, defineComponent, nextTick, reactive, watch } from 'vue'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import DraftEditor from '~/pages/agent-drafts/edit.vue'

const mocks = vi.hoisted(() => ({
  store: null as any,
  fetch: vi.fn(),
  authenticated: false,
  alert: { success: vi.fn(), warning: vi.fn(), error: vi.fn() },
}))

vi.mock('~/composables/useIsAuthenticated', () => ({
  useIsAuthenticated: () => ({ isAuthenticated: computed(() => mocks.authenticated) }),
}))
mockNuxtImport('$fetch', () => mocks.fetch)
mockNuxtImport('useOpnSeoMeta', () => () => {})
vi.mock('~/composables/useAlert', () => ({ useAlert: () => mocks.alert }))
mockNuxtImport('useWindowMessage', () => () => ({ listen: vi.fn() }))
mockNuxtImport('useForm', () => (definition: any) => reactive({
  ...definition,
  data() {
    const { data: _data, ...values } = this
    return values
  },
}))

// Exercise the same settings hydration as FormEditor, without the full builder.
vi.mock('~/components/open/forms/components/FormEditor.vue', () => ({
  default: defineComponent({
    name: 'AgentFormEditorStub',
    props: ['saveHandler', 'loading'],
    setup() {
      watch(() => mocks.store.content, (form) => {
        if (Array.isArray(form.settings)) form.settings = {}
      }, { immediate: true, flush: 'sync' })
    },
    template: '<div><slot name="before-save" /></div>',
  }),
}))
vi.mock('~/api', () => ({ workspaceApi: { list: vi.fn().mockResolvedValue([{ id: 1, name: 'Test workspace' }]) } }))

const draft = (version = 1, title = 'Agent form') => ({
  version,
  definition: { title, settings: [], properties: [{ id: 'name', type: 'text', name: 'Name' }] },
})

let wrapper: ReturnType<typeof mount> | undefined
let fetchMock: ReturnType<typeof vi.fn>

async function openEditor() {
  wrapper = mount(DraftEditor, {
    global: { stubs: { UModal: true, UButton: true, UAlert: true, Loader: true, SelectInput: true } },
  })
  await flushPromises()
  await nextTick()
}

async function debounceSave() {
  await nextTick()
  await vi.advanceTimersByTimeAsync(900)
  await flushPromises()
}

function writes() {
  return fetchMock.mock.calls.filter(([, options]) => options?.method === 'PUT')
}

describe('agent draft editor autosave', () => {
  beforeEach(() => {
    vi.useFakeTimers()
    vi.clearAllMocks()
    mocks.authenticated = false
    mocks.store = reactive({ content: null, set(form: any) { this.content = form } })
    fetchMock = mocks.fetch.mockReset().mockResolvedValue({ draft: draft() })
    vi.stubGlobal('$fetch', fetchMock)
    vi.stubGlobal('useWorkingFormStore', () => mocks.store)
    vi.stubGlobal('useAppStore', () => ({}))
  })

  afterEach(() => {
    wrapper?.unmount()
    wrapper = undefined
    vi.useRealTimers()
    vi.unstubAllGlobals()
  })

  it('does not save an untouched draft after editor hydration', async () => {
    await openEditor()
    await vi.advanceTimersByTimeAsync(9000)
    expect(writes()).toHaveLength(0)
  })

  it('saves one edit once even when the server normalizes settings back to an array', async () => {
    await openEditor()
    fetchMock.mockResolvedValue({ draft: draft(2, 'Changed') })
    const form = mocks.store.content
    form.title = 'Changed'
    await debounceSave()
    await vi.advanceTimersByTimeAsync(9000)

    expect(writes()).toHaveLength(1)
    expect(writes()[0][1].body).toMatchObject({ expected_version: 1, definition: { title: 'Changed', settings: {} } })
    expect(mocks.store.content).toBe(form)
  })

  it('keeps edits made during a save and serializes the next save with the new version', async () => {
    await openEditor()
    let resolveSave: (value: any) => void = () => {}
    fetchMock.mockImplementationOnce(() => new Promise(resolve => { resolveSave = resolve }))
    mocks.store.content.title = 'First'
    await debounceSave()
    mocks.store.content.title = 'Second'
    await debounceSave()
    expect(writes()).toHaveLength(1)

    fetchMock.mockResolvedValue({ draft: draft(3, 'Second') })
    resolveSave({ draft: draft(2, 'First') })
    await flushPromises()

    expect(writes()).toHaveLength(2)
    expect(writes()[1][1].body).toMatchObject({ expected_version: 2, definition: { title: 'Second' } })
    expect(mocks.store.content.title).toBe('Second')
    await vi.advanceTimersByTimeAsync(9000)
    expect(writes()).toHaveLength(2)
  })

  it.each([false, true])('guards closing during an in-flight save with a second edit (debounce elapsed: %s)', async (debounceElapsed) => {
    await openEditor()
    let resolveSave: (value: any) => void = () => {}
    fetchMock.mockImplementationOnce(() => new Promise(resolve => { resolveSave = resolve }))
    mocks.store.content.title = 'First'
    await debounceSave()
    mocks.store.content.title = 'Latest edit'
    await nextTick()
    if (debounceElapsed) await debounceSave()

    const beforeUnload = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(beforeUnload)
    expect(beforeUnload.defaultPrevented).toBe(true)
    window.dispatchEvent(new Event('pagehide'))

    fetchMock.mockResolvedValue({ draft: draft(3, 'Latest edit') })
    resolveSave({ draft: draft(2, 'First') })
    await flushPromises()
    expect(writes()).toHaveLength(2)
    expect(writes()[1][1].body.definition.title).toBe('Latest edit')

    const savedUnload = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(savedUnload)
    expect(savedUnload.defaultPrevented).toBe(false)
  })

  it('does not guard an unchanged draft and removes the guard on navigation', async () => {
    await openEditor()
    const unchangedUnload = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(unchangedUnload)
    expect(unchangedUnload.defaultPrevented).toBe(false)
    mocks.store.content.title = 'Unsaved edit'
    await nextTick()
    const dirtyUnload = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(dirtyUnload)
    expect(dirtyUnload.defaultPrevented).toBe(true)
    wrapper?.unmount()
    wrapper = undefined
    const navigatedUnload = new Event('beforeunload', { cancelable: true })
    window.dispatchEvent(navigatedUnload)
    expect(navigatedUnload.defaultPrevented).toBe(false)
    await flushPromises()
  })

  it('blocks the editor while the final save and account claim are in progress', async () => {
    mocks.authenticated = true
    await openEditor()
    mocks.store.content.title = 'Final account draft'
    await nextTick()
    let resolveSave: (value: any) => void = () => {}
    fetchMock.mockImplementationOnce(() => new Promise(resolve => { resolveSave = resolve }))
    wrapper?.findComponent({ name: 'UButton' }).vm.$emit('click', new MouseEvent('click'))
    await nextTick()

    expect(writes()).toHaveLength(1)
    expect(wrapper?.findComponent({ name: 'AgentFormEditorStub' }).props('loading')).toBe(true)
    fetchMock.mockImplementationOnce(() => new Promise(() => {}))
    resolveSave({ draft: draft(2, 'Final account draft') })
    await flushPromises()
    expect(fetchMock.mock.calls.some(([url]) => url === '/api/agent-drafts/claim')).toBe(true)
    expect(wrapper?.findComponent({ name: 'AgentFormEditorStub' }).props('loading')).toBe(true)
  })

  it('flushes the original editor when navigation replaces the shared form during a save', async () => {
    await openEditor()
    let resolveSave: (value: any) => void = () => {}
    fetchMock.mockImplementationOnce(() => new Promise(resolve => { resolveSave = resolve }))
    mocks.store.content.title = 'First'
    await debounceSave()
    mocks.store.content.title = 'Last edit before navigation'
    await nextTick()
    wrapper?.unmount()
    wrapper = undefined
    const destinationForm = { title: 'Unrelated destination form', data() { return { title: this.title } } }
    mocks.store.set(destinationForm)

    fetchMock.mockResolvedValue({ draft: draft(3, 'Last edit before navigation') })
    resolveSave({ draft: draft(2, 'First') })
    await flushPromises()

    expect(writes()).toHaveLength(2)
    expect(writes()[1][1]).toMatchObject({
      keepalive: true,
      body: { expected_version: 2, definition: { title: 'Last edit before navigation' } },
    })
    expect(mocks.store.content.title).toBe('Unrelated destination form')
  })

  it('does not replace the destination form when a conflict refresh finishes after navigation', async () => {
    await openEditor()
    let rejectSave: (error: any) => void = () => {}
    fetchMock.mockImplementationOnce(() => new Promise((_resolve, reject) => { rejectSave = reject }))
    mocks.store.content.title = 'Local edit'
    await debounceSave()
    wrapper?.unmount()
    wrapper = undefined
    mocks.store.set({ title: 'Destination form' })
    fetchMock.mockResolvedValue({ draft: draft(37, 'Agent revision') })
    rejectSave({ statusCode: 422, data: { data: { errors: { expected_version: ['Conflict'] } } } })
    await flushPromises()

    expect(mocks.store.content.title).toBe('Destination form')
    expect(writes()).toHaveLength(1)
  })

  it('refreshes a wrapped version conflict without replaying a queued stale edit', async () => {
    await openEditor()
    let rejectSave: (error: any) => void = () => {}
    fetchMock.mockImplementationOnce(() => new Promise((_resolve, reject) => { rejectSave = reject }))
    mocks.store.content.title = 'Local'
    await debounceSave()
    mocks.store.content.title = 'Queued local edit'
    await debounceSave()

    fetchMock.mockResolvedValue({ draft: draft(37, 'Agent revision') })
    rejectSave({ statusCode: 422, data: { data: { errors: { expected_version: ['Conflict'] } } } })
    await flushPromises()
    await vi.advanceTimersByTimeAsync(9000)

    expect(mocks.store.content.title).toBe('Agent revision')
    expect(writes()).toHaveLength(1)
    await flushPromises()
    mocks.store.content.title = 'New edit after refresh'
    await debounceSave()
    expect(writes()).toHaveLength(2)
    expect(writes()[1][1].body.expected_version).toBe(37)
    expect(mocks.alert.warning).toHaveBeenCalledOnce()
    expect(mocks.alert.error).not.toHaveBeenCalled()
  })
})
