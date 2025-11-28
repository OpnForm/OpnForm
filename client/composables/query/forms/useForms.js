import { useQueryClient, useQuery, useMutation } from '@tanstack/vue-query'
import { toValue } from 'vue'
import { formsApi } from '~/api/forms'
import { useIsAuthenticated } from '~/composables/useAuthFlow'
import { useFormsListCache } from './useFormsList'

export function useForms() {
  const queryClient = useQueryClient()
  const { isAuthenticated } = useIsAuthenticated()
  const formsListCache = useFormsListCache()

  const detail = (slug, options = {}) => {
    const { usePrivate = false, ...queryOptions } = options
    
    return useQuery({
      queryKey: ['forms', 'slug', slug],
      queryFn: () => {
        if (usePrivate && isAuthenticated.value) {
          return formsApi.get(slug, options)
        }
        return formsApi.publicGet(slug, options)
      },
      enabled: !!slug,
      onSuccess: (form) => {
        if (form) {
          queryClient.setQueryData(['forms', form.id], form)
        }
      },
      ...queryOptions,
    })
  }

  const detailById = (id, options = {}) => {
    const { usePrivate = false, ...queryOptions } = options
    
    return useQuery({
      queryKey: ['forms', id],
      queryFn: () => {
        if (usePrivate && isAuthenticated.value) {
          return formsApi.getById(id, options)
        }
        return formsApi.publicGetById(id, options)
      },
      enabled: !!id,
      onSuccess: (form) => {
        if (form) {
          queryClient.setQueryData(['forms', 'slug', form.slug], form)
        }
      },
      ...queryOptions,
    })
  }

  // Form Mutations
  const create = (options = {}) => {    
    return useMutation({
      mutationFn: (data) => formsApi.create(data),
      onSuccess: (response) => {
        const newForm = response.form
        formsListCache.add(newForm.workspace_id, newForm)
        // Cache the new form
        queryClient.setQueryData(['forms', newForm.id], newForm)
        queryClient.setQueryData(['forms', 'slug', newForm.slug], newForm)
      },
      ...options
    })
  }

  const update = (formId, options = {}) => {
    return useMutation({
      mutationFn: (data) => formsApi.update(toValue(formId), data),
      onSuccess: (updatedForm) => {
      const form = updatedForm.form
      const currentFormId = toValue(formId)

      // Update individual form cache
      queryClient.setQueryData(['forms', currentFormId], form)
      if (form.slug) {
        queryClient.setQueryData(['forms', 'slug', form.slug], form)
      }
      
      // Update in workspace lists
      formsListCache.update(form.workspace_id, form)
      },
      ...options
    })
  }

  const remove = (options = {}) => {
    return useMutation({
      mutationFn: (id) => formsApi.delete(id),
      onSuccess: (_, deletedId) => {      
      const deletedForm = queryClient.getQueryData(['forms', deletedId])
      if (!deletedForm) return

      invalidateDetail(deletedForm)

      // Remove from workspace lists
      formsListCache.remove(deletedForm.workspace_id, deletedId)
      },
      ...options
    })
  }

  const duplicate = (options = {}) => {
    return useMutation({
      mutationFn: (id) => formsApi.duplicate(id),
      onSuccess: (response) => {
      const duplicatedForm = response.new_form
      // Add to workspace forms list
      formsListCache.add(duplicatedForm.workspace_id, duplicatedForm)
      // Cache the duplicated form
      queryClient.setQueryData(['forms', duplicatedForm.id], duplicatedForm)
      queryClient.setQueryData(['forms', 'slug', duplicatedForm.slug], duplicatedForm)
      },
      ...options
    })
  }

  const regenerateLink = (options = {}) => {
    return useMutation({
      mutationFn: ({ id, option }) => formsApi.regenerateLink(id, option),
      onSuccess: (updatedForm, { id }) => {
      queryClient.setQueryData(['forms', id], (old) => {
        return old ? { ...old, ...updatedForm } : updatedForm
      })
      if (updatedForm.slug) {
        queryClient.setQueryData(['forms', 'slug', updatedForm.slug], (old) => {
          return old ? { ...old, ...updatedForm } : updatedForm
        })
      }
      },
      ...options
    })
  }

  const updateWorkspace = (options = {}) => {
    return useMutation({
      mutationFn: ({ id, workspaceId, data }) => formsApi.updateWorkspace(id, workspaceId, data),
      onSuccess: (updatedForm, { id, workspaceId: newWorkspaceId }) => {
      const oldForm = queryClient.getQueryData(['forms', id])
      const oldWorkspaceId = oldForm?.workspace_id
      
      // Update form cache
      queryClient.setQueryData(['forms', id], updatedForm)
      if (updatedForm.slug) {
        queryClient.setQueryData(['forms', 'slug', updatedForm.slug], updatedForm)
      }
      
      // Remove from old workspace list
      if (oldWorkspaceId) {
        formsListCache.remove(oldWorkspaceId, id)
      }

      // Add to new workspace list
      formsListCache.add(newWorkspaceId, updatedForm)
      },
      ...options
    })
  }

  // Asset Upload Mutation
  const uploadAsset = (options = {}) => {
    return useMutation({
      mutationFn: (data) => formsApi.assets.upload(data, options),
      ...options
    })
  }

  // Zapier Webhook Mutations
  const createZapierWebhook = (options = {}) => {
    return useMutation({
      mutationFn: (data) => formsApi.zapier.store(data),
      ...options
    })
  }

  const deleteZapierWebhook = (options = {}) => {
    return useMutation({
      mutationFn: (id) => formsApi.zapier.delete(id),
      ...options
    })
  }

  const invalidateAll = () => {
    queryClient.removeQueries({ queryKey: ['forms', 'list'], exact: false })
  }

  const invalidateDetail = (form) => {
    if (form.id) {
      queryClient.removeQueries({ queryKey: ['forms', form.id] })
    }
    if (form.slug) {
      queryClient.removeQueries({ queryKey: ['forms', 'slug', form.slug] })
    }
  }

  return {
    // Queries
    detail,
    detailById,

    // Form Mutations
    create,
    update,
    remove,
    duplicate,
    regenerateLink,
    updateWorkspace,
    
    // Other Mutations
    uploadAsset,
    createZapierWebhook,
    deleteZapierWebhook,
    
    // Utilities
    invalidateAll,
    invalidateDetail
  }
} 