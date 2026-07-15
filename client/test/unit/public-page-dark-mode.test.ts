// @vitest-environment happy-dom
import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import {
  computed,
  defineComponent,
  nextTick,
  onMounted,
  onUnmounted,
  ref,
  watch,
} from 'vue'
import { darkModeEnabled } from '../../lib/forms/public-page.js'

vi.stubGlobal('computed', computed)
vi.stubGlobal('onMounted', onMounted)
vi.stubGlobal('onUnmounted', onUnmounted)
vi.stubGlobal('ref', ref)
vi.stubGlobal('watch', watch)

describe('darkModeEnabled', () => {
  it('observes the element referenced by the ref', async () => {
    const wrapper = mount(defineComponent({
      setup () {
        const target = ref(null)
        const isDark = darkModeEnabled(target)

        return {
          isDark,
          target,
        }
      },
      template: '<div ref="target">{{ isDark }}</div>',
    }))

    expect(wrapper.text()).toBe('false')

    wrapper.element.classList.add('dark')
    await nextTick()

    expect(wrapper.text()).toBe('true')
  })
})
