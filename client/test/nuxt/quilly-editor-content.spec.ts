import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import QuillyEditor from '~/components/forms/heavy/components/QuillyEditor.vue'

describe('QuillyEditor external content changes', () => {
  it('replaces the previous zone content and formatting without emitting edits', async () => {
    const wrapper = mount(QuillyEditor, {
      attachTo: document.body,
      props: { modelValue: '<h1>Document title</h1>' },
    })
    await wrapper.setProps({ modelValue: '<p>Full name</p>' })

    expect(wrapper.get('.ql-editor').text()).toBe('Full name')
    expect(wrapper.find('.ql-editor h1').exists()).toBe(false)
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()

    await wrapper.setProps({ modelValue: '<p>Email</p>' })
    expect(wrapper.get('.ql-editor').text()).toBe('Email')
    await wrapper.setProps({ modelValue: '' })
    expect(wrapper.get('.ql-editor').text()).toBe('')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()
    wrapper.unmount()
  })

  it('emits user edits and clearing after switching content', async () => {
    const wrapper = mount(QuillyEditor, {
      attachTo: document.body,
      props: { modelValue: '<p>Before</p>' },
    })
    await wrapper.setProps({ modelValue: '<p>After</p>' })
    const quill = wrapper.emitted('ready')![0][0] as any
    quill.insertText(5, ' edited', 'user')
    expect(wrapper.emitted('update:modelValue')?.at(-1)?.[0]).toContain('After edited')
    quill.deleteText(0, quill.getLength(), 'user')
    expect(wrapper.emitted('update:modelValue')).toHaveLength(2)
    expect(quill.getText().trim()).toBe('')
    wrapper.unmount()
  })
})
