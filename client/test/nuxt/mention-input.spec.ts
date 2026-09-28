import { describe, expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import MentionInput from '~/components/forms/heavy/MentionInput.vue'

mockNuxtImport('useAppModals', () => () => ({ openSubscriptionModal: vi.fn() }))

describe('MentionInput', () => {
  function createWrapper(props = {}) {
    return mount(MentionInput, {
      props: {
        name: 'amount',
        ...props,
      },
      global: {
        stubs: {
          InputWrapper: {
            template: '<div><slot name="label" /><slot /><slot name="help" /><slot name="error" /></div>',
          },
          MentionDropdown: {
            template: '<div><slot /></div>',
          },
          UButton: true,
        },
        provide: {
          form: undefined,
        },
      },
    })
  }

  it('renders and edits a numeric form value without throwing', async () => {
    const form = { amount: 5 }
    const wrapper = createWrapper({ form })
    const editableDiv = wrapper.find('[contenteditable="true"]')

    expect(editableDiv.html()).toContain('5')
    expect(form.amount).toBe(5)

    editableDiv.element.innerHTML = '7'
    await editableDiv.trigger('input')

    expect(form.amount).toBe('7')
  })

  it('preserves mention HTML values', () => {
    const mention = '<span mention="true" class="mention-item">Order total</span>'
    const wrapper = createWrapper({ modelValue: mention })

    expect(wrapper.find('[contenteditable="true"]').element.innerHTML).toBe(mention)
  })

  it('renders zero on mount', () => {
    const wrapper = createWrapper({ modelValue: 0 })

    expect(wrapper.find('[contenteditable="true"]').text()).toBe('0')
  })

  it('updates numeric values and clears nullish values', async () => {
    const wrapper = createWrapper({ modelValue: 5 })
    const editableDiv = wrapper.find('[contenteditable="true"]')

    await wrapper.setProps({ modelValue: 10 })
    expect(editableDiv.text()).toBe('10')
    await wrapper.setProps({ modelValue: null })
    expect(editableDiv.text()).toBe('')
    await wrapper.setProps({ modelValue: 0 })
    expect(editableDiv.text()).toBe('0')
    await wrapper.setProps({ modelValue: undefined })
    expect(editableDiv.text()).toBe('')
  })
})
