import { describe, it, expect, vi, afterEach } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import AccessTokenModal from '~/components/users/settings/AccessTokenModal.vue'

vi.mock('@tanstack/vue-query', () => ({ useQuery: () => ({ data: { value: ['admin:users:block'] } }) }))
mockNuxtImport('useTokens', () => () => ({ abilities: [{ name: 'forms-read', title: 'Read forms' }], create: () => ({}) }))
mockNuxtImport('useAlert', () => () => ({ error: vi.fn() }))
mockNuxtImport('useCrisp', () => () => ({ openHelpdesk: vi.fn() }))
mockNuxtImport('useForm', () => (initial: Record<string, unknown>) => {
  const form = reactive({
    ...initial,
    errors: { get: vi.fn(), set: vi.fn() },
    reset() { Object.assign(form, initial) },
    mutate: vi.fn().mockResolvedValue({ token: 'fake-test-token' }),
  })
  return form
})

function mountModal() {
  return mount(AccessTokenModal, { props: { modelValue: true }, global: { stubs: {
    UModal: { name: 'UModal', emits: ['update:open'], template: '<div><slot name="body"/><slot name="footer"/></div>' },
    VForm: { template: '<div><slot/></div>' },
    FlatSelectInput: { name: 'FlatSelectInput', props: ['form', 'options'], template: '<div/>' },
    TextInput: true,
    UAlert: true,
    CopyContent: true,
    UFormField: { template: '<div><slot/></div>', props: ['label', 'error'] },
    UInput: { name: 'UInput', props: ['modelValue'], emits: ['update:modelValue'], template: '<input :value="modelValue" @input="$emit(\'update:modelValue\', $event.target.value)"/>' },
    UButton: { emits: ['click'], template: '<button @click="$emit(\'click\')"><slot/></button>' },
  } } })
}

describe('admin token opt-in', () => {
  afterEach(() => vi.useRealTimers())

  it('offers admin scopes without default selection and resets selection', async () => {
    const wrapper = mountModal()
    const select = wrapper.findComponent({ name: 'FlatSelectInput' })
    const form = select.props('form')
    expect(select.props('options').map((option: {value: string}) => option.value)).toContain('admin:users:block')
    expect(form.abilities).toEqual(['forms-read'])
    expect(wrapper.find('input').exists()).toBe(false)
    form.abilities = ['admin:users:block']
    await wrapper.vm.$nextTick()
    await wrapper.get('input').setValue('7')
    await wrapper.findAll('button')[0].trigger('click')
    expect(form.abilities).toEqual(['forms-read'])
    form.abilities = ['admin:users:block']
    await wrapper.vm.$nextTick()
    expect((wrapper.get('input').element as HTMLInputElement).value).toBe('30')
  })

  it('submits a timezone-qualified expiry only when admin scopes are selected', async () => {
    vi.useFakeTimers()
    vi.setSystemTime(new Date('2026-09-18T10:00:00Z'))
    const wrapper = mountModal()
    const form = wrapper.findComponent({ name: 'FlatSelectInput' }).props('form')
    form.abilities = ['admin:users:block']
    await wrapper.vm.$nextTick()
    await wrapper.get('input').setValue('7')
    await wrapper.findAll('button')[1].trigger('click')
    expect(form.expires_at).toBe('2026-09-25T10:00:00.000Z')
    expect(form.mutate).toHaveBeenCalledOnce()
    await flushPromises()
    wrapper.unmount()

    const publicWrapper = mountModal()
    const publicForm = publicWrapper.findComponent({ name: 'FlatSelectInput' }).props('form')
    await publicWrapper.findAll('button')[1].trigger('click')
    expect(publicForm.expires_at).toBeNull()
    expect(publicForm.mutate).toHaveBeenCalledOnce()
  })

  it('rejects an invalid admin expiry before submitting', async () => {
    const wrapper = mountModal()
    const form = wrapper.findComponent({ name: 'FlatSelectInput' }).props('form')
    form.abilities = ['admin:users:block']
    await wrapper.vm.$nextTick()
    await wrapper.get('input').setValue('91')
    await wrapper.findAll('button')[1].trigger('click')
    expect(form.errors.set).toHaveBeenCalledWith('expires_at', expect.any(String))
    expect(form.mutate).not.toHaveBeenCalled()
  })

  it('clears admin selection when the dialog emits an Escape or overlay close', async () => {
    const wrapper = mountModal()
    const form = wrapper.findComponent({ name: 'FlatSelectInput' }).props('form')
    form.abilities = ['admin:users:block']
    wrapper.findComponent({ name: 'UModal' }).vm.$emit('update:open', false)
    await wrapper.vm.$nextTick()
    expect(wrapper.emitted('close')?.at(-1)).toEqual([false])
    expect(form.abilities).toEqual(['forms-read'])
  })

  it('does not show a previous creation secret after closing and reopening the form', async () => {
    const wrapper = mountModal()
    const form = wrapper.findComponent({ name: 'FlatSelectInput' }).props('form')
    let finish: (value: { token: string }) => void = () => {}
    form.mutate.mockReturnValue(new Promise(resolve => { finish = resolve }))
    await wrapper.findAll('button')[1].trigger('click')
    await wrapper.setProps({ modelValue: false })
    await wrapper.setProps({ modelValue: true })
    finish({ token: 'old-fake-secret' })
    await flushPromises()
    expect(wrapper.findComponent({ name: 'FlatSelectInput' }).exists()).toBe(true)
    expect(wrapper.find('copy-content-stub').exists()).toBe(false)
  })
})
