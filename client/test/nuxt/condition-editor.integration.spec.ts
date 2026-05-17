import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, nextTick } from 'vue'
import { describe, expect, it } from 'vitest'
import ConditionEditor from '../../components/open/forms/components/form-logic-components/ConditionEditor.client.vue'
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
  setup(props, { emit }) {
    function insertBudgetMention() {
      const field = props.mentions[0]
      emit('update:modelValue', `<span mention="true" mention-field-id="${field.id}" mention-field-name="${field.name}" mention-fallback="">@${field.name}</span>`)
    }

    return {
      insertBudgetMention,
    }
  },
  template: '<button type="button" data-test="insert-mention" @click="insertBudgetMention">Insert mention</button>',
})

function mountConditionEditor() {
  ;(ColumnCondition as any).components.MentionInput = mentionInputStub

  return mount(ConditionEditor, {
    props: {
      form: {
        workspace_id: 1,
        slug: 'guest-form',
        properties: [budgetField, emailField],
        computed_variables: [totalVariable, taxVariable],
      },
      modelValue: {
        operatorIdentifier: 'and',
        children: [
          {
            identifier: 'cv_total',
            value: {
              operator: 'greater_than',
              value: null,
            },
          },
        ],
      },
    },
    global: {
      stubs: {
        USelectMenu: {
          template: '<div data-test="select-menu" />',
          props: ['modelValue', 'items'],
        },
        UDropdownMenu: {
          template: '<div><slot /></div>',
        },
        UButton: {
          template: '<button type="button"><slot /></button>',
        },
        Icon: true,
        MentionInput: mentionInputStub,
      },
    },
  })
}

describe('ConditionEditor computed mentions integration', () => {
  it('lets computed variable rules author mention values', async () => {
    const wrapper = mountConditionEditor()
    await flushPromises()

    const mentionInput = wrapper.findComponent({ name: 'MentionInput' })
    expect(mentionInput.exists()).toBe(true)
    expect(mentionInput.props('mentions')).toEqual([budgetField, emailField])
    expect(mentionInput.props('computedVariables')).toEqual([taxVariable])

    await wrapper.find('[data-test="insert-mention"]').trigger('click')
    await nextTick()

    const emitted = wrapper.emitted('update:modelValue')
    expect(emitted).toBeTruthy()

    const lastQuery = emitted?.at(-1)?.[0]
    expect(lastQuery.children[0].identifier).toBe('cv_total')
    expect(lastQuery.children[0].value.value).toContain('mention-field-id="budget"')
  })
})
