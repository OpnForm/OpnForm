import { shallowMount } from '@vue/test-utils'
import { nextTick } from 'vue'
import { afterEach, expect, it, vi } from 'vitest'
import { useNuxtApp, useState } from '#app'
import FormEditorPreview from '~/components/open/forms/components/form-components/FormEditorPreview.vue'

vi.mock('~/components/open/forms/OpenCompleteForm.vue', () => ({
  default: { template: '<div />' },
}))
vi.mock('~/stores/working_form', async () => {
  const { reactive, ref } = await import('vue')
  const store = reactive({ content: ref({ dark_mode: 'light', presentation_style: 'classic' }) })
  return { useWorkingFormStore: () => store }
})

afterEach(() => vi.unstubAllGlobals())

it('clears the fullscreen state when the expanded preview unmounts', async () => {
  vi.stubGlobal('defineShortcuts', vi.fn())
  const fullscreen = useNuxtApp().runWithContext(() => useState('form-editor-fullscreen-preview', () => false))
  const wrapper = shallowMount(FormEditorPreview, {
    global: {
      stubs: {
        TrackClick: { template: '<div><slot /></div>' },
        UTooltip: { template: '<div><slot /></div>' },
        UButton: { template: '<button />' },
        OverlayScrollbarsComponent: {
          setup: () => ({ osInstance: () => null }),
          template: '<div><slot /></div>',
        },
        USkeleton: true,
        UDropdownMenu: true,
        UFieldGroup: true,
      },
    },
  })

  try {
    expect(fullscreen.value).toBe(false)
    await wrapper.get('button').trigger('click')
    expect(fullscreen.value).toBe(true)
  } finally {
    wrapper.unmount()
  }
  await nextTick()
  expect(fullscreen.value).toBe(false)
})
