import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import OpenFileThumbnail from '~/components/open/tables/components/OpenFileThumbnail.vue'

let observers: MockIntersectionObserver[]

class MockIntersectionObserver {
  callback: IntersectionObserverCallback
  observe = vi.fn()
  unobserve = vi.fn()
  disconnect = vi.fn()

  constructor(callback: IntersectionObserverCallback) {
    this.callback = callback
    observers.push(this)
  }

  intersect(isIntersecting: boolean) {
    this.callback([{ isIntersecting } as IntersectionObserverEntry], this as unknown as IntersectionObserver)
  }
}

beforeEach(() => {
  observers = []
  vi.stubGlobal('IntersectionObserver', MockIntersectionObserver)
})

afterEach(() => vi.unstubAllGlobals())

describe('submission table image loading', () => {
  it('withholds the image URL until visible and retains it after scrolling away', async () => {
    const wrapper = mount(OpenFileThumbnail, { props: { src: '/test-image.jpg', alt: 'Upload' } })
    await flushPromises()
    const observer = observers.find(observer => observer.observe.mock.calls.some(([el]) => el === wrapper.element))!

    observer.intersect(false)
    await flushPromises()
    expect(wrapper.attributes('src')).toBeUndefined()

    observer.intersect(true)
    await flushPromises()
    expect(wrapper.attributes('src')).toBe('/test-image.jpg')
    expect(observer.disconnect).toHaveBeenCalled()

    // A queued exit notification must not remove an already loaded source.
    observer.intersect(false)
    await flushPromises()
    expect(wrapper.attributes('src')).toBe('/test-image.jpg')
    wrapper.unmount()
  })

  it('preserves error handling and disconnects when an unseen row is removed', async () => {
    const onError = vi.fn()
    const wrapper = mount(OpenFileThumbnail, { props: { src: '/missing.jpg', alt: 'Upload', onError } })
    await flushPromises()
    const observer = observers.find(observer => observer.observe.mock.calls.some(([el]) => el === wrapper.element))!

    await wrapper.trigger('error')
    expect(onError).toHaveBeenCalledOnce()
    wrapper.unmount()
    expect(observer.disconnect).toHaveBeenCalled()
  })

  it('still displays images when IntersectionObserver is unavailable', async () => {
    Reflect.deleteProperty(window, 'IntersectionObserver')
    Reflect.deleteProperty(globalThis, 'IntersectionObserver')
    const wrapper = mount(OpenFileThumbnail, { props: { src: '/test-image.jpg', alt: 'Upload' } })
    await flushPromises()
    expect(wrapper.attributes('src')).toBe('/test-image.jpg')
    wrapper.unmount()
  })
})
