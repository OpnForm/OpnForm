import { expect, it, vi } from 'vitest'
import { mount } from '@vue/test-utils'
import { nextTick, reactive, ref } from 'vue'
import PaymentInput from '~/components/forms/heavy/PaymentInput.client.vue'

vi.mock('~/composables/useFeatureFlag', () => ({ useFeatureFlag: () => '' }))
vi.mock('~/composables/useAlert', () => ({ useAlert: () => ({ error: vi.fn() }) }))
vi.mock('@stripe/stripe-js', () => ({ loadStripe: vi.fn() }))

it('keeps the transparent payment focus color reactive to brand and validation changes', async () => {
  const invalid = ref(false)
  const form = reactive({ payment: null, errors: { has: () => invalid.value } })
  const wrapper = mount(PaymentInput, {
    props: { name: 'payment', theme: 'transparent', color: '#7c3aed', form },
    global: { stubs: { InputWrapper: { template: '<div><slot /></div>' }, Icon: true } },
  })
  const container = wrapper.get('[style*="--form-focus-color"]')
  expect(container.attributes('style')).toContain('--form-focus-color: #7c3aed')

  await wrapper.setProps({ color: '#15803d' })
  expect(container.attributes('style')).toContain('--form-focus-color: #15803d')
  invalid.value = true
  await nextTick()
  expect(container.attributes('style')).toContain('--form-focus-color: var(--color-red-500)')
  invalid.value = false
  await nextTick()
  expect(container.attributes('style')).toContain('--form-focus-color: #15803d')
  wrapper.unmount()
})
