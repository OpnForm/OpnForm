import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import VueKatex from '@janniks/vue3-katex'
import katex from 'katex'

describe('patched KaTeX through the Notion Vue wrapper', () => {
  it('renders inline and display mathematics through the real wrapper', () => {
    for (const displayMode of [false, true]) {
      const wrapper = mount({ template: '<katex-element expression="c^2 = a^2 + b^2" :display-mode="display" />', data: () => ({ display: displayMode }) }, {
        global: { plugins: [[VueKatex, { globalOptions: {} }]] },
      })
      expect(wrapper.find('.katex').exists()).toBe(true)
      expect(wrapper.find('math').text()).toContain('c2')
      expect(wrapper.find('.katex-display').exists()).toBe(displayMode)
      wrapper.unmount()
    }
  })

  it('rejects untrusted links and HTML extensions in the wrapper', () => {
    const wrapper = mount({ template: '<katex-element :expression="expression" />', data: () => ({ expression: String.raw`\href{javascript:alert(1)}{click} \htmlClass{unsafe}{x}` }) }, {
      global: { plugins: [[VueKatex, { globalOptions: { strict: 'ignore' } }]] },
    })
    expect(wrapper.find('a[href]').exists()).toBe(false)
    expect(wrapper.find('.unsafe').exists()).toBe(false)
    wrapper.unmount()
  })

  it('does not inherit trust from pre-existing prototype pollution', () => {
    expect(katex.renderToString(String.raw`\href{javascript:alert(1)}{click}`, { trust: true, strict: 'ignore' })).toContain('href="javascript:')
    const previous = Object.getOwnPropertyDescriptor(Object.prototype, 'trust')
    try {
      Object.defineProperty(Object.prototype, 'trust', { value: true, configurable: true, writable: true, enumerable: true })
      const html = katex.renderToString(String.raw`\href{javascript:alert(1)}{click}`, { strict: 'ignore' })
      expect(html).not.toContain('href="javascript:')
    } finally {
      if (previous) Object.defineProperty(Object.prototype, 'trust', previous)
      else delete Object.prototype.trust
    }
  })
})
