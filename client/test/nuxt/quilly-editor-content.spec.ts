import { describe, expect, it, vi } from 'vitest'
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

  it('keeps undo and redo within the current zone after switching documents', async () => {
    const wrapper = mount(QuillyEditor, {
      attachTo: document.body,
      props: { modelValue: '<h1>Zone A</h1>' },
    })
    const quill = wrapper.emitted('ready')![0][0] as any
    // JSDOM has no range geometry; keep the real history and content operations.
    vi.spyOn(quill, 'scrollSelectionIntoView').mockImplementation(() => {})
    quill.history.undo()
    expect(wrapper.get('.ql-editor').text()).toBe('Zone A')
    expect(wrapper.emitted('update:modelValue')).toBeUndefined()

    quill.insertText(6, ' edited', 'user')
    quill.history.cutoff()
    await wrapper.setProps({ modelValue: '<p>Zone B</p>' })
    quill.history.undo()
    quill.history.redo()
    expect(wrapper.get('.ql-editor').text()).toBe('Zone B')
    expect(wrapper.find('.ql-editor h1').exists()).toBe(false)
    expect(wrapper.emitted('update:modelValue')).toHaveLength(1)

    quill.insertText(6, ' edited', 'user')
    quill.history.undo()
    expect(wrapper.get('.ql-editor').text()).toBe('Zone B')
    quill.history.redo()
    expect(wrapper.get('.ql-editor').text()).toBe('Zone B edited')

    await wrapper.setProps({ modelValue: '' })
    const editCount = wrapper.emitted('update:modelValue')!.length
    quill.history.undo()
    quill.history.redo()
    expect(wrapper.get('.ql-editor').text()).toBe('')
    expect(wrapper.emitted('update:modelValue')).toHaveLength(editCount)
    wrapper.unmount()
  })
})
