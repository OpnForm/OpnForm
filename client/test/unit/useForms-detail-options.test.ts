import { beforeEach, describe, expect, it, vi } from 'vitest'

const mocks = vi.hoisted(() => ({
  publicGet: vi.fn(),
  get: vi.fn(),
  runWithContext: vi.fn(callback => callback()),
  useQuery: vi.fn(config => config),
}))

vi.stubGlobal('useNuxtApp', () => ({
  runWithContext: mocks.runWithContext,
}))

vi.mock('@tanstack/vue-query', () => ({
  useMutation: vi.fn(),
  useQuery: mocks.useQuery,
  useQueryClient: () => ({
    getQueryData: vi.fn(),
    setQueryData: vi.fn(),
  }),
}))

vi.mock('~/api/forms', () => ({
  formsApi: {
    get: mocks.get,
    publicGet: mocks.publicGet,
  },
}))

vi.mock('~/composables/useAuthFlow', () => ({
  useIsAuthenticated: () => ({ isAuthenticated: { value: true } }),
}))

vi.mock('~/composables/query/forms/useFormsListCache', () => ({
  useFormsListCache: () => ({
    add: vi.fn(),
    remove: vi.fn(),
    update: vi.fn(),
  }),
}))

import { useForms } from '../../composables/query/forms/useForms.js'

describe('useForms detail options', () => {
  beforeEach(() => {
    vi.clearAllMocks()
  })

  it('keeps query retry options out of the underlying HTTP request', async () => {
    const retry = vi.fn()
    const retryDelay = vi.fn()
    const requestOptions = {
      headers: { 'x-test': 'value' },
      retry: false,
    }

    const query = useForms().detail('contact', {
      refetchOnWindowFocus: false,
      requestOptions,
      retry,
      retryDelay,
    })

    expect(query.retry).toBe(retry)
    expect(query.retryDelay).toBe(retryDelay)
    expect(query.refetchOnWindowFocus).toBe(false)

    await query.queryFn()

    expect(mocks.runWithContext).toHaveBeenCalledTimes(1)
    expect(mocks.publicGet).toHaveBeenCalledWith('contact', requestOptions)
  })

  it('preserves private form requests with their explicit HTTP options', async () => {
    const requestOptions = {
      headers: { authorization: 'Bearer test-token' },
    }

    const query = useForms().detail('contact', {
      usePrivate: true,
      requestOptions,
    })

    await query.queryFn()

    expect(mocks.runWithContext).toHaveBeenCalledTimes(1)
    expect(mocks.get).toHaveBeenCalledWith('contact', requestOptions)
    expect(mocks.publicGet).not.toHaveBeenCalled()
  })
})
