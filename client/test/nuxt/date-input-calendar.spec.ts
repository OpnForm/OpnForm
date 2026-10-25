import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { mount, type VueWrapper } from '@vue/test-utils'
import { defineComponent, h, nextTick } from 'vue'
import { DatePicker } from 'v-calendar'
import DateInput from '../../components/forms/heavy/DateInput.vue'

const wrappers: VueWrapper[] = []
beforeEach(() => {
  vi.stubGlobal('ResizeObserver', class {
    observe() {}
    unobserve() {}
    disconnect() {}
  })
})
const Popover = defineComponent({
  setup(_, { slots }) {
    return () => h('div', [slots.default?.(), slots.content?.()])
  },
})

function createWrapper(props = {}) {
  const wrapper = mount(DateInput, {
    props: { name: 'appointment', modelValue: null, ...props },
    global: {
      stubs: {
        InputWrapper: { template: '<div><slot /></div>' },
        UPopover: Popover,
        Icon: true,
      },
      provide: { form: undefined },
    },
  })
  wrappers.push(wrapper)
  return wrapper
}

afterEach(() => {
  wrappers.splice(0).forEach(wrapper => wrapper.unmount())
  vi.unstubAllGlobals()
})

describe('DateInput with the installed calendar and date formatter', () => {
  it('renders a saved date without changing its day or format', async () => {
    const wrapper = createWrapper({ modelValue: '2026-10-25T12:30:00' })
    await nextTick()
    expect(wrapper.find('button').attributes('aria-label')).toBe('Selected date: 25/10/2026')
    expect(wrapper.findComponent(DatePicker).exists()).toBe(true)
  })

  it('emits an ISO timestamp preserving the selected instant', async () => {
    const wrapper = createWrapper({ withTime: true })
    const selected = new Date(2026, 9, 25, 12, 30)
    wrapper.findComponent(DatePicker).vm.$emit('update:modelValue', selected)
    await nextTick()
    const value = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as string
    expect(value).toMatch(/^2026-10-25T12:30:00(?:Z|[+-]\d{2}:\d{2})$/)
    expect(new Date(value).getTime()).toBe(selected.getTime())
  })

  it('preserves both dates in a selected range', async () => {
    const wrapper = createWrapper({ dateRange: true })
    const start = new Date(2026, 9, 24, 12)
    const end = new Date(2026, 9, 26, 12)
    wrapper.findComponent(DatePicker).vm.$emit('update:modelValue', { start, end })
    await nextTick()
    const values = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as string[]
    expect(values).toHaveLength(2)
    expect(values.map(value => new Date(value).getTime())).toEqual([start.getTime(), end.getTime()])
    expect(wrapper.find('button').attributes('aria-label')).toBe('Selected date: 24/10/2026 - 26/10/2026')
  })

  it('clears a saved selection through its visible clear action', async () => {
    const wrapper = createWrapper({ modelValue: '2026-10-25T12:30:00' })
    await nextTick()
    await wrapper.find('button[aria-label="Clear date"]').trigger('click')
    await nextTick()
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toBeNull()
    expect(wrapper.find('button').attributes('aria-label')).toBe('Select date')
  })

  it.each(['Enter', ' '])('clears with the %s key without reopening the calendar', async (key) => {
    const wrapper = createWrapper({ modelValue: '2026-10-25T12:30:00' })
    await nextTick()
    await wrapper.find('button[aria-label="Clear date"]').trigger('keydown', { key })
    await nextTick()
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toBeNull()
    expect(wrapper.find('button[aria-label="Select date"]').attributes('aria-expanded')).toBe('false')
  })
})
