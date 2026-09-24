import { describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { useElementVisibility } from '@vueuse/core'
import { nextTick } from 'vue'
import type { Ref } from 'vue'
import GoogleFontPicker from '~/components/open/editors/GoogleFontPicker.vue'

const mockQuery = vi.hoisted(() => ({ data: null as Ref<string[]> | null }))

vi.mock('~/composables/query/useContent', async () => {
  const { ref } = await import('vue')

  return {
    useContent: () => {
      const data = ref([])
      mockQuery.data = data
      return { fonts: { list: () => ({ data, isLoading: ref(false) }) } }
    },
  }
})

vi.mock('@vueuse/core', async (importOriginal) => {
  const original = await importOriginal()
  const { ref } = await import('vue')

  return {
    ...original,
    useElementVisibility: vi.fn(() => ref(true)),
  }
})

vi.mock('~/components/global/OverlayScrollbarsComponent.client.vue', () => ({
  default: { template: '<div class="font-scroll-container"><slot /></div>' },
}))

function mountPicker(show = true) {
  return mount(GoogleFontPicker, {
    props: { show },
    global: {
      stubs: {
        UModal: {
          props: ['open'],
          template: '<div v-if="open"><slot name="body" /><slot name="footer" /></div>',
        },
        TextInput: { template: '<input />' },
        USkeleton: { template: '<div />' },
        UButton: { template: '<button><slot /></button>' },
        Icon: true,
      },
    },
  })
}

describe('GoogleFontPicker', () => {
  it('shows fonts when their response arrives without requiring a scrollbar ref method', async () => {
    const wrapper = mountPicker()
    expect(wrapper.find('.font-scroll-container').exists()).toBe(true)
    mockQuery.data!.value = ['Roboto']
    await nextTick()
    await flushPromises()

    expect(vi.mocked(useElementVisibility).mock.calls.at(-1)?.[1]?.root).toBeInstanceOf(HTMLElement)
    expect(wrapper.text()).toContain('The quick brown fox jumped over the lazy dog')
    wrapper.unmount()
  })

  it('shows fonts already cached before the picker opens', async () => {
    const wrapper = mountPicker(false)
    mockQuery.data!.value = ['Roboto']
    await nextTick()
    await wrapper.setProps({ show: true })
    await flushPromises()

    expect(wrapper.text()).toContain('The quick brown fox jumped over the lazy dog')
    wrapper.unmount()
  })
})
