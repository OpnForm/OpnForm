import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import CheckboxInput from '../../components/forms/core/CheckboxInput.vue'

describe('CheckboxInput', () => {
  const createWrapper = (props = {}) => {
    return mount(CheckboxInput, {
      props: {
        name: 'terms',
        label: 'I accept the terms',
        modelValue: false,
        ...props,
      },
      global: {
        stubs: {
          InputWrapper: {
            props: ['inputStyle'],
            template: '<div :style="inputStyle"><slot name="label" /><slot /><slot name="help" /><slot name="error" /></div>',
          },
          Icon: true,
        },
        provide: {
          form: undefined,
        },
      },
    })
  }

  it('associates the visible label with the checkbox input', () => {
    const wrapper = createWrapper({ id: 'terms-checkbox' })

    const input = wrapper.find('input[type="checkbox"]')
    const labels = wrapper.findAll('label')
    const visibleLabel = labels.find(label => label.text().includes('I accept the terms'))

    expect(input.attributes('id')).toBe('terms-checkbox')
    expect(visibleLabel?.attributes('for')).toBe('terms-checkbox')
  })

  it('falls back to the field name when no id is provided', () => {
    const wrapper = createWrapper()

    const input = wrapper.find('input[type="checkbox"]')
    const labels = wrapper.findAll('label')
    const visibleLabel = labels.find(label => label.text().includes('I accept the terms'))

    expect(input.attributes('id')).toBe('terms')
    expect(visibleLabel?.attributes('for')).toBe('terms')
  })
})
