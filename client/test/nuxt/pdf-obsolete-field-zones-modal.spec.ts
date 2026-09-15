import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PdfObsoleteFieldZonesModal from '~/components/open/pdf-editor/PdfObsoleteFieldZonesModal.vue'

const zones = [
  { id: 'account-number', page: 1, field_id: 'deleted_account_number', field_name: 'Account Number' },
  { id: 'routing-number', page: 2, field_id: '3b8362f4-41f9-4a39-8d3d-a122697b7216' },
]

function createWrapper(props = {}) {
  return mount(PdfObsoleteFieldZonesModal, {
    props: {
      open: true,
      zones,
      ...props,
    },
    global: {
      stubs: {
        UModal: {
          props: ['open', 'ui', 'description'],
          emits: ['update:open'],
          template: '<div class="modal"><slot name="title" /><p>{{ description }}</p><slot name="body" /><slot name="footer" /></div>',
        },
        UButton: {
          emits: ['click'],
          template: '<button @click="$emit(\'click\')"><slot /></button>',
        },
      },
    },
  })
}

describe('PdfObsoleteFieldZonesModal', () => {
  it('lists every obsolete zone with its page and available label', () => {
    const wrapper = createWrapper()

    expect(wrapper.text()).toContain('Account Number')
    expect(wrapper.text()).toContain('Page 1')
    expect(wrapper.text()).toContain('Deleted field 2')
    expect(wrapper.text()).not.toContain(zones[1].field_id)
    expect(wrapper.text()).toContain('Page 2')
  })

  it('closes when the acknowledgement is clicked without emitting a removal action', async () => {
    const wrapper = createWrapper()

    expect(wrapper.text()).toContain('have been removed')
    expect(wrapper.findAll('button')).toHaveLength(1)
    expect(wrapper.get('button').text()).toBe('Got it')

    await wrapper.get('button').trigger('click')

    expect(wrapper.emitted('update:open')).toEqual([[false]])
    expect(wrapper.emitted('remove')).toBeUndefined()
  })
})
