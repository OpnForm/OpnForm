import { describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'

const mocks = vi.hoisted(() => ({ list: vi.fn(), useInfiniteQuery: vi.fn() }))
vi.mock('~/api/forms', () => ({ formsApi: { list: mocks.list } }))
vi.mock('@tanstack/vue-query', () => ({
  useInfiniteQuery: mocks.useInfiniteQuery,
  useQueryClient: vi.fn(),
}))
import { useFormsList } from '../../composables/query/forms/useFormsList.js'

describe('form list pagination', () => {
  it('requests bounded larger pages and stops at the API last page', async () => {
    mocks.useInfiniteQuery.mockReturnValue({ data: ref(), hasNextPage: ref(false) })
    useFormsList(ref(42))
    const options = mocks.useInfiniteQuery.mock.calls.at(-1)[0]
    await options.queryFn({ pageParam: 2 })
    expect(mocks.list).toHaveBeenCalledWith(42, { params: { page: 2, per_page: 50 } })
    expect(options.getNextPageParam({ meta: { current_page: 1, last_page: 2 } })).toBe(2)
    expect(options.getNextPageParam({ meta: { current_page: 2, last_page: 2 } })).toBeUndefined()
  })
})
