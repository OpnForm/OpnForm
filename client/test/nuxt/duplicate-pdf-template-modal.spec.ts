import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { ref } from 'vue'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import DuplicatePdfTemplateModal from '~/components/open/pdf-editor/DuplicatePdfTemplateModal.vue'

const state = vi.hoisted(() => ({
  destination: null as any,
  forms: null as any,
  mutation: null as any,
  mutateAsync: vi.fn(),
}))
vi.mock('~/composables/query/forms/useFormsList', () => ({ useFormsList: () => state.forms }))
vi.mock('~/composables/query/forms/usePdfTemplates', () => ({
  usePdfTemplates: () => ({ duplicate: () => state.mutation }),
}))
mockNuxtImport('useForms', () => () => ({ detailById: () => state.destination }))

function mountModal() {
  return mount(DuplicatePdfTemplateModal, {
    props: {
      open: true,
      form: { id: 1, title: 'Source', workspace_id: 3, slug: 'source', properties: [] },
      template: { id: 10, name: 'Static template', zone_mappings: [{ static_text: '<h1>Agreement</h1>' }] },
    },
    global: { stubs: {
      UModal: { props: ['open'], template: '<section><slot name="body" /><slot name="footer" /></section>' },
      UAlert: { props: ['title'], template: '<div role="alert">{{ title }}</div>' },
      UButton: { props: ['disabled', 'loading'], template: '<button :disabled="disabled || loading"><slot /></button>' },
      TextInput: { template: '<input />' },
      SelectInput: {
        props: ['modelValue', 'options'],
        emits: ['update:modelValue'],
        template: '<select :value="modelValue" @change="$emit(\'update:modelValue\', Number($event.target.value))"><option v-for="option in options" :key="option.value" :value="option.value">{{ option.name }}</option></select>',
      },
    } },
  })
}

describe('static-only PDF template duplication', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    state.destination = { data: ref(undefined), isLoading: ref(true), error: ref(null) }
    state.forms = { forms: ref([{ id: 2, title: 'Destination' }]), isLoading: ref(false), isFetchingNextPage: ref(false), error: ref(null) }
    state.mutation = { mutateAsync: state.mutateAsync, isPending: ref(false) }
  })

  it('shows destination loading and failure even when there are no mapped fields', async () => {
    const wrapper = mountModal()
    await wrapper.get('select').setValue('2')
    expect(wrapper.text()).toContain('Loading destination fields')
    expect(wrapper.findAll('button').at(-1)!.attributes('disabled')).toBeDefined()

    state.destination.isLoading.value = false
    state.destination.error.value = new Error('Destination unavailable')
    await flushPromises()
    expect(wrapper.get('[role="alert"]').text()).toBe('Could not load destination fields.')
    expect(wrapper.findAll('button').at(-1)!.attributes('disabled')).toBeDefined()
    expect(state.mutateAsync).not.toHaveBeenCalled()
    wrapper.unmount()
  })

  it('can copy a static-only template after the destination loads', async () => {
    const wrapper = mountModal()
    await wrapper.get('select').setValue('2')
    const destination = { id: 2, slug: 'destination', title: 'Destination', properties: [] }
    state.destination.data.value = destination
    state.destination.isLoading.value = false
    state.mutateAsync.mockResolvedValue({ data: { id: 20, form_id: 2 } })
    await flushPromises()
    expect(wrapper.findAll('button').at(-1)!.attributes('disabled')).toBeUndefined()
    await wrapper.get('form').trigger('submit')
    await flushPromises()
    expect(state.mutateAsync).toHaveBeenCalledWith({ target_form_id: 2, name: 'Copy of Static template', field_mappings: {} })
    expect(wrapper.emitted('duplicated')?.[0]).toEqual([{ template: { id: 20, form_id: 2 }, form: destination }])
    wrapper.unmount()
  })
})
