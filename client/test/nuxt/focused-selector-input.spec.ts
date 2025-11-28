import { describe, it, expect } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'
import FocusedSelectorInput from '../../components/forms/core/FocusedSelectorInput.vue'

/**
 * Test suite for FocusedSelectorInput Component
 * 
 * Tests actual component behavior:
 * - Component mounting and rendering
 * - Single/multiple selection modes
 * - Keyboard interactions (letters, arrows, enter/space)
 * - Selection constraints (min/max)
 * - Emitted events
 * - Animation and visual states
 * - Object value handling
 */

describe('FocusedSelectorInput Component', () => {
  const mockOptions = [
    { name: 'Option A', value: 'a' },
    { name: 'Option B', value: 'b' },
    { name: 'Option C', value: 'c' }
  ]

  const createWrapper = (props = {}, slots = {}) => {
    return mount(FocusedSelectorInput, {
      props: {
        name: 'test-selector',
        options: mockOptions,
        modelValue: null,
        theme: 'default',
        size: 'md',
        color: '#3B82F6',
        ...props
      },
      slots,
      global: {
        stubs: {
          InputWrapper: {
            template: '<div><slot /><slot name="label" /><slot name="help" /><slot name="bottom_after_help" /><slot name="error" /></div>'
          },
          Icon: true
        },
        provide: {
          form: undefined
        }
      }
    })
  }

  describe('Component Rendering', () => {
    it('should render the component', () => {
      const wrapper = createWrapper()
      expect(wrapper.exists()).toBe(true)
    })

    it('should render all options as buttons with role option', () => {
      const wrapper = createWrapper()
      const options = wrapper.findAll('button[role="option"]')
      expect(options).toHaveLength(3)
    })

    it('should display option labels A, B, C for default labels', () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button[role="option"]')
      
      expect(buttons[0].text()).toContain('A')
      expect(buttons[1].text()).toContain('B')
      expect(buttons[2].text()).toContain('C')
    })

    it('should display custom letters when provided', () => {
      const optionsWithLetters = [
        { name: 'Yes', value: true, letter: 'Y' },
        { name: 'No', value: false, letter: 'N' }
      ]
      
      const wrapper = createWrapper({ options: optionsWithLetters })
      const buttons = wrapper.findAll('button[role="option"]')
      
      expect(buttons[0].text()).toContain('Y')
      expect(buttons[1].text()).toContain('N')
    })

    it('should render listbox container with proper aria attributes', () => {
      const wrapper = createWrapper()
      const container = wrapper.find('[role="listbox"]')
      
      expect(container.exists()).toBe(true)
      expect(container.attributes('aria-multiselectable')).toBe('false')
      expect(container.attributes('tabindex')).toBe('0')
    })
  })

  describe('Single Selection Mode', () => {
    it('should emit update:modelValue when option clicked', async () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button[role="option"]')
      
      await buttons[0].trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['a'])
    })

    it('should show option as selected after prop update', async () => {
      const wrapper = createWrapper({ modelValue: 'a' })
      const buttons = wrapper.findAll('button[role="option"]')
      
      expect(buttons[0].attributes('aria-selected')).toBe('true')
      expect(buttons[1].attributes('aria-selected')).toBe('false')
    })

    it('should emit input-filled after selection animation completes', async () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button[role="option"]')
      
      await buttons[0].trigger('click')
      
      // Wait for animation to complete (300ms)
      await new Promise(resolve => setTimeout(resolve, 350))
      
      expect(wrapper.emitted('input-filled')).toBeTruthy()
    })

    it('should support clearable mode - clicking same option clears it', async () => {
      const wrapper = createWrapper({ modelValue: 'a', clearable: true })
      const buttons = wrapper.findAll('button[role="option"]')
      
      await buttons[0].trigger('click')
      
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([null])
    })
  })

  describe('Multiple Selection Mode', () => {
    it('should allow multiple selections and toggle on second click', async () => {
      const wrapper = createWrapper({ multiple: true, modelValue: [] })
      const buttons = wrapper.findAll('button[role="option"]')
      
      // First click adds
      await buttons[0].trigger('click')
      let emitted = wrapper.emitted('update:modelValue')
      expect(emitted?.[0][0]).toEqual(['a'])
      
      // Second click on same removes
      await buttons[0].trigger('click')
      emitted = wrapper.emitted('update:modelValue')
      expect(emitted?.[1][0]).toEqual([])
    })

    it('should not emit input-filled in multiple mode', async () => {
      const wrapper = createWrapper({ multiple: true, modelValue: [] })
      const buttons = wrapper.findAll('button[role="option"]')
      
      await buttons[0].trigger('click')
      await new Promise(resolve => setTimeout(resolve, 350))
      
      expect(wrapper.emitted('input-filled')).toBeFalsy()
    })

    it('should enforce max selection constraint', async () => {
      const wrapper = createWrapper({ 
        multiple: true, 
        modelValue: ['a', 'b'],
        maxSelection: 2
      })
      const buttons = wrapper.findAll('button[role="option"]')
      
      // Try to select third option - should not work
      await buttons[2].trigger('click')
      
      const emitted = wrapper.emitted('update:modelValue')
      expect(emitted).toBeFalsy()
    })

    it('should enforce min selection constraint on deselection', async () => {
      const wrapper = createWrapper({ 
        multiple: true, 
        modelValue: ['a'],
        minSelection: 1
      })
      const buttons = wrapper.findAll('button[role="option"]')
      
      // Try to deselect the only selected - should not work
      await buttons[0].trigger('click')
      
      const emitted = wrapper.emitted('update:modelValue')
      expect(emitted).toBeFalsy()
    })

    it('should display selection count with constraints', async () => {
      const wrapper = createWrapper({ 
        multiple: true,
        modelValue: ['a', 'b'],
        minSelection: 1,
        maxSelection: 3
      })
      
      expect(wrapper.text()).toContain('2 of 1-3')
    })
  })

  describe('Keyboard Interactions - Letter Shortcuts', () => {
    it('should select option with A key', async () => {
      const wrapper = createWrapper()
      const container = wrapper.find('[role="listbox"]')
      
      const event = new KeyboardEvent('keydown', {
        key: 'A',
        bubbles: true,
        cancelable: true
      })
      
      container.element.dispatchEvent(event)
      await nextTick()
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['a'])
    })

    it('should select option with lowercase a key', async () => {
      const wrapper = createWrapper()
      const container = wrapper.find('[role="listbox"]')
      
      const event = new KeyboardEvent('keydown', {
        key: 'a',
        bubbles: true,
        cancelable: true
      })
      
      container.element.dispatchEvent(event)
      await nextTick()
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
    })

    it('should select option with custom letter', async () => {
      const optionsWithLetters = [
        { name: 'Yes', value: true, letter: 'Y' },
        { name: 'No', value: false, letter: 'N' }
      ]
      
      const wrapper = createWrapper({ options: optionsWithLetters })
      const container = wrapper.find('[role="listbox"]')
      
      const event = new KeyboardEvent('keydown', {
        key: 'Y',
        bubbles: true,
        cancelable: true
      })
      
      container.element.dispatchEvent(event)
      await nextTick()
      
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true])
    })

    it('should prioritize custom letters over default mapping', async () => {
      const optionsWithLetters = [
        { name: 'Apple', value: 'apple', letter: 'X' },
        { name: 'Banana', value: 'banana' }
      ]
      
      const wrapper = createWrapper({ options: optionsWithLetters })
      const container = wrapper.find('[role="listbox"]')
      
      // A should not match (Apple has custom letter X)
      const eventA = new KeyboardEvent('keydown', { key: 'A', bubbles: true, cancelable: true })
      container.element.dispatchEvent(eventA)
      await nextTick()
      
      expect(wrapper.emitted('update:modelValue')).toBeFalsy()
      
      // X should match
      const eventX = new KeyboardEvent('keydown', { key: 'X', bubbles: true, cancelable: true })
      container.element.dispatchEvent(eventX)
      await nextTick()
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['apple'])
    })
  })

  describe('Keyboard Interactions - Arrow Navigation', () => {
    it('should navigate down with ArrowDown', async () => {
      const wrapper = createWrapper()
      const container = wrapper.find('[role="listbox"]')
      
      const event = new KeyboardEvent('keydown', {
        key: 'ArrowDown',
        bubbles: true,
        cancelable: true
      })
      
      container.element.dispatchEvent(event)
      await nextTick()
      
      // Should set focusedIdx to 0 from -1
      expect(wrapper.vm.focusedIdx).toBe(0)
    })

    it('should navigate up with ArrowUp', async () => {
      const wrapper = createWrapper()
      const container = wrapper.find('[role="listbox"]')
      
      const event = new KeyboardEvent('keydown', {
        key: 'ArrowUp',
        bubbles: true,
        cancelable: true
      })
      
      container.element.dispatchEvent(event)
      await nextTick()
      
      // Should set focusedIdx to 2 (last) from -1
      expect(wrapper.vm.focusedIdx).toBe(2)
    })

    it('should wrap around with ArrowDown at end', async () => {
      const wrapper = createWrapper()
      wrapper.vm.focusedIdx = 2
      const container = wrapper.find('[role="listbox"]')
      
      const event = new KeyboardEvent('keydown', {
        key: 'ArrowDown',
        bubbles: true,
        cancelable: true
      })
      
      container.element.dispatchEvent(event)
      await nextTick()
      
      expect(wrapper.vm.focusedIdx).toBe(0)
    })

    it('should emit input-filled when Enter pressed without focus', async () => {
      const wrapper = createWrapper()
      wrapper.vm.focusedIdx = -1
      const container = wrapper.find('[role="listbox"]')
      
      const event = new KeyboardEvent('keydown', {
        key: 'Enter',
        bubbles: true,
        cancelable: true
      })
      
      container.element.dispatchEvent(event)
      await nextTick()
      
      expect(wrapper.emitted('input-filled')).toBeTruthy()
    })

    it('should select focused option when Enter pressed', async () => {
      const wrapper = createWrapper()
      wrapper.vm.focusedIdx = 0
      const container = wrapper.find('[role="listbox"]')
      
      const event = new KeyboardEvent('keydown', {
        key: 'Enter',
        bubbles: true,
        cancelable: true
      })
      
      container.element.dispatchEvent(event)
      await nextTick()
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['a'])
    })
  })

  describe('Disabled State', () => {
    it('should disable all option buttons when component disabled', () => {
      const wrapper = createWrapper({ disabled: true })
      const buttons = wrapper.findAll('button[role="option"]')
      
      buttons.forEach(button => {
        expect(button.attributes('disabled')).toBeDefined()
      })
    })

    it('should disable individual options', () => {
      const optionsWithDisabled = [
        { name: 'Option A', value: 'a', disabled: false },
        { name: 'Option B', value: 'b', disabled: true }
      ]
      
      const wrapper = createWrapper({ options: optionsWithDisabled })
      const buttons = wrapper.findAll('button[role="option"]')
      
      expect(buttons[0].attributes('disabled')).toBeUndefined()
      expect(buttons[1].attributes('disabled')).toBeDefined()
    })
  })

  describe('Object Value Handling', () => {
    it('should use emitKey for emitted values', async () => {
      const objectOptions = [
        { id: 1, code: 'CODE1' },
        { id: 2, code: 'CODE2' }
      ]
      
      const wrapper = createWrapper({ 
        options: objectOptions,
        optionKey: 'id',
        emitKey: 'code'
      })
      
      const buttons = wrapper.findAll('button[role="option"]')
      await buttons[0].trigger('click')
      
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual(['CODE1'])
    })

    it('should use displayKey for displayed text', () => {
      const objectOptions = [
        { id: 1, label: 'First Label' },
        { id: 2, label: 'Second Label' }
      ]
      
      const wrapper = createWrapper({ 
        options: objectOptions,
        optionKey: 'id',
        displayKey: 'label'
      })
      
      expect(wrapper.text()).toContain('First Label')
      expect(wrapper.text()).toContain('Second Label')
    })
  })

  describe('Animation States', () => {
    it('should apply animation on selection', async () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button[role="option"]')
      
      await buttons[0].trigger('click')
      await nextTick()
      
      expect(wrapper.vm.animatingOption).toBe('a')
    })

    it('should clear animation after timeout', async () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button[role="option"]')
      
      await buttons[0].trigger('click')
      
      // Wait for animation to complete
      await new Promise(resolve => setTimeout(resolve, 350))
      await nextTick()
      
      expect(wrapper.vm.animatingOption).toBeNull()
    })
  })

  describe('Value Change Resets Focus', () => {
    it('should reset focusedIdx when value changes', async () => {
      const wrapper = createWrapper()
      
      wrapper.vm.focusedIdx = 1
      
      await wrapper.setProps({ modelValue: 'a' })
      
      expect(wrapper.vm.focusedIdx).toBe(-1)
    })
  })
})
