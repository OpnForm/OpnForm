import { describe, it, expect, vi } from 'vitest'
import { ref } from 'vue'
import { FormMode, createFormModeStrategy } from '~/lib/forms/FormModeStrategy'
import { useFieldState } from '~/lib/forms/composables/useFieldState'

/**
 * BlockRenderer label computation tests - focuses on the core label logic
 * without the overhead of component mounting
 * 
 * Tests validate that BlockRenderer correctly computes labels based on:
 * 1. Field hidden status
 * 2. Form mode (LIVE, READ_ONLY, EDIT, etc)
 * 3. Strategy's showHiddenFields flag
 */
describe('BlockRenderer - Label Computation Logic', () => {
  /**
   * Simulate BlockRenderer's boundProps.label computation
   * This is the actual logic from BlockRenderer.vue line 145
   */
  function computeLabel(field, fieldState) {
    if (field.hide_field_name) {
      return null
    }
    return field.name + (fieldState.hiddenIndicator ? ' (Hidden Field)' : '')
  }

  describe('LIVE mode - Public form submission', () => {
    it('hidden field renders label WITHOUT "(Hidden Field)" indicator', () => {
      const mockConfig = ref({
        properties: [
          { id: 'secret', name: 'Secret Data', hidden: true },
        ],
      })
      const mockData = ref({ secret: 'value' })
      const strategy = createFormModeStrategy(FormMode.LIVE)
      const fieldState = useFieldState(mockData, mockConfig, ref(strategy))

      const field = mockConfig.value.properties[0]
      const state = fieldState.getState(field)
      const label = computeLabel(field, state)

      // In LIVE mode, hidden fields show name WITHOUT indicator
      expect(label).toBe('Secret Data')
      expect(label).not.toContain('(Hidden Field)')
    })

    it('visible field renders label WITHOUT indicator in LIVE mode', () => {
      const mockConfig = ref({
        properties: [
          { id: 'name', name: 'Full Name', hidden: false },
        ],
      })
      const mockData = ref({})
      const strategy = createFormModeStrategy(FormMode.LIVE)
      const fieldState = useFieldState(mockData, mockConfig, ref(strategy))

      const field = mockConfig.value.properties[0]
      const state = fieldState.getState(field)
      const label = computeLabel(field, state)

      expect(label).toBe('Full Name')
      expect(label).not.toContain('(Hidden Field)')
    })
  })

  describe('READ_ONLY mode - ViewSubmissionModal', () => {
    it('hidden field renders label WITH "(Hidden Field)" indicator', () => {
      const mockConfig = ref({
        properties: [
          { id: 'secret', name: 'Internal ID', hidden: true },
        ],
      })
      const mockData = ref({})
      const strategy = createFormModeStrategy(FormMode.READ_ONLY)
      const fieldState = useFieldState(mockData, mockConfig, ref(strategy))

      const field = mockConfig.value.properties[0]
      const state = fieldState.getState(field)
      const label = computeLabel(field, state)

      // In READ_ONLY mode, hidden fields show indicator
      expect(label).toBe('Internal ID (Hidden Field)')
      expect(label).toContain('(Hidden Field)')
    })

    it('visible field renders label WITHOUT indicator in READ_ONLY mode', () => {
      const mockConfig = ref({
        properties: [
          { id: 'name', name: 'User Name', hidden: false },
        ],
      })
      const mockData = ref({})
      const strategy = createFormModeStrategy(FormMode.READ_ONLY)
      const fieldState = useFieldState(mockData, mockConfig, ref(strategy))

      const field = mockConfig.value.properties[0]
      const state = fieldState.getState(field)
      const label = computeLabel(field, state)

      expect(label).toBe('User Name')
      expect(label).not.toContain('(Hidden Field)')
    })
  })

  describe('EDIT mode - EditSubmissionModal', () => {
    it('hidden field renders label WITH "(Hidden Field)" indicator', () => {
      const mockConfig = ref({
        properties: [
          { id: 'ref', name: 'Reference Number', hidden: true },
        ],
      })
      const mockData = ref({})
      const strategy = createFormModeStrategy(FormMode.EDIT)
      const fieldState = useFieldState(mockData, mockConfig, ref(strategy))

      const field = mockConfig.value.properties[0]
      const state = fieldState.getState(field)
      const label = computeLabel(field, state)

      // In EDIT mode, hidden fields show indicator
      expect(label).toBe('Reference Number (Hidden Field)')
      expect(label).toContain('(Hidden Field)')
    })

    it('respects hide_field_name flag - returns null when true', () => {
      const field = {
        id: 'secret',
        name: 'Secret Field',
        hidden: true,
        hide_field_name: true, // This should hide the label
      }
      
      const mockConfig = ref({ properties: [field] })
      const mockData = ref({})
      const strategy = createFormModeStrategy(FormMode.READ_ONLY)
      const fieldState = useFieldState(mockData, mockConfig, ref(strategy))

      const state = fieldState.getState(field)
      const label = computeLabel(field, state)

      // When hide_field_name is true, no label is generated
      expect(label).toBe(null)
    })
  })

  describe('Field state hiddenIndicator flag control', () => {
    it('LIVE mode sets hiddenIndicator = false for hidden fields', () => {
      const mockConfig = ref({
        properties: [{ id: 'x', name: 'Test', hidden: true }],
      })
      const mockData = ref({})
      const strategy = createFormModeStrategy(FormMode.LIVE)
      const fieldState = useFieldState(mockData, mockConfig, ref(strategy))

      const state = fieldState.getState(mockConfig.value.properties[0])
      expect(state.hiddenIndicator).toBe(false)
    })

    it('READ_ONLY mode sets hiddenIndicator = true for hidden fields', () => {
      const mockConfig = ref({
        properties: [{ id: 'x', name: 'Test', hidden: true }],
      })
      const mockData = ref({})
      const strategy = createFormModeStrategy(FormMode.READ_ONLY)
      const fieldState = useFieldState(mockData, mockConfig, ref(strategy))

      const state = fieldState.getState(mockConfig.value.properties[0])
      expect(state.hiddenIndicator).toBe(true)
    })

    it('EDIT mode sets hiddenIndicator = true for hidden fields', () => {
      const mockConfig = ref({
        properties: [{ id: 'x', name: 'Test', hidden: true }],
      })
      const mockData = ref({})
      const strategy = createFormModeStrategy(FormMode.EDIT)
      const fieldState = useFieldState(mockData, mockConfig, ref(strategy))

      const state = fieldState.getState(mockConfig.value.properties[0])
      expect(state.hiddenIndicator).toBe(true)
    })

    it('never sets hiddenIndicator for visible fields', () => {
      const mockConfig = ref({
        properties: [{ id: 'x', name: 'Test', hidden: false }],
      })
      const mockData = ref({})

      ;[FormMode.LIVE, FormMode.READ_ONLY, FormMode.EDIT].forEach(mode => {
        const strategy = createFormModeStrategy(mode)
        const fieldState = useFieldState(mockData, mockConfig, ref(strategy))
        const state = fieldState.getState(mockConfig.value.properties[0])

        expect(state.hiddenIndicator).toBe(false)
      })
    })
  })
})
