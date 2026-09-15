import { describe, expect, it } from 'vitest'
import { mount } from '@vue/test-utils'
import PdfObsoleteFieldZonesModal from '~/components/open/pdf-editor/PdfObsoleteFieldZonesModal.vue'

const zones = [
  { id: 'account-number', page: 1, field_id: 'deleted_account_number', field_name: 'Account Number' },
  { id: 'routing-number', page: 2, field_id: 'deleted_routing_number' },
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
          props: ['open', 'ui'],
          emits: ['update:open'],
          template: '<div class="modal"><slot name="header" /><slot name="body" /><slot name="footer" /></div>',
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
    expect(wrapper.text()).toContain('deleted_routing_number')
    expect(wrapper.text()).toContain('Page 2')
  })

  it('keeps zones when dismissed and emits removal only after explicit confirmation', async () => {
    const wrapper = createWrapper()
    const buttons = wrapper.findAll('button')

    await buttons.find(button => button.text().includes('Keep and review')).trigger('click')
    expect(wrapper.emitted('update:open')).toEqual([[false]])
    expect(wrapper.emitted('remove')).toBeUndefined()

    await buttons.find(button => button.text().includes('Remove obsolete zones')).trigger('click')
    expect(wrapper.emitted('remove')).toEqual([[]])
  })
})
