import { useInfiniteQuery } from '@tanstack/vue-query'
import { formsApi } from '~/api/forms'
import { watchEffect, computed } from 'vue'
import { useQueryClient } from '@tanstack/vue-query'

export function useFormsList(workspaceId, options = {}) {
  // Separate API filters and custom options from TanStack Query options
  const { fetchAll, ...queryOptions } = options

  const query = useInfiniteQuery({
    queryKey: computed(() => ['forms', 'list', workspaceId.value]),
    queryFn: ({ pageParam = 1 }) => {
      const apiFilters = { page: pageParam }
      // Note: List endpoint returns lightweight form data (no properties).
      // Individual form detail queries will fetch the full form when needed.
      return formsApi.list(workspaceId.value, { params: apiFilters })
    },
    initialPageParam: 1,
    getNextPageParam: (lastPage) => {
      if (!lastPage?.meta) {
        console.warn('`meta` property not found in lastPage response. Cannot determine next page.')
        return undefined
      }
      const { current_page, last_page } = lastPage.meta
      const nextPage = current_page < last_page ? current_page + 1 : undefined
      return nextPage
    },
    ...queryOptions,
  })

  // If fetchAll is true, automatically fetch all pages
  if (fetchAll) {
    watchEffect(() => {
      if (
        query.isSuccess.value &&
        query.hasNextPage.value &&
        !query.isFetchingNextPage.value
      ) {
        query.fetchNextPage()
      }
    })
  }
    
  // Computed values for easier access
  const forms = computed(() => 
    query.data.value?.pages?.filter(page => page !== null)?.flatMap(page => page.data) || []
  )
  const currentPage = computed(() => {
    const pages = query.data.value?.pages
    if (!pages || pages.length === 0) return 0
    const lastPage = pages[pages.length - 1]
    return lastPage?.meta?.current_page || pages.length
  })
  const totalPages = computed(() => query.data.value?.pages?.[0]?.meta?.last_page || 1)
  const isComplete = computed(() => !query.hasNextPage.value)

  return {
    ...query,
    forms,
    currentPage,
    totalPages,
    isComplete,
    isFetched: query.isFetched,
  }
} 

export function useFormsListCache() {
  const queryClient = useQueryClient()

  const add = (workspaceId, newForm) => {
    const queryKey = ['forms', 'list', workspaceId]

    // Cancel any in-flight queries to avoid overwriting the optimistic update
    queryClient.cancelQueries({ queryKey, exact: true })

    queryClient.setQueryData(queryKey, (oldData) => {
      if (!oldData || !oldData.pages || oldData.pages.length === 0) {
        return oldData
      }

      const newPages = [...oldData.pages]
      newPages[0] = {
        ...newPages[0],
        data: [newForm, ...newPages[0].data],
      }

      const newData = {
        ...oldData,
        pages: newPages,
        pageParams: oldData.pageParams, // Keep pageParams intact
      }
      return newData
    })
  }

  const update = (workspaceId, updatedForm) => {
    const queryKey = ['forms', 'list', workspaceId]

    // Cancel any in-flight queries to avoid overwriting the optimistic update
    queryClient.cancelQueries({ queryKey, exact: true })

    queryClient.setQueryData(queryKey, (oldData) => {
      if (!oldData) return oldData

      const newPages = oldData.pages.map((page) => ({
        ...page,
        data: page.data.map((form) =>
          form.id === updatedForm.id ? { ...form, ...updatedForm } : form
        ),
      }))

      const newData = { 
        ...oldData, 
        pages: newPages,
        pageParams: oldData.pageParams, // Keep pageParams intact
      }
      return newData
    })
  }

  const remove = (workspaceId, formId) => {
    const queryKey = ['forms', 'list', workspaceId]

    // Cancel any in-flight fetches for this infinite query to prevent
    // them from overwriting our manual cache update when they resolve.
    queryClient.cancelQueries({ queryKey, exact: true })

    queryClient.setQueryData(queryKey, (oldData) => {
      if (!oldData) return oldData

      const newPages = oldData.pages.map((page) => ({
        ...page,
        data: page.data.filter((form) => form.id !== formId),
      }))

      const newData = { 
        ...oldData, 
        pages: newPages,
        pageParams: oldData.pageParams, // Keep pageParams intact
      }
      
      return newData
    })
  }

  return { add, update, remove }
} 