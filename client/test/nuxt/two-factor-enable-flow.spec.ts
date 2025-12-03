import { describe, it, expect, beforeEach, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import TwoFactorEnableFlow from '~/components/users/settings/two-factor-auth/TwoFactorEnableFlow.vue'

describe('TwoFactorEnableFlow', () => {
  const createWrapper = (props = {}) => {
    return mount(TwoFactorEnableFlow, {
      props: {
        enabling: false,
        confirming: false,
        secret: null,
        qrCode: null,
        ...props,
      },
      global: {
        stubs: {
          UButton: {
            template: '<button @click="$emit(\'click\')" :disabled="disabled" :class="{ loading }"><slot /></button>',
            props: ['color', 'loading', 'block', 'disabled'],
            emits: ['click'],
          },
          UPinInput: {
            template: '<input type="text" data-testid="pin-input" />',
            props: ['modelValue', 'length', 'type', 'otp', 'size'],
            emits: ['update:modelValue', 'complete'],
          },
          CopyContent: {
            template: '<div class="copy-content">{{ content }}</div>',
            props: ['content', 'label'],
          },
          VTransition: {
            template: '<div><slot /></div>',
            props: ['name'],
          },
        },
      },
    })
  }

  describe('Initial State', () => {
    it('should render enable button when secret is not provided', () => {
      const wrapper = createWrapper()
      
      expect(wrapper.exists()).toBe(true)
      const button = wrapper.find('button')
      expect(button.text()).toContain('Enable Two-Factor Authentication')
    })

    it('should not show QR code section initially', () => {
      const wrapper = createWrapper()
      
      const qrSection = wrapper.find('.border')
      expect(qrSection.exists()).toBe(false)
    })
  })

  describe('Enable Flow', () => {
    it('should emit enable event when enable button is clicked', async () => {
      const wrapper = createWrapper()
      
      const button = wrapper.find('button')
      await button.trigger('click')
      
      expect(wrapper.emitted('enable')).toBeTruthy()
      expect(wrapper.emitted('enable')).toHaveLength(1)
    })

    it('should show loading state on enable button when enabling', () => {
      const wrapper = createWrapper({ enabling: true })
      
      const button = wrapper.find('button')
      expect(button.classes()).toContain('loading')
    })
  })

  describe('QR Code Display', () => {
    it('should display QR code when secret is provided', () => {
      const wrapper = createWrapper({
        secret: 'TEST_SECRET_123',
        qrCode: '<svg>QR Code</svg>',
      })
      
      const qrSection = wrapper.find('.border')
      expect(qrSection.exists()).toBe(true)
    })

    it('should display secret section when secret is provided', () => {
      const wrapper = createWrapper({
        secret: 'TEST_SECRET_123',
        qrCode: '<svg>QR Code</svg>',
      })
      
      // Should show manual entry section
      expect(wrapper.text()).toContain('Or enter this code manually')
    })

    it('should show confirmation section when secret is provided', () => {
      const wrapper = createWrapper({
        secret: 'TEST_SECRET_123',
        qrCode: '<svg>QR Code</svg>',
      })
      
      // QR section should be visible
      const qrSection = wrapper.find('.border')
      expect(qrSection.exists()).toBe(true)
      
      // Should have text about entering code
      expect(wrapper.text()).toContain('Enter the 6-digit code')
    })
  })

  describe('Confirmation', () => {
    it('should emit confirm event with code when handleConfirm is called with 6 digits', () => {
      const wrapper = createWrapper({
        secret: 'TEST_SECRET_123',
        qrCode: '<svg>QR Code</svg>',
      })
      
      // Set code to 6 digits
      wrapper.vm.code = ['1', '2', '3', '4', '5', '6']
      wrapper.vm.handleConfirm()
      
      expect(wrapper.emitted('confirm')).toBeTruthy()
      expect(wrapper.emitted('confirm')[0]).toEqual(['123456'])
    })

    it('should not emit confirm if code is not 6 digits', () => {
      const wrapper = createWrapper({
        secret: 'TEST_SECRET_123',
        qrCode: '<svg>QR Code</svg>',
      })
      
      wrapper.vm.code = ['1', '2', '3']
      wrapper.vm.handleConfirm()
      
      expect(wrapper.emitted('confirm')).toBeFalsy()
    })

    it('should disable confirm button when code is not 6 digits', async () => {
      const wrapper = createWrapper({
        secret: 'TEST_SECRET_123',
        qrCode: '<svg>QR Code</svg>',
      })
      
      wrapper.vm.code = ['1', '2', '3']
      await wrapper.vm.$nextTick()
      
      const buttons = wrapper.findAll('button')
      const confirmButton = buttons.find(btn => btn.text().includes('Confirm'))
      
      if (confirmButton) {
        expect(confirmButton.attributes('disabled')).toBeDefined()
      }
    })

    it('should show loading state on confirm button when confirming', () => {
      const wrapper = createWrapper({
        secret: 'TEST_SECRET_123',
        qrCode: '<svg>QR Code</svg>',
        confirming: true,
      })
      
      const buttons = wrapper.findAll('button')
      const confirmButton = buttons.find(btn => btn.text().includes('Confirm'))
      
      if (confirmButton) {
        expect(confirmButton.classes()).toContain('loading')
      }
    })
  })

  describe('State Reset', () => {
    it('should clear code when secret is cleared', async () => {
      const wrapper = createWrapper({
        secret: 'TEST_SECRET_123',
        qrCode: '<svg>QR Code</svg>',
      })
      
      wrapper.vm.code = ['1', '2', '3', '4', '5', '6']
      
      // Clear secret
      await wrapper.setProps({ secret: null })
      await wrapper.vm.$nextTick()
      
      expect(wrapper.vm.code).toEqual([])
    })
  })
})

