import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import FormErrorModal from '~/components/open/forms/components/form-components/FormErrorModal.vue'

const fieldError = {
  key: 'property:2',
  fieldIndex: 2,
  fieldId: 'pride-list',
  fieldName: 'siehe Pride-Liste',
  fieldType: 'select',
  messages: ['At least one option is required.'],
  targetTab: 'options',
  canNavigate: true,
}

const computedVariableError = {
  key: 'computed-variable:1',
  variableIndex: 1,
  variableId: 'cv_tax',
  variableName: 'Tax',
  messages: ['The formula is required.'],
  canNavigate: true,
}

function createWrapper(errorSummary = {}) {
  return mount(FormErrorModal, {
    props: {
      show: true,
      errorSummary: {
        fieldGroups: [],
        computedVariableGroups: [],
        generalErrors: [],
        issueCount: 0,
        fieldCount: 0,
        computedVariableCount: 0,
        fallbackMessage: null,
        ...errorSummary,
      },
    },
    global: {
      stubs: {
        UModal: {
          props: ['open', 'title', 'ui'],
          emits: ['update:open'],
          template: '<div class="modal"><h2>{{ title }}</h2><slot name="body" /><slot name="footer" /></div>',
        },
        UButton: {
          emits: ['click'],
          template: '<button @click="$emit(\'click\')"><slot /></button>',
        },
        Icon: {
          template: '<span />',
        },
      },
    },
  })
}

describe('FormErrorModal', () => {
  it('identifies a single invalid field without showing aggregate API errors', () => {
    const wrapper = createWrapper({
      fieldGroups: [fieldError],
      issueCount: 1,
      fieldCount: 1,
    })

    expect(wrapper.get('h2').text()).toBe('Fix 1 field before saving')
    expect(wrapper.text()).toContain('siehe Pride-Liste')
    expect(wrapper.text()).toContain('Select')
    expect(wrapper.text()).toContain('At least one option is required.')
    expect(wrapper.text()).not.toContain('One or more properties have validation errors.')
    expect(wrapper.text()).not.toContain('and 1 more error')
  })

  it('uses a plural field count when multiple fields are invalid', () => {
    const wrapper = createWrapper({
      fieldGroups: [
        fieldError,
        {
          ...fieldError,
          key: 'property:3',
          fieldIndex: 3,
          fieldId: 'email',
          fieldName: 'Email',
          fieldType: 'email',
        },
      ],
      issueCount: 2,
      fieldCount: 2,
    })

    expect(wrapper.get('h2').text()).toBe('Fix 2 fields before saving')
  })

  it('emits the selected field when Edit field is clicked', async () => {
    const wrapper = createWrapper({
      fieldGroups: [fieldError],
      issueCount: 1,
      fieldCount: 1,
    })

    const editButton = wrapper.get('[data-testid="edit-form-field-pride-list"]')
    expect(editButton.attributes('aria-label')).toBe('Edit siehe Pride-Liste')

    await editButton.trigger('click')

    expect(wrapper.emitted('edit-field')).toEqual([[fieldError]])
  })

  it('shows field and general errors as separate issues', () => {
    const wrapper = createWrapper({
      fieldGroups: [fieldError],
      generalErrors: [{
        key: 'title',
        label: 'Title',
        messages: ['The title field is required.'],
      }],
      issueCount: 2,
      fieldCount: 1,
    })

    expect(wrapper.get('h2').text()).toBe('Fix 2 issues before saving')
    expect(wrapper.get('[data-testid="form-save-general-errors"]').text())
      .toContain('The title field is required.')
  })

  it('identifies a computed variable and emits it for editing', async () => {
    const wrapper = createWrapper({
      computedVariableGroups: [computedVariableError],
      issueCount: 1,
      computedVariableCount: 1,
    })

    expect(wrapper.get('h2').text()).toBe('Fix 1 variable before saving')
    expect(wrapper.text()).toContain('Tax')
    expect(wrapper.text()).toContain('The formula is required.')

    const editButton = wrapper.get('[data-testid="edit-computed-variable-cv_tax"]')
    expect(editButton.attributes('aria-label')).toBe('Edit Tax')

    await editButton.trigger('click')

    expect(wrapper.emitted('edit-computed-variable')).toEqual([[computedVariableError]])
  })

  it('shows a safe fallback when no structured errors are available', () => {
    const wrapper = createWrapper({
      fallbackMessage: 'Unable to validate the form.',
    })

    expect(wrapper.get('h2').text()).toBe("We couldn't save your form")
    expect(wrapper.text()).toContain('Unable to validate the form.')
  })

  it('exposes the error summary as an assertive alert', () => {
    const wrapper = createWrapper({
      fieldGroups: [fieldError],
      issueCount: 1,
      fieldCount: 1,
    })

    const alert = wrapper.get('[data-testid="form-save-error-modal"]')
    expect(alert.attributes('role')).toBe('alert')
    expect(alert.attributes('aria-live')).toBe('assertive')
  })
})
