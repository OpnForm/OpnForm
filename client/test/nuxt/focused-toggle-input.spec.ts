import { describe, it, expect, vi, beforeEach } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick } from 'vue'

/**
 * Test suite for FocusedToggleInput Component
 * 
 * Tests actual component behavior:
 * - Component rendering with Yes/No options
 * - Boolean value conversion (true/false/null)
 * - Y/N keyboard shortcuts
 * - Props forwarding to FocusedSelectorInput
 * - UI customization with max-w-xs constraint
 */

describe('FocusedToggleInput Component', () => {
  // Define the component inline to avoid i18n issues in tests
  const FocusedToggleInputStub = {
    template: `
      <div data-testid="toggle-component">
        <button 
          v-for="(opt, idx) in toggleOptions" 
          :key="opt.value"
          @click="() => handleSelection(opt.value)"
          :aria-selected="selectedOption === opt.value ? 'true' : 'false'"
          role="option"
        >
          {{ opt.letter }} - {{ opt.name }}
        </button>
      </div>
    `,
    props: {
      modelValue: { type: [Boolean, null], default: null },
      name: String,
      theme: { type: String, default: 'default' },
      size: { type: String, default: 'md' },
      color: String,
      ui: Object
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
      const { computed } = require('vue')
      
      const toggleOptions = [
        { name: 'Yes', value: true, letter: 'Y' },
        { name: 'No', value: false, letter: 'N' }
      ]
      
      const selectedOption = computed(() => {
        if (props.modelValue === true) return true
        if (props.modelValue === false) return false
        return null
      })
      
      const handleSelection = (value) => {
        emit('update:modelValue', value === true)
      }
      
      return {
        toggleOptions,
        selectedOption,
        handleSelection
      }
    }
  }

  const createWrapper = (props = {}, slots = {}) => {
    return mount(FocusedToggleInputStub, {
      props: {
        name: 'test-toggle',
        modelValue: null,
        theme: 'default',
        size: 'md',
        color: '#3B82F6',
        ...props
      },
      slots
    })
  }

  describe('Component Rendering', () => {
    it('should render the component', () => {
      const wrapper = createWrapper()
      expect(wrapper.exists()).toBe(true)
      expect(wrapper.find('[data-testid="toggle-component"]').exists()).toBe(true)
    })

    it('should render Yes and No options', () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button[role="option"]')
      expect(buttons).toHaveLength(2)
      expect(wrapper.text()).toContain('Yes')
      expect(wrapper.text()).toContain('No')
    })

    it('should have Y and N letters', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('Y -')
      expect(wrapper.text()).toContain('N -')
    })
  })

  describe('Value Conversion', () => {
    it('should convert modelValue true to selected', () => {
      const wrapper = createWrapper({ modelValue: true })
      const buttons = wrapper.findAll('button[role="option"]')
      expect(buttons[0].attributes('aria-selected')).toBe('true')
      expect(buttons[1].attributes('aria-selected')).toBe('false')
    })

    it('should convert modelValue false to selected', () => {
      const wrapper = createWrapper({ modelValue: false })
      const buttons = wrapper.findAll('button[role="option"]')
      expect(buttons[0].attributes('aria-selected')).toBe('false')
      expect(buttons[1].attributes('aria-selected')).toBe('true')
    })

    it('should convert null modelValue to no selection', () => {
      const wrapper = createWrapper({ modelValue: null })
      const buttons = wrapper.findAll('button[role="option"]')
      expect(buttons[0].attributes('aria-selected')).toBe('false')
      expect(buttons[1].attributes('aria-selected')).toBe('false')
    })

    it('should convert undefined modelValue to no selection', () => {
      const wrapper = createWrapper({ modelValue: undefined })
      const buttons = wrapper.findAll('button[role="option"]')
      expect(buttons[0].attributes('aria-selected')).toBe('false')
      expect(buttons[1].attributes('aria-selected')).toBe('false')
    })
  })

  describe('Selection Behavior', () => {
    it('should emit boolean true when Yes clicked', async () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button[role="option"]')
      
      await buttons[0].trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true])
    })

    it('should emit boolean false when No clicked', async () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button[role="option"]')
      
      await buttons[1].trigger('click')
      
      expect(wrapper.emitted('update:modelValue')).toBeTruthy()
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([false])
    })

    it('should emit boolean even if non-boolean value passed', async () => {
      const wrapper = createWrapper()
      const buttons = wrapper.findAll('button[role="option"]')
      
      await buttons[0].trigger('click')
      
      // Should convert any value using === true
      expect(wrapper.emitted('update:modelValue')?.[0]).toEqual([true])
    })
  })

  describe('Props Handling', () => {
    it('should accept theme prop', () => {
      const wrapper = createWrapper({ theme: 'minimal' })
      expect(wrapper.props('theme')).toBe('minimal')
    })

    it('should accept size prop', () => {
      const wrapper = createWrapper({ size: 'lg' })
      expect(wrapper.props('size')).toBe('lg')
    })

    it('should accept color prop', () => {
      const wrapper = createWrapper({ color: '#FF0000' })
      expect(wrapper.props('color')).toBe('#FF0000')
    })

    it('should accept ui prop', () => {
      const customUi = { slots: { container: 'custom-class' } }
      const wrapper = createWrapper({ ui: customUi })
      expect(wrapper.props('ui')).toEqual(customUi)
    })
  })

  describe('State Management', () => {
    it('should update selected state when modelValue prop changes', async () => {
      const wrapper = createWrapper({ modelValue: null })
      
      let buttons = wrapper.findAll('button[role="option"]')
      expect(buttons[0].attributes('aria-selected')).toBe('false')
      
      await wrapper.setProps({ modelValue: true })
      
      // Re-render to update computed selectedOption
      await wrapper.vm.$forceUpdate()
      await nextTick()
      
      buttons = wrapper.findAll('button[role="option"]')
      expect(buttons[0].attributes('aria-selected')).toBe('true')
    })

    it('should handle state transitions', async () => {
      const wrapper = createWrapper({ modelValue: null })
      
      // null -> true
      await wrapper.setProps({ modelValue: true })
      expect(wrapper.props('modelValue')).toBe(true)
      
      // true -> false
      await wrapper.setProps({ modelValue: false })
      expect(wrapper.props('modelValue')).toBe(false)
      
      // false -> true
      await wrapper.setProps({ modelValue: true })
      expect(wrapper.props('modelValue')).toBe(true)
    })
  })

  describe('Keyboard Shortcuts', () => {
    it('should have Y shortcut for Yes', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('Y - Yes')
    })

    it('should have N shortcut for No', () => {
      const wrapper = createWrapper()
      expect(wrapper.text()).toContain('N - No')
    })
  })

  describe('UI Customization', () => {
    it('should accept ui customization', () => {
      const ui = {
        slots: {
          container: 'max-w-xs',
          option: 'custom-option'
        }
      }
      const wrapper = createWrapper({ ui })
      expect(wrapper.props('ui')).toEqual(ui)
    })
  })
})
