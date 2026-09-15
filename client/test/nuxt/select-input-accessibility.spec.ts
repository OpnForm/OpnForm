import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import SelectInput from '../../components/forms/core/SelectInput.vue'
import VSelect from '../../components/forms/core/components/VSelect.vue'

const options = [
  { name: 'Bug', value: 'bug' },
  { name: 'Suggestion', value: 'suggestion' },
]

const popoverStub = {
  props: ['open'],
  emits: ['update:open'],
  template: '<div><slot name="anchor" /><slot name="content" /></div>',
}

describe('SelectInput accessibility', () => {
  it('uses the field id for the select control', () => {
    const wrapper = mount(SelectInput, {
      props: {
        id: 'feedback-type',
        name: 'type_of_feedback',
        label: 'Type of feedback',
        options,
      },
      global: {
        stubs: {
          InputWrapper: {
            props: ['id', 'name', 'label'],
            template: '<div><label :for="id || name">{{ label }}</label><slot /></div>',
          },
          VSelect: {
            props: ['id'],
            template: '<button :id="id" />',
          },
          Icon: true,
        },
        provide: {
          form: undefined,
        },
      },
    })

    expect(wrapper.get('label').attributes('for')).toBe('feedback-type')
    expect(wrapper.get('button').attributes('id')).toBe('feedback-type')
  })

  it('falls back to the field name when no id is provided', () => {
    const wrapper = mount(SelectInput, {
      props: {
        name: 'type_of_feedback',
        label: 'Type of feedback',
        options,
      },
      global: {
        stubs: {
          InputWrapper: {
            props: ['id', 'name', 'label'],
            template: '<div><label :for="id || name">{{ label }}</label><slot /></div>',
          },
          VSelect: {
            props: ['id'],
            template: '<button :id="id" />',
          },
          Icon: true,
        },
        provide: {
          form: undefined,
        },
      },
    })

    expect(wrapper.get('label').attributes('for')).toBe('type_of_feedback')
    expect(wrapper.get('button').attributes('id')).toBe('type_of_feedback')
  })
})

describe('VSelect accessibility', () => {
  it('connects the control, listbox, and active option with unique ids', async () => {
    const wrapper = mount(VSelect, {
      props: {
        id: 'feedback-type',
        data: options,
        optionKey: 'value',
      },
      global: {
        stubs: {
          UPopover: popoverStub,
          Icon: true,
          Loader: true,
        },
      },
    })

    const button = wrapper.get('button[aria-haspopup="listbox"]')
    const listbox = wrapper.get('[role="listbox"]')
    const renderedOptions = wrapper.findAll('[role="option"]')

    expect(button.attributes('id')).toBe('feedback-type')
    expect(button.attributes('aria-controls')).toBe('feedback-type-listbox')
    expect(button.attributes('aria-labelledby')).toBeUndefined()
    expect(listbox.attributes('id')).toBe('feedback-type-listbox')
    expect(renderedOptions.map(option => option.attributes('id'))).toEqual([
      'feedback-type-option-0',
      'feedback-type-option-1',
    ])

    await button.trigger('keydown', { key: 'ArrowDown' })
    await wrapper.vm.$nextTick()

    expect(listbox.attributes('aria-activedescendant')).toBe('feedback-type-option-0')
  })

  it('uses native button disabling', () => {
    const wrapper = mount(VSelect, {
      props: {
        id: 'feedback-type',
        data: options,
        disabled: true,
      },
      global: {
        stubs: {
          UPopover: popoverStub,
          Icon: true,
          Loader: true,
        },
      },
    })

    expect(wrapper.get('button[aria-haspopup="listbox"]').attributes('disabled')).toBeDefined()
  })
})
