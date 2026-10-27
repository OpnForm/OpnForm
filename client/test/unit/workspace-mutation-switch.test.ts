import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { computed, createSSRApp, effectScope, ref, toValue } from 'vue'
import { QueryClient, VueQueryPlugin } from '@tanstack/vue-query'
import { workspaceApi } from '~/api'
import { useWorkspaces } from '~/composables/query/useWorkspaces'

vi.mock('~/api', () => ({
  workspaceApi: { update: vi.fn(), customDomains: { update: vi.fn() } },
}))

describe('workspace mutations while switching workspaces', () => {
  let queryClient: QueryClient
  let scope: ReturnType<typeof effectScope>

  beforeEach(() => {
    queryClient = new QueryClient({ defaultOptions: { mutations: { retry: false } } })
    scope = effectScope()
    vi.stubGlobal('useIsAuthenticated', () => ({ isAuthenticated: ref(true) }))
    vi.stubGlobal('computed', computed)
    vi.stubGlobal('toValue', toValue)
    vi.clearAllMocks()
  })

  afterEach(() => {
    scope.stop()
    queryClient.clear()
    vi.unstubAllGlobals()
  })

  function mutationFor(factory: (workspaces: ReturnType<typeof useWorkspaces>) => unknown) {
    const app = createSSRApp({ render: () => null })
    app.use(VueQueryPlugin, { queryClient })

    return scope.run(() => app.runWithContext(() => factory(useWorkspaces()))) as ReturnType<ReturnType<typeof useWorkspaces>['update']>
  }

  it.each(['profile', 'domains'])('keeps a delayed %s response attached to the saved workspace', async (kind) => {
    const first = { id: 1, name: 'First', custom_domains: [] }
    const second = { id: 2, name: 'Second', custom_domains: [] }
    queryClient.setQueryData(['workspaces', 'list'], [first, second])
    queryClient.setQueryData(['workspaces', 2], second)
    const currentId = ref(1)
    let finishRequest!: (value: unknown) => void
    const request = new Promise(resolve => { finishRequest = resolve })
    const api = kind === 'profile' ? workspaceApi.update : workspaceApi.customDomains.update
    vi.mocked(api).mockReturnValueOnce(request)
    const mutation = mutationFor(workspaces => kind === 'profile'
      ? workspaces.update(currentId)
      : workspaces.updateCustomDomains(currentId))
    const data = kind === 'profile' ? { name: 'Updated first' } : { custom_domains: ['forms.example.com'] }
    const pending = mutation.mutateAsync(data)
    await vi.waitFor(() => expect(api).toHaveBeenCalledWith(1, data))

    currentId.value = 2
    const updated = { ...first, ...data }
    finishRequest(kind === 'profile' ? { workspace: updated } : updated)
    await pending

    expect(queryClient.getQueryData(['workspaces', 1])).toEqual(updated)
    expect(queryClient.getQueryData(['workspaces', 2])).toEqual(second)
    expect(queryClient.getQueryData(['workspaces', 'list'])).toEqual([updated, second])
  })
})
