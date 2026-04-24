import { mount } from '@vue/test-utils'
import { describe, expect, it } from 'vitest'
import PasswordStrengthIndicator from '~/components/pages/auth/PasswordStrengthIndicator.vue'

describe('PasswordStrengthIndicator', () => {
  it('treats a hyphen as a special character', () => {
    const wrapper = mount(PasswordStrengthIndicator, {
      props: {
        password: 'Abcd-1234',
      },
      global: {
        stubs: {
          Icon: true,
        },
      },
    })

    expect(wrapper.text()).toContain('Strong')
    expect(wrapper.text()).not.toContain('Good')
  })
})
