import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { storeFile } from '../../lib/file-uploads.js'
import { uploadsApi } from '~/api'

vi.mock('~/api', () => ({
  uploadsApi: {
    getSignedStorageUrl: vi.fn(),
    uploadFile: vi.fn(),
  },
}))

describe('storeFile', () => {
  beforeEach(() => {
    globalThis.useFeatureFlag = vi.fn(() => false)
    globalThis.useFetch = vi.fn(() => Promise.resolve({ error: { value: null } }))
  })

  afterEach(() => {
    vi.clearAllMocks()
    delete globalThis.useFeatureFlag
    delete globalThis.useFetch
  })

  it('forwards signed storage headers to the S3 PUT request', async () => {
    const file = {
      name: 'logo.svg',
      type: 'image/svg+xml',
    }

    uploadsApi.getSignedStorageUrl.mockResolvedValue({
      uuid: '7d438d35-6d81-4317-9bd4-e9c6780b210c',
      url: 'https://nbg1.your-objectstorage.com/mybucket/tmp/file?X-Amz-SignedHeaders=host%3Bx-amz-acl',
      headers: {
        Host: ['nbg1.your-objectstorage.com'],
        host: ['nbg1.your-objectstorage.com'],
        'x-amz-acl': ['private'],
        'Content-Type': 'image/svg+xml',
      },
    })

    await storeFile(file)

    expect(globalThis.useFetch).toHaveBeenCalledWith(
      'https://nbg1.your-objectstorage.com/mybucket/tmp/file?X-Amz-SignedHeaders=host%3Bx-amz-acl',
      expect.objectContaining({
        method: 'PUT',
        body: file,
        headers: expect.objectContaining({
          'x-amz-acl': 'private',
          'Content-Type': 'image/svg+xml',
        }),
      }),
    )

    const uploadOptions = globalThis.useFetch.mock.calls[0][1]
    expect(uploadOptions.headers).not.toHaveProperty('Host')
    expect(uploadOptions.headers).not.toHaveProperty('host')
  })
  it('rejects a failed signed PUT instead of returning file metadata', async () => {
    const error = new Error('Storage rejected the upload')
    uploadsApi.getSignedStorageUrl.mockResolvedValue({ uuid: 'failed', url: 'https://storage.example.test/tmp/file', headers: {} })
    globalThis.useFetch.mockResolvedValue({ error: { value: error } })
    await expect(storeFile({ name: 'file.txt', type: 'text/plain' })).rejects.toBe(error)
  })
})
