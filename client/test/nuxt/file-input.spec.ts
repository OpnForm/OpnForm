import { beforeEach, describe, expect, it, vi } from 'vitest'
import { flushPromises, mount } from '@vue/test-utils'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import FileInput from '../../components/forms/heavy/FileInput.vue'
import { storeFile } from '../../lib/file-uploads.js'

const { showError } = vi.hoisted(() => ({ showError: vi.fn() }))
mockNuxtImport('useAlert', () => () => ({ error: showError }))
vi.mock('../../lib/file-uploads.js', () => ({ storeFile: vi.fn() }))
vi.mock('../../components/forms/heavy/components/CameraUpload.vue', () => ({ default: { template: '<div />' } }))

function createWrapper(props = {}) {
  return mount(FileInput, {
    props: { name: 'attachment', modelValue: [], accept: 'pdf', ...props },
    global: {
      mocks: { $t: (key, params) => params?.types ? `Allowed only: ${params.types}` : key },
      stubs: {
        InputWrapper: { template: '<div><slot /></div>' },
        UploadedFile: true,
        UButton: true,
        ClientOnly: { template: '<div><slot /></div>' },
      },
    },
  })
}

function fileList(files) {
  return { length: files.length, item: index => files[index] }
}

describe('FileInput allowed file types', () => {
  beforeEach(() => {
    vi.clearAllMocks()
    vi.mocked(storeFile).mockResolvedValue({ uuid: 'upload-id', extension: 'pdf' })
  })

  it.each(['drop', 'picker'])('rejects a PNG from the %s before uploading', async (source) => {
    const wrapper = createWrapper()
    await flushPromises()
    const files = fileList([new File(['image'], 'image.png', { type: 'image/png' })])

    if (source === 'drop') {
      await wrapper.get('[role="button"]').trigger('drop', { dataTransfer: { files } })
    } else {
      Object.defineProperty(wrapper.get('input').element, 'files', { value: files })
      await wrapper.get('input').trigger('change')
    }

    expect(storeFile).not.toHaveBeenCalled()
    expect(showError).toHaveBeenCalledWith('Allowed only: pdf')
    expect(wrapper.vm.files).toEqual([])
    expect(wrapper.vm.loading).toBe(false)
  })

  it('uploads an allowed dropped file with uppercase extension and empty MIME type', async () => {
    const wrapper = createWrapper({ accept: ' PDF , , .jpg ' })
    await flushPromises()
    const file = new File(['document'], 'document.PDF')
    expect(wrapper.get('input').attributes('accept')).toBe('.pdf,.jpg')

    await wrapper.get('[role="button"]').trigger('drop', { dataTransfer: { files: fileList([file]) } })
    await flushPromises()

    expect(storeFile).toHaveBeenCalledWith(file)
    expect(wrapper.vm.files).toHaveLength(1)
    expect(showError).not.toHaveBeenCalled()
  })

  it('keeps accepted files when a mixed batch contains a rejected file', async () => {
    const wrapper = createWrapper()
    await flushPromises()
    const pdf = new File(['document'], 'document.pdf')
    const png = new File(['image'], 'image.png')
    await wrapper.get('[role="button"]').trigger('drop', { dataTransfer: { files: fileList([pdf, png]) } })
    await flushPromises()

    expect(storeFile).toHaveBeenCalledTimes(1)
    expect(wrapper.vm.files[0].file.name).toBe('document.pdf')
    expect(showError).toHaveBeenCalledOnce()
  })

  it('allows all extensions when restrictions are blank', async () => {
    const wrapper = createWrapper({ accept: ' , ' })
    await flushPromises()
    const file = new File(['data'], 'README')
    await wrapper.get('[role="button"]').trigger('drop', { dataTransfer: { files: fileList([file]) } })
    expect(storeFile).toHaveBeenCalledWith(file)
    expect(showError).not.toHaveBeenCalled()
  })

  it('rejects camera files using the same restrictions', async () => {
    const wrapper = createWrapper({ cameraUpload: true })
    await flushPromises()
    wrapper.vm.cameraFileUpload(new File(['image'], 'camera.png'))
    expect(storeFile).not.toHaveBeenCalled()
    expect(showError).toHaveBeenCalledOnce()
  })
})
