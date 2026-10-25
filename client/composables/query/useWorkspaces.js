import { useQuery, useQueryClient, useMutation } from '@tanstack/vue-query'
import { workspaceApi } from '~/api'

export function useWorkspaces() {
  const queryClient = useQueryClient()
  const { isAuthenticated } = useIsAuthenticated()

  // Queries
  const list = (options = {}) => {
    return useQuery({
      queryKey: ['workspaces', 'list'],
      queryFn: () => { 
        return workspaceApi.list()
      },
      enabled: () => isAuthenticated.value,
      ...options
    })
  }

  const listWithSuspense = (options = {}) => {
    return useQuery({
      queryKey: ['workspaces', 'list'],
      queryFn: () => workspaceApi.list(),
      staleTime: 5 * 60 * 1000, // 5 minutes
      suspense: true,
      onSuccess: (data) => {
        data?.forEach((workspace) => {
          queryClient.setQueryData(['workspaces', workspace.id], workspace)
        })
      },
      ...options
    })
  }

  const detail = (workspaceId, options = {}) => {
    return useQuery({
      queryKey: ['workspaces', workspaceId],
      queryFn: () => workspaceApi.get(toValue(workspaceId)),
      enabled: computed(() => !!toValue(workspaceId)),
      ...options
    })
  }

  // Mutations with manual cache updates
  const create = (options = {}) => {
    return useMutation({
      mutationFn: (data) => workspaceApi.create(data),
      onSuccess: (response) => {
        const newWorkspace = response.workspace
        // Cache the new item individually
        queryClient.setQueryData(['workspaces', newWorkspace.id], newWorkspace)
        
        // Add to list cache
        queryClient.setQueryData(['workspaces', 'list'], (old) => {
          if (!old) return [newWorkspace]
          if (!Array.isArray(old)) return old
          return [...old, newWorkspace]
        })
      },
      ...options
    })
  }

  const update = (workspaceId, options = {}) => {
    return useMutation({
      mutationFn: (data) => workspaceApi.update(toValue(workspaceId), data),
      onSuccess: (response) => {
        const updatedWorkspace = response.workspace
        const id = updatedWorkspace.id
        // Update individual item cache
        queryClient.setQueryData(['workspaces', id], updatedWorkspace)
        
        // Update in list cache
        queryClient.setQueryData(['workspaces', 'list'], (old) => {
          if (!Array.isArray(old)) return old
          return old.map(workspace => 
            workspace.id === id ? { ...workspace, ...updatedWorkspace } : workspace
          )
        })
      },
      ...options
    })
  }

  const remove = (options = {}) => {
    return useMutation({
      mutationFn: (id) => workspaceApi.delete(id),
      onSuccess: (_, deletedId) => {
        // Remove from individual cache
        queryClient.removeQueries({ queryKey: ['workspaces', deletedId] })
        
        // Remove from list cache
        queryClient.setQueryData(['workspaces', 'list'], (old) => {
          if (!Array.isArray(old)) return old
          return old.filter(workspace => workspace.id !== deletedId)
        })
      },
      ...options
    })
  }

  const leave = (options = {}) => {
    return useMutation({
      mutationFn: (workspaceId) => workspaceApi.leave(workspaceId),
      onSuccess: () => {
        invalidate()
      },
      ...options
    })
  }

  // Custom domains mutation
  const updateCustomDomains = (workspaceId, options = {}) => {
    return useMutation({
      mutationFn: ({ workspaceId: requestedId, ...data }) => workspaceApi.customDomains.update(requestedId ?? toValue(workspaceId), data),
      onSuccess: (updatedWorkspace) => {
        // Update individual workspace cache with custom domain info
        queryClient.setQueryData(['workspaces', updatedWorkspace.id], (old) => {
          if (!old) return updatedWorkspace
          return { ...old, ...updatedWorkspace }
        })
        queryClient.setQueryData(['workspaces', 'list'], (old) => {
          if (!Array.isArray(old)) return old
          return old.map(workspace => workspace.id === updatedWorkspace.id ? { ...workspace, ...updatedWorkspace } : workspace)
        })
      },
      ...options
    })
  }

  const customDomainVerification = () => useMutation({
    mutationFn: ({ workspaceId, domain }) => workspaceApi.customDomains.verification(workspaceId, domain),
  })

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['workspaces'] })
  }

  const invalidateAll = () => {
    queryClient.invalidateQueries({ queryKey: ['workspaces'] })
  }

  // Helper to get workspace from cache by ID
  const getWorkspaceById = (id) => {
    // Note: this is a non-reactive lookup
    return queryClient.getQueryData(['workspaces', id])
  }

  return {
    // Queries
    list,
    listWithSuspense,
    detail,
    
    // Mutations
    create,
    update, 
    remove,
    leave,
    updateCustomDomains,
    customDomainVerification,
    
    // Utilities
    invalidate,
    invalidateAll,
    getWorkspaceById,
  }
}
