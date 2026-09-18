import { describe, it, expect, vi } from 'vitest'
import { mount, flushPromises } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { mockNuxtImport } from '@nuxt/test-utils/runtime'
import { useTokens } from '~/composables/query/useTokens'

const mocks = vi.hoisted(() => ({ list: vi.fn(), create: vi.fn(), remove: vi.fn() }))
vi.mock('~/api/tokens', () => ({ tokensApi: { list: mocks.list, create: mocks.create, delete: mocks.remove } }))
mockNuxtImport('useAlert', () => () => ({ success: vi.fn() }))

describe('token list after creation', () => {
  it('reloads revocable metadata without putting the one-time secret into query data', async () => {
    const metadata = { id: 42, name: 'Bureau', abilities: ['admin:users:block'], expires_at: '2026-10-01T12:00:00Z' }
    mocks.list.mockResolvedValueOnce([]).mockResolvedValueOnce([metadata])
    mocks.create.mockResolvedValue({ token: 'fake-one-time-secret', message: 'Created' })
    mocks.remove.mockResolvedValue({})
    const client = new QueryClient({ defaultOptions: { queries: { retry: false }, mutations: { retry: false } } })
    let createToken: ReturnType<ReturnType<typeof useTokens>['create']>
    let removeToken: ReturnType<ReturnType<typeof useTokens>['remove']>
    const wrapper = mount(defineComponent({ setup() {
      const tokens = useTokens()
      const list = tokens.list()
      createToken = tokens.create()
      removeToken = tokens.remove()
      return () => h('div', JSON.stringify(list.data.value))
    } }), { global: { plugins: [[VueQueryPlugin, { queryClient: client }]] } })
    await vi.waitFor(() => expect(client.getQueryData(['tokens', 'list'])).toEqual([]))
    const result = await createToken!.mutateAsync({ name: 'Bureau', abilities: ['admin:users:block'] })
    expect(result.token).toBe('fake-one-time-secret')
    await flushPromises()
    expect(client.getQueryData(['tokens', 'list'])).toEqual([metadata])
    expect(JSON.stringify(client.getQueriesData({ queryKey: ['tokens'] }))).not.toContain('fake-one-time-secret')
    await removeToken!.mutateAsync(metadata.id)
    expect(mocks.remove).toHaveBeenCalledWith(42)
    expect(client.getQueryData(['tokens', 'list'])).toEqual([])
    wrapper.unmount()
    client.clear()
  })
})
