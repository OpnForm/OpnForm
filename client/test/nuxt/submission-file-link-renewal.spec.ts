import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import FormSubmissions from '~/components/open/forms/components/FormSubmissions.vue'
import OpenFile from '~/components/open/tables/components/OpenFile.vue'

const query = vi.hoisted(() => ({ refetch: vi.fn() }))
vi.mock('~/composables/query/forms/useFormSubmissions', () => ({
  useFormSubmissions: () => ({ paginatedList: () => ({ refetch: query.refetch }) }),
}))

let observers: MockIntersectionObserver[]
class MockIntersectionObserver {
  target?: Element
  callback: IntersectionObserverCallback
  disconnect = vi.fn()
  constructor(callback: IntersectionObserverCallback) {
    this.callback = callback
    observers.push(this)
  }
  observe(target: Element) { this.target = target }
  intersect(isIntersecting: boolean) {
    this.callback([{ isIntersecting } as IntersectionObserverEntry], this as unknown as IntersectionObserver)
  }
}

beforeEach(() => {
  observers = []
  query.refetch.mockReset()
  vi.stubGlobal('IntersectionObserver', MockIntersectionObserver)
})
afterEach(() => {
  vi.unstubAllGlobals()
  vi.restoreAllMocks()
})

function mountTable(expires = Math.floor(Date.now() / 1000) - 1) {
  const files = ref([
    { file_name: 'first.jpg', file_url: `/first.jpg?expires=${expires}` },
    { file_name: 'second.jpg', file_url: `/second.jpg?expires=${expires}` },
  ])
  const wrapper = mount(FormSubmissions, {
    props: { form: { id: 1 } },
    global: {
      stubs: {
        DashboardLoadingBlock: { props: ['loading'], template: '<div><slot /></div>' },
        USkeleton: true,
        UBadge: { template: '<span><slot /></span>' },
        UButton: true,
        Icon: true,
        OpenTable: { components: { OpenFile }, setup: () => ({ files }), template: '<OpenFile :value="files" />' },
        UModal: true,
      },
    },
  })
  return { wrapper, files }
}

describe('expired submission image links', () => {
  it('renews concurrent expired images once and loads the refreshed URLs', async () => {
    let resolveRefresh!: (result: unknown) => void
    query.refetch.mockImplementation(() => new Promise(resolve => { resolveRefresh = resolve }))
    const { wrapper, files } = mountTable()
    await flushPromises()
    observers.forEach(observer => observer.intersect(true))
    await flushPromises()
    expect(query.refetch).toHaveBeenCalledTimes(1)
    expect(query.refetch).toHaveBeenCalledWith({ cancelRefetch: false, throwOnError: true })
    expect(wrapper.findAll('img[src]')).toHaveLength(0)

    const oldObservers = [...observers]
    const expires = Math.floor(Date.now() / 1000) + 600
    files.value = files.value.map(file => ({ ...file, file_url: file.file_url.replace(/expires=\d+/, `expires=${expires}`) }))
    resolveRefresh({ data: { data: [{ data: { images: files.value } }] } })
    await flushPromises()
    expect(oldObservers.every(observer => observer.disconnect.mock.calls.length > 0)).toBe(true)
    observers.filter(observer => !oldObservers.includes(observer)).forEach(observer => observer.intersect(true))
    await flushPromises()
    expect(wrapper.findAll('img').map(image => image.attributes('src'))).toEqual(files.value.map(file => file.file_url))
    wrapper.unmount()
  })

  it('trusts successfully refreshed links when the browser clock is ahead', async () => {
    const { wrapper, files } = mountTable()
    await flushPromises()
    const expires = Math.floor(Date.now() / 1000) + 600
    files.value = files.value.map(file => ({ ...file, file_url: file.file_url.replace(/expires=\d+/, `expires=${expires}`) }))
    await flushPromises()
    // The server considers these links fresh, but this browser thinks they expired.
    const now = Date.now()
    const clock = vi.spyOn(Date, 'now').mockReturnValue(now + 20 * 60 * 1000)
    query.refetch.mockResolvedValue({ data: { data: [{ data: { images: files.value } }] } })
    observers.forEach(observer => observer.intersect(true))
    await flushPromises()
    expect(query.refetch).toHaveBeenCalledTimes(1)
    expect(wrapper.findAll('img').map(image => image.attributes('src'))).toEqual(files.value.map(file => file.file_url))
    await Promise.all(wrapper.findAll('img').map(image => image.trigger('load')))
    expect(observers.slice(-2).every(observer => observer.disconnect.mock.calls.length > 0)).toBe(true)
    clock.mockRestore()
    wrapper.unmount()
  })

  it('renews failed signed images with a behind-clock browser and bounds broken-file retries', async () => {
    const now = Date.now()
    vi.spyOn(Date, 'now').mockReturnValue(now - 20 * 60 * 1000)
    await flushPromises()
    const { wrapper, files } = mountTable(Math.floor(now / 1000) - 1)
    await flushPromises()
    const initialObservers = [...observers]
    initialObservers.forEach(observer => observer.intersect(true))
    await flushPromises()
    // The browser thinks the expired links are still valid until the request fails.
    expect(query.refetch).not.toHaveBeenCalled()
    expect(wrapper.findAll('img[src]')).toHaveLength(2)
    const renewedFiles = files.value.map(file => ({ ...file, file_url: file.file_url.replace(/expires=\d+/, `expires=${Math.floor(now / 1000) + 600}`) }))
    query.refetch.mockImplementation(() => {
      files.value = renewedFiles
      return Promise.resolve({ data: { data: [{ data: { images: renewedFiles } }] } })
    })
    await wrapper.findAll('img')[0].trigger('error')
    await flushPromises()
    expect(query.refetch).toHaveBeenCalledTimes(1)
    observers.filter(observer => !initialObservers.includes(observer)).forEach(observer => observer.intersect(true))
    await flushPromises()
    expect(wrapper.findAll('img').map(image => image.attributes('src'))).toEqual(renewedFiles.map(file => file.file_url))

    // A genuinely missing file still uses the normal download fallback after renewal.
    await wrapper.findAll('img')[0].trigger('error')
    await flushPromises()
    expect(query.refetch).toHaveBeenCalledTimes(1)
    expect(wrapper.findAll('img')).toHaveLength(1)
    expect(wrapper.find('a').attributes('href')).toBe(renewedFiles[0].file_url)
    wrapper.unmount()
  })

  it('keeps expired sources unset after a renewal failure and retries on re-entry', async () => {
    const warn = vi.spyOn(console, 'warn').mockImplementation(() => {})
    query.refetch.mockRejectedValue(new Error('Offline'))
    const { wrapper } = mountTable()
    await flushPromises()
    observers.forEach(observer => observer.intersect(true))
    await flushPromises()
    expect(query.refetch).toHaveBeenCalledTimes(1)
    expect(wrapper.findAll('img[src]')).toHaveLength(0)
    expect(wrapper.findAll('button[aria-label^="Preview"]')).toHaveLength(2)
    expect(observers.every(observer => observer.disconnect.mock.calls.length === 0)).toBe(true)

    observers.forEach(observer => observer.intersect(false))
    await flushPromises()
    observers.forEach(observer => observer.intersect(true))
    await flushPromises()
    expect(query.refetch).toHaveBeenCalledTimes(2)
    expect(wrapper.findAll('img[src]')).toHaveLength(0)
    wrapper.unmount()
    warn.mockRestore()
  })
})
