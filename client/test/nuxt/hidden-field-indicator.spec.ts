// @vitest-environment node
import { describe, it, expect } from 'vitest'
import { ref } from 'vue'
import { FormMode, createFormModeStrategy } from '~/lib/forms/FormModeStrategy'
import { useFieldState } from '~/lib/forms/composables/useFieldState'

/**
 * Consolidated test suite for hidden field indicator behavior.
 * 
 * Tests that:
 * - Hidden fields display "(Hidden Field)" indicator ONLY in admin contexts (READ_ONLY, EDIT)
 * - Hidden fields are hidden from public users (LIVE mode)
 * - Field state correctly computes hiddenIndicator based on form mode
 * - Label generation works correctly across all scenarios
 * - Edge cases and consistency are maintained
 */
describe('Hidden Field Indicator', () => {
  const mockFormConfig = ref({
    id: 1,
    name: 'Test Form',
    properties: [
      {
        id: 'field-1',
        name: 'Regular Field',
        type: 'text',
        hidden: false,
        required: false,
        hide_field_name: false,
      },
      {
        id: 'field-2',
        name: 'Hidden Field',
        type: 'text',
        hidden: true,
        required: false,
        hide_field_name: false,
      },
    ],
    color: '#3b82f6',
    presentation_style: 'classic',
  })

  const mockFormData = ref({
    'field-1': 'value1',
    'field-2': 'value2',
  })

  function generateLabel(field, hiddenIndicator) {
    if (field.hide_field_name) {
      return null
    }
    return field.name + (hiddenIndicator ? ' (Hidden Field)' : '')
  }

  describe('Strategy flag validation', () => {
    it('LIVE mode should have showHiddenFields = false', () => {
      const strategy = createFormModeStrategy(FormMode.LIVE)
      expect(strategy.display.showHiddenFields).toBe(false)
    })

    it('READ_ONLY mode should have showHiddenFields = true', () => {
      const strategy = createFormModeStrategy(FormMode.READ_ONLY)
      expect(strategy.display.showHiddenFields).toBe(true)
    })

    it('EDIT mode should have showHiddenFields = true', () => {
      const strategy = createFormModeStrategy(FormMode.EDIT)
      expect(strategy.display.showHiddenFields).toBe(true)
    })

    it('PREFILL mode should have showHiddenFields = true', () => {
      const strategy = createFormModeStrategy(FormMode.PREFILL)
      expect(strategy.display.showHiddenFields).toBe(true)
    })

    it('TEST, PREVIEW, TEMPLATE modes set showHiddenFields = false', () => {
      const testModes = [FormMode.TEST, FormMode.PREVIEW, FormMode.TEMPLATE]
      testModes.forEach(mode => {
        const strategy = createFormModeStrategy(mode)
        expect(strategy.display.showHiddenFields).toBe(false)
      })
    })
  })

  describe('Field state hiddenIndicator computation', () => {
    it('LIVE mode: hidden fields do NOT show indicator', () => {
      const strategy = createFormModeStrategy(FormMode.LIVE)
      const fieldState = useFieldState(mockFormData, mockFormConfig, ref(strategy))
      const hiddenField = mockFormConfig.value.properties[1]
      const state = fieldState.getState(hiddenField)

      expect(state.hiddenIndicator).toBe(false)
    })

    it('READ_ONLY mode: hidden fields DO show indicator', () => {
      const strategy = createFormModeStrategy(FormMode.READ_ONLY)
      const fieldState = useFieldState(mockFormData, mockFormConfig, ref(strategy))
      const hiddenField = mockFormConfig.value.properties[1]
      const state = fieldState.getState(hiddenField)

      expect(state.hiddenIndicator).toBe(true)
    })

    it('EDIT mode: hidden fields DO show indicator', () => {
      const strategy = createFormModeStrategy(FormMode.EDIT)
      const fieldState = useFieldState(mockFormData, mockFormConfig, ref(strategy))
      const hiddenField = mockFormConfig.value.properties[1]
      const state = fieldState.getState(hiddenField)

      expect(state.hiddenIndicator).toBe(true)
    })

    it('visible fields never show indicator regardless of mode', () => {
      const visibleField = mockFormConfig.value.properties[0]

      ;[FormMode.LIVE, FormMode.READ_ONLY, FormMode.EDIT, FormMode.TEST, FormMode.PREVIEW].forEach(mode => {
        const strategy = createFormModeStrategy(mode)
        const fieldState = useFieldState(mockFormData, mockFormConfig, ref(strategy))
        const state = fieldState.getState(visibleField)

        expect(state.hiddenIndicator).toBe(false)
      })
    })
  })

  describe('Label generation logic', () => {
    it('LIVE mode: hidden field label without indicator', () => {
      const hiddenField = mockFormConfig.value.properties[1]
      const strategy = createFormModeStrategy(FormMode.LIVE)
      const fieldState = useFieldState(mockFormData, mockFormConfig, ref(strategy))
      const state = fieldState.getState(hiddenField)
      const label = generateLabel(hiddenField, state.hiddenIndicator)

      expect(label).toBe('Hidden Field')
      expect(label).not.toContain('(Hidden Field)')
    })

    it('READ_ONLY mode: hidden field label with indicator', () => {
      const hiddenField = mockFormConfig.value.properties[1]
      const strategy = createFormModeStrategy(FormMode.READ_ONLY)
      const fieldState = useFieldState(mockFormData, mockFormConfig, ref(strategy))
      const state = fieldState.getState(hiddenField)
      const label = generateLabel(hiddenField, state.hiddenIndicator)

      expect(label).toBe('Hidden Field (Hidden Field)')
      expect(label).toContain('(Hidden Field)')
    })

    it('EDIT mode: hidden field label with indicator', () => {
      const hiddenField = mockFormConfig.value.properties[1]
      const strategy = createFormModeStrategy(FormMode.EDIT)
      const fieldState = useFieldState(mockFormData, mockFormConfig, ref(strategy))
      const state = fieldState.getState(hiddenField)
      const label = generateLabel(hiddenField, state.hiddenIndicator)

      expect(label).toBe('Hidden Field (Hidden Field)')
      expect(label).toContain('(Hidden Field)')
    })

    it('respects hide_field_name flag regardless of mode', () => {
      const hiddenField = { ...mockFormConfig.value.properties[1], hide_field_name: true }

      ;[FormMode.LIVE, FormMode.READ_ONLY, FormMode.EDIT].forEach(mode => {
        const strategy = createFormModeStrategy(mode)
        const fieldState = useFieldState(mockFormData, mockFormConfig, ref(strategy))
        const state = fieldState.getState(hiddenField)
        const label = generateLabel(hiddenField, state.hiddenIndicator)

        expect(label).toBe(null)
      })
    })
  })

  describe('Edge cases', () => {
    it('handles multiple hidden fields correctly', () => {
      const multiFieldConfig = ref({
        properties: [
          { id: '1', hidden: true, name: 'Hidden 1' },
          { id: '2', hidden: true, name: 'Hidden 2' },
          { id: '3', hidden: false, name: 'Visible' },
          { id: '4', hidden: true, name: 'Hidden 3' },
        ],
      })
      const formData = ref({})
      const strategy = createFormModeStrategy(FormMode.READ_ONLY)
      const fieldState = useFieldState(formData, multiFieldConfig, ref(strategy))

      // All hidden fields should get indicator
      ;[0, 1, 3].forEach(index => {
        const state = fieldState.getState(multiFieldConfig.value.properties[index])
        expect(state.hiddenIndicator).toBe(true)
      })

      // Visible field should not
      const visibleState = fieldState.getState(multiFieldConfig.value.properties[2])
      expect(visibleState.hiddenIndicator).toBe(false)
    })

    it('field state consistency across multiple calls', () => {
      const formConfig = ref({
        properties: [{ id: '1', hidden: true, name: 'Hidden' }],
      })
      const formData = ref({})
      const strategy = createFormModeStrategy(FormMode.READ_ONLY)
      const fieldState = useFieldState(formData, formConfig, ref(strategy))

      const field = formConfig.value.properties[0]
      const state1 = fieldState.getState(field)
      const state2 = fieldState.getState(field)
      const state3 = fieldState.getState(field)

      expect(state1.hiddenIndicator).toBe(true)
      expect(state2.hiddenIndicator).toBe(true)
      expect(state3.hiddenIndicator).toBe(true)
      expect(state1).toEqual(state2)
      expect(state2).toEqual(state3)
    })
  })

  describe('Integration: Real-world scenarios', () => {
    it('ViewSubmissionModal: admin views submission in READ_ONLY mode', () => {
      const formConfig = ref({
        id: 'form-1',
        properties: [
          { id: 'name', hidden: false, name: 'Full Name' },
          { id: 'secret', hidden: true, name: 'Secret ID' },
          { id: 'email', hidden: false, name: 'Email' },
        ],
      })
      const formData = ref({
        name: 'John Doe',
        secret: 'XYZ123',
        email: 'john@example.com',
      })
      const strategy = createFormModeStrategy(FormMode.READ_ONLY)
      const fieldState = useFieldState(formData, formConfig, ref(strategy))

      const nameState = fieldState.getState(formConfig.value.properties[0])
      const secretState = fieldState.getState(formConfig.value.properties[1])
      const emailState = fieldState.getState(formConfig.value.properties[2])

      expect(nameState.hiddenIndicator).toBe(false)
      expect(emailState.hiddenIndicator).toBe(false)
      expect(secretState.hiddenIndicator).toBe(true)
      expect(strategy.display.disableFields).toBe(true)
    })

    it('EditSubmissionModal: admin edits submission in EDIT mode', () => {
      const formConfig = ref({
        id: 'form-1',
        properties: [
          { id: 'name', hidden: false, name: 'Full Name' },
          { id: 'internal', hidden: true, name: 'Internal Reference' },
        ],
      })
      const formData = ref({
        name: 'Jane Smith',
        internal: 'REF-2024-001',
      })
      const strategy = createFormModeStrategy(FormMode.EDIT)
      const fieldState = useFieldState(formData, formConfig, ref(strategy))

      const internalState = fieldState.getState(formConfig.value.properties[1])

      expect(internalState.hiddenIndicator).toBe(true)
      expect(strategy.display.forceClassicPresentation).toBe(true)
    })

    it('public form: user sees no hidden field indicator in LIVE mode', () => {
      const formConfig = ref({
        id: 'form-1',
        properties: [
          { id: 'name', hidden: false, name: 'Name' },
          { id: 'promo', hidden: true, name: 'Promo Code' },
        ],
      })
      const formData = ref({})
      const strategy = createFormModeStrategy(FormMode.LIVE)
      const fieldState = useFieldState(formData, formConfig, ref(strategy))

      const promoState = fieldState.getState(formConfig.value.properties[1])

      expect(promoState.hiddenIndicator).toBe(false)
      expect(strategy.display.showHiddenFields).toBe(false)
    })
  })
})
