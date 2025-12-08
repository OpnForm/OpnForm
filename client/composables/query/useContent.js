import { useQuery, useQueryClient } from '@tanstack/vue-query'
import { contentApi } from '~/api/content'
import { computed, toValue } from 'vue'

export function useContent() {
  const queryClient = useQueryClient()

  // Fonts
  const fonts = {
    list: (options = {}) => {
      return useQuery({
        queryKey: ['content', 'fonts', 'list'],
        queryFn: () => contentApi.fonts.list(options),
        staleTime: 10 * 60 * 1000, // 10 minutes - fonts don't change often
        ...options
      })
    }
  }

  // Unsplash
  const unsplash = {
    list: (searchTerm = '', options = {}) => {
      const isFeatureEnabled = useFeatureFlag('services.unsplash', false)
      
      return useQuery({
        queryKey: ['content', 'unsplash', 'list', searchTerm || 'default'],
        queryFn: () => {
          const term = toValue(searchTerm)
          const queryOptions = term ? { query: { term } } : {}
          return contentApi.unsplash.list(queryOptions)
        },
        enabled: computed(() => {
          return isFeatureEnabled && (options.enabled !== false)
        }),
        staleTime: 5 * 60 * 1000, // 5 minutes
        ...options
      })
    },

  }

  // Invalidate all content queries
  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['content'] })
  }

  // Invalidate fonts
  const invalidateFonts = () => {
    queryClient.invalidateQueries({ queryKey: ['content', 'fonts'] })
  }

  // Invalidate unsplash
  const invalidateUnsplash = () => {
    queryClient.invalidateQueries({ queryKey: ['content', 'unsplash'] })
  }

  return {
    fonts,
    unsplash,
    invalidate,
    invalidateFonts,
    invalidateUnsplash
  }
}

