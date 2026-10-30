import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { describe, expect, it } from 'vitest'
import ColumnCondition from '../../components/open/forms/components/form-logic-components/ColumnCondition.vue'

const budgetField = { id: 'budget', name: 'Budget', type: 'number' }
const emailField = { id: 'email', name: 'Email', type: 'email' }
const totalVariable = { id: 'cv_total', name: 'Total', result_type: 'number' }
const taxVariable = { id: 'cv_tax', name: 'Tax', result_type: 'number' }

const mentionInputStub = defineComponent({
  name: 'MentionInput',
  inheritAttrs: false,
  props: {
    mentions: { type: Array, default: () => [] },
    computedVariables: { type: Array, default: () => [] },
    modelValue: { type: [String, Number], default: null },
  },
  emits: ['update:modelValue'],
  template: '<div data-test="mention-input" />',
})

function mountColumnCondition({
  property = {
    id: 'cv_total',
    name: 'Total',
    type: 'computed',
    result_type: 'number',
  },
  formProperties = [budgetField, emailField],
  formComputedVariables = [totalVariable, taxVariable],
  modelValue = {
    operator: 'greater_than',
    value: null,
  },
} = {}) {
  ;(ColumnCondition as any).components.MentionInput = mentionInputStub

  const TestColumnCondition = defineComponent({
    extends: ColumnCondition,
    computed: {
      property() {
        return property
      },
      formProperties() {
        return formProperties
      },
      formComputedVariables() {
        return formComputedVariables
      },
      viewContext() {
        return {
          form_slug: 'test-form',
          workspace_id: 1,
        }
      },
    },
  })

  return mount(TestColumnCondition, {
    props: {
      modelValue,
    },
    global: {
      stubs: {
        USelectMenu: {
          template: '<div data-test="select-menu" />',
          props: ['modelValue', 'items'],
        },
        MentionInput: mentionInputStub,
        TextInput: { name: 'TextInput', inheritAttrs: false, props: ['modelValue'], emits: ['update:modelValue'], template: '<input />' },
        PhoneInput: true,
        DateInput: true,
      },
    },
  })
}

describe('ColumnCondition computed mentions', () => {
  it('renders MentionInput for computed conditions', async () => {
    const wrapper = mountColumnCondition()
    await flushPromises()

    expect(wrapper.vm.inputComponentData.component).toBe('MentionInput')
    expect(wrapper.find('[data-test="mention-input"]').exists()).toBe(true)
  })

  it('passes form fields and excludes the current computed variable from mention targets', () => {
    const wrapper = mountColumnCondition()

    expect(wrapper.vm.inputComponentData.mentions).toEqual([budgetField, emailField])
    expect(wrapper.vm.inputComponentData.computedVariables).toEqual([taxVariable])
  })

  it('casts numeric computed literals but preserves mention HTML values', () => {
    const wrapper = mountColumnCondition()
    wrapper.vm.content.operator = 'greater_than'

    expect(wrapper.vm.castContent({ value: '100' }).value).toBe(100)

    const mentionValue = '<span mention="true" mention-field-id="budget" mention-field-name="Budget" mention-fallback="">@Budget</span>'
    expect(wrapper.vm.castContent({ value: mentionValue }).value).toBe(mentionValue)
  })
})


describe('ColumnCondition phone regex patterns', () => {
  it.each(['matches_regex', 'does_not_match_regex'])('accepts a plain text pattern for %s', async (operator) => {
    const wrapper = mountColumnCondition({
      property: { id: 'phone', name: 'Phone', type: 'phone_number' } as any,
      modelValue: { operator, value: '^\\+?[0-9]{10,15}$' },
    })
    await flushPromises()

    expect(wrapper.vm.inputComponentData.component).toBe('TextInput')
    const input = wrapper.findComponent({ name: 'TextInput' })
    expect(input.exists()).toBe(true)
    input.vm.$emit('update:modelValue', '^0[1-9][0-9]{8}$')
    await flushPromises()
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toMatchObject({
      operator, value: '^0[1-9][0-9]{8}$',
    })
    wrapper.unmount()
  })

  it('keeps the phone input for a phone equality condition', () => {
    const wrapper = mountColumnCondition({
      property: { id: 'phone', name: 'Phone', type: 'phone_number' } as any,
      modelValue: { operator: 'equals', value: '+33123456789' },
    })
    expect(wrapper.vm.inputComponentData.component).toBe('PhoneInput')
    wrapper.unmount()
  })
})

describe('ColumnCondition date references', () => {
  const start = { id: 'start', name: 'Start date', type: 'date' }
  const end = { id: 'end', name: 'End date', type: 'date' }
  function mountDate(value: any = '2026-10-30') {
    return mountColumnCondition({
      property: end as any,
      formProperties: [start, end, { id: 'range', name: 'Range', type: 'date', date_range: true }, emailField] as any,
      formComputedVariables: [totalVariable, { id: 'cv_date', name: 'Computed date', result_type: 'text' }],
      modelValue: { operator: 'on_or_after', value },
    })
  }

  it('switches from a fixed date to a reference and restores saved references', async () => {
    const wrapper = mountDate()
    expect(wrapper.vm.dateValueMode).toBe('fixed')
    wrapper.vm.changeDateValueMode('reference')
    wrapper.vm.selectDateReference('start')
    await flushPromises()
    const saved = wrapper.emitted('update:modelValue')?.at(-1)?.[0] as any
    expect(saved.value).toContain('mention-field-id="start"')
    const restored = mountDate(saved.value)
    expect(restored.vm.dateValueMode).toBe('reference')
    expect(restored.vm.dateReferenceId).toBe('start')
    wrapper.vm.changeDateValueMode('fixed')
    expect(wrapper.vm.content.value).toBeNull()
    wrapper.unmount()
    restored.unmount()
  })

  it('offers other single date fields and non-numeric variables', () => {
    const wrapper = mountDate()
    expect(wrapper.vm.dateReferenceOptions.map((option: any) => option.value)).toEqual(['start', 'cv_date'])
    wrapper.unmount()
  })

  it('clears a date reference when switching to a relative day count', async () => {
    const wrapper = mountDate()
    wrapper.vm.changeDateValueMode('reference')
    wrapper.vm.selectDateReference('start')
    wrapper.vm.content.operator = 'within_next_x_days'
    await flushPromises()
    expect(wrapper.vm.supportsDateReference).toBe(false)
    expect(wrapper.vm.content.value).toBeNull()
    expect(wrapper.vm.inputComponentData.component).toBe('TextInput')
    wrapper.unmount()
  })
})

describe('date condition time settings', () => {
  it('offers matching date-time references and enables time in the fixed date picker', () => {
    const wrapper = mountColumnCondition({
      property: { id: 'end', name: 'End', type: 'date', with_time: true } as any,
      formProperties: [
        { id: 'start', name: 'Start', type: 'date', with_time: true },
        { id: 'day', name: 'Day', type: 'date' },
      ] as any,
      formComputedVariables: [],
      modelValue: { operator: 'on_or_after', value: null },
    })
    expect(wrapper.vm.dateReferenceOptions.map((option: any) => option.value)).toEqual(['start'])
    expect(wrapper.vm.inputComponentData.withTime).toBe(true)
    wrapper.vm.selectDateReference('start')
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toMatchObject({ property_meta: { with_time: true } })
    wrapper.unmount()
  })
})
