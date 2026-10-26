import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useForms } from '~/composables/query/forms/useForms'
import { formsApi } from '~/api/forms'

vi.mock('@tanstack/vue-query', () => ({
  useQuery: (options: any) => options,
  useMutation: vi.fn(),
  useQueryClient: () => ({}),
}))
vi.mock('~/api/forms', () => ({
  formsApi: { getById: vi.fn(), publicGetById: vi.fn() },
}))

describe('form detail queries with a reactive destination id', () => {
  beforeEach(() => vi.clearAllMocks())

  it.each([true, false])('uses the current id for private=%s queries', usePrivate => {
    const targetId = ref(10)
    const query = useForms().detailById(targetId, { usePrivate }) as any
    const fetch = usePrivate ? formsApi.getById : formsApi.publicGetById
    query.queryFn()
    expect(fetch).toHaveBeenLastCalledWith(10, {})
    targetId.value = 20
    query.queryFn()
    expect(fetch).toHaveBeenLastCalledWith(20, {})
  })
})
