import { flushPromises, mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { defineComponent, readonly, ref } from 'vue'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import IntegrationModal from '~/components/open/integrations/components/IntegrationModal.vue'

const integrationMocks = vi.hoisted(() => ({ integrations: { value: [] as any[] }, create: vi.fn(), update: vi.fn(), success: vi.fn() }))
vi.mock('~/api', () => ({ apiService: { mutate: vi.fn() } }))
mockNuxtImport('useFormIntegrations', () => () => ({
  list: () => ({ data: integrationMocks.integrations }),
  createIntegration: () => ({ isPending: { value: false }, mutateAsync: integrationMocks.create }),
  updateIntegration: () => ({ isPending: { value: false }, mutateAsync: integrationMocks.update }),
}))
mockNuxtImport('useAlert', () => () => ({ success: integrationMocks.success, error: vi.fn() }))
mockNuxtImport('useCrisp', () => () => ({}))

// Keep the real useForm and modal, with a small editor for nested values.
vi.mock('~/composables/components/useComponentRegistry', () => ({
  useComponentRegistry: () => ({ getIntegrationComponent: () => defineComponent({
    name: 'TestIntegrationEditor',
    props: ['integrationData'],
    template: `<div>
      <input aria-label="Send To" v-model="integrationData.data.send_to" />
      <input aria-label="Subject" v-model="integrationData.data.subject" />
      <input v-if="integrationData.data.appearance" aria-label="Header" v-model="integrationData.data.appearance.header" />
      <input v-if="integrationData.logic" aria-label="Condition" v-model="integrationData.logic.conditions[0].value" />
    </div>`,
  }) }),
}))

function mountModal(formIntegrationId: number | null = 2) {
  return mount(IntegrationModal, {
    props: { show: true, form: { id: 1 }, integrationKey: 'email', integration: { id: 'email', name: 'Email Notification' }, formIntegrationId },
    global: { stubs: {
      UModal: { template: '<section><slot name="body" /><slot name="footer" /></section>' },
      VForm: { template: '<div><slot /></div>' },
      OverlayScrollbarsComponent: { template: '<div><slot /></div>' },
      UButton: { template: '<button><slot /></button>' },
      Icon: true, USkeleton: true,
    } },
  })
}
function cachedIntegration() {
  return {
    id: 2, integration_id: 'email', status: 'active', oauth_id: null,
    data: { send_to: 'old@example.com', subject: 'Old', appearance: { header: 'Old header' } },
    logic: { conditions: [{ value: 'old condition' }] },
  }
}

describe('integration editor query data isolation', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.useFakeTimers()
    integrationMocks.integrations = readonly(ref([cachedIntegration()]))
    integrationMocks.update.mockResolvedValue({ message: 'Form Integration was updated.' })
    integrationMocks.create.mockResolvedValue({ message: 'Form Integration was created.' })
  })
  afterEach(() => vi.useRealTimers())

  it('submits edited settings and nested logic without changing the readonly cache', async () => {
    const wrapper = mountModal()
    await wrapper.get('[aria-label="Send To"]').setValue('new@example.com')
    await wrapper.get('[aria-label="Subject"]').setValue('New')
    await wrapper.get('[aria-label="Header"]').setValue('New header')
    await wrapper.get('[aria-label="Condition"]').setValue('new condition')
    expect(integrationMocks.integrations.value).toEqual([cachedIntegration()])
    await wrapper.findAll('button')[0].trigger('click')
    await flushPromises()
    expect(integrationMocks.update).toHaveBeenCalledWith({
      integration_id: 'email', status: 'active', oauth_id: null,
      data: { send_to: 'new@example.com', subject: 'New', appearance: { header: 'New header' } },
      logic: { conditions: [{ value: 'new condition' }] },
    })
    expect(integrationMocks.success).toHaveBeenCalledWith('Form Integration was updated.')
    expect(wrapper.emitted('close')).toHaveLength(1)
    expect(integrationMocks.integrations.value).toEqual([cachedIntegration()])
    wrapper.unmount()
  })

  it('leaves mutable cached settings unchanged when closing without saving', async () => {
    integrationMocks.integrations = ref([cachedIntegration()])
    const wrapper = mountModal()
    await wrapper.get('[aria-label="Send To"]').setValue('unsaved@example.com')
    await wrapper.get('[aria-label="Condition"]').setValue('unsaved condition')
    await wrapper.findAll('button')[1].trigger('click')
    expect(integrationMocks.update).not.toHaveBeenCalled()
    expect(integrationMocks.integrations.value).toEqual([cachedIntegration()])
    wrapper.unmount()
  })

  it.each([{ logic: null }, { logic: [] }])('normalizes empty saved logic ($logic) and allows missing data', ({ logic }) => {
    integrationMocks.integrations = readonly(ref([{ ...cachedIntegration(), data: null, logic }]))
    const wrapper = mountModal()
    const form = wrapper.findComponent({ name: 'TestIntegrationEditor' }).props('integrationData')
    expect(form.data).toEqual({})
    expect(form.logic).toBeNull()
    wrapper.unmount()
  })

  it('creates an integration with editable defaults', async () => {
    integrationMocks.integrations = readonly(ref([]))
    const wrapper = mountModal(null)
    await wrapper.get('[aria-label="Send To"]').setValue('new@example.com')
    await wrapper.findAll('button')[0].trigger('click')
    await flushPromises()
    expect(integrationMocks.create).toHaveBeenCalledWith({
      integration_id: 'email', status: 'active', oauth_id: null,
      data: { send_to: 'new@example.com' }, logic: null,
    })
    expect(integrationMocks.update).not.toHaveBeenCalled()
    wrapper.unmount()
  })
})
