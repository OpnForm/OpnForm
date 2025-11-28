import { useQueryClient, useQuery, useMutation } from '@tanstack/vue-query'
import { templatesApi } from '~/api/templates'

export function useTemplates() {
  const queryClient = useQueryClient()

  // Queries
  const list = (options = {}) => {
    return useQuery({
      queryKey: ['templates', 'list', options.params || {}],
      queryFn: () => templatesApi.list(options.params || {}),
      onSuccess: (data) => {
        data?.forEach(template => {
          queryClient.setQueryData(['templates', template.id], template)
          queryClient.setQueryData(['templates', 'slug', template.slug], template)
        })
      },
      ...options
    })
  }

  const detail = (slug, options = {}) => {
    return useQuery({
      queryKey: ['templates', 'slug', slug],
      queryFn: () => templatesApi.get(slug),
      enabled: !!slug,
      onSuccess: (template) => {
        if (template) {
          queryClient.setQueryData(['templates', template.id], template)
        }
      },
      ...options
    })
  }

  const detailById = (id, options = {}) => {
    return useQuery({
      queryKey: ['templates', id],
      queryFn: () => {
        // Since there's no getById in the API, we get from cached list
        const cachedTemplates = queryClient.getQueryData(['templates', 'list'])
        return cachedTemplates?.find(t => t.id === id) || null
      },
      enabled: !!id,
      onSuccess: (template) => {
        if (template) {
          queryClient.setQueryData(['templates', 'slug', template.slug], template)
        }
      },
      ...options
    })
  }

  // Paginated list
  const paginatedList = (page = ref(1), filters = ref({}), options = {}) => {
    return useQuery({
      queryKey: ['templates', 'list', { page: page.value, ...filters.value }],
      queryFn: () => templatesApi.list({ page: page.value, ...filters.value }),
      keepPreviousData: true,
      onSuccess: (data) => {
        data?.data?.forEach(template => {
          queryClient.setQueryData(['templates', template.id], template)
          queryClient.setQueryData(['templates', 'slug', template.slug], template)
        })
      },
      ...options
    })
  }

  // Mutations
  const create = (options = {}) => {
    return useMutation({
      mutationFn: (data) => templatesApi.create(data),
      onSuccess: (newTemplate) => {
      // Update templates list
      queryClient.setQueryData(['templates', 'list'], (old) => {
        if (!old) return [newTemplate]
        if (Array.isArray(old)) return [newTemplate, ...old]
        if (old.data) {
          return {
            ...old,
            data: [newTemplate, ...old.data]
          }
        }
        return old
      })
      // Cache the new template
      queryClient.setQueryData(['templates', newTemplate.id], newTemplate)
      queryClient.setQueryData(['templates', 'slug', newTemplate.slug], newTemplate)
      },
      ...options
    })
  }

  const update = (options = {}) => {
    return useMutation({
      mutationFn: ({ id, data }) => templatesApi.update(id, data),
      onSuccess: (updatedTemplate, { id }) => {
      // Update individual template cache
      queryClient.setQueryData(['templates', id], updatedTemplate)
      if (updatedTemplate.slug) {
        queryClient.setQueryData(['templates', 'slug', updatedTemplate.slug], updatedTemplate)
      }
      
      // Update in templates lists
      queryClient.setQueryData(['templates', 'list'], (old) => {
        if (!old) return old
        if (Array.isArray(old)) {
          return old.map(template => 
            template.id === id ? { ...template, ...updatedTemplate } : template
          )
        }
        if (old.data) {
          return {
            ...old,
            data: old.data.map(template => 
              template.id === id ? { ...template, ...updatedTemplate } : template
            )
          }
        }
        return old
      })
      },
      ...options
    })
  }

  const remove = (options = {}) => {
    return useMutation({
      mutationFn: (id) => templatesApi.delete(id),
      onSuccess: (_, deletedId) => {
      const deletedTemplate = queryClient.getQueryData(['templates', deletedId])
      
      // Remove from all caches
      queryClient.removeQueries({ queryKey: ['templates', deletedId] })
      if (deletedTemplate?.slug) {
        queryClient.removeQueries({ queryKey: ['templates', 'slug', deletedTemplate.slug] })
      }
      
      // Remove from templates lists
      queryClient.setQueryData(['templates', 'list'], (old) => {
        if (!old) return old
        if (Array.isArray(old)) {
          return old.filter(template => template.id !== deletedId)
        }
        if (old.data) {
          return {
            ...old,
            data: old.data.filter(template => template.id !== deletedId)
          }
        }
        return old
      })
      },
      ...options
    })
  }

  const invalidateAll = () => {
    queryClient.invalidateQueries(['templates'])
  }

  const invalidateDetail = (id) => {
    queryClient.invalidateQueries(['templates', id])
  }

  const invalidateList = () => {
    queryClient.invalidateQueries(['templates', 'list'])
  }

  const getTemplateBySlug = (slug) => {
    return queryClient.getQueryData(['templates', 'slug', slug])
  }

  const getTemplateById = (id) => {
    return queryClient.getQueryData(['templates', id])
  }

  return {
    // Queries
    list,
    detail,
    detailById,
    paginatedList,
    
    // Mutations
    create,
    update,
    remove,
    
    // Utilities
    invalidateAll,
    invalidateDetail,
    invalidateList,
    getTemplateBySlug,
    getTemplateById
  }
} 