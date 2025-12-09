import { useQueryClient, useQuery, useMutation } from '@tanstack/vue-query'
import { oidcApi } from '~/api/oidc'

export function useOidcConnections(workspaceId) {
  const queryClient = useQueryClient()

  // Resolve workspaceId value (handles both refs and plain values)
  const workspaceIdValue = computed(() => {
    return typeof workspaceId === 'function' ? workspaceId.value : workspaceId?.value ?? workspaceId
  })

  // Queries
  const connections = (options = {}) => {
    return useQuery({
      queryKey: ['oidc-connections', workspaceIdValue],
      queryFn: () => {
        const id = workspaceIdValue.value
        return id 
          ? oidcApi.list(id, options)
          : oidcApi.listGlobal(options)
      },
      enabled: computed(() => !!workspaceIdValue.value || workspaceIdValue.value === null),
      ...options
    })
  }

  const connection = (connectionId, options = {}) => {
    return useQuery({
      queryKey: ['oidc-connections', workspaceIdValue, connectionId],
      queryFn: () => {
        const id = workspaceIdValue.value
        return id
          ? oidcApi.get(id, connectionId)
          : oidcApi.getGlobal(connectionId)
      },
      enabled: computed(() => !!connectionId && (!!workspaceIdValue.value || workspaceIdValue.value === null)),
      ...options
    })
  }

  // Mutations - following useWorkspaces.js pattern
  const create = (options = {}) => {
    return useMutation({
      mutationFn: (data) => {
        const id = workspaceIdValue.value
        return id
          ? oidcApi.create(id, data)
          : oidcApi.createGlobal(data)
      },
      onSuccess: (newConnection) => {
        const queryKey = ['oidc-connections', workspaceIdValue]
        
        // Cache individual connection
        queryClient.setQueryData(['oidc-connections', workspaceIdValue, newConnection.id], newConnection)
        
        // Optimistically update list cache
        queryClient.setQueryData(queryKey, (old) => {
          if (!old) return [newConnection]
          if (!Array.isArray(old)) return old
          return [...old, newConnection]
        })
      },
      ...options
    })
  }

  const update = (connectionId, options = {}) => {
    return useMutation({
      mutationFn: (data) => {
        const id = workspaceIdValue.value
        return id
          ? oidcApi.update(id, toValue(connectionId), data)
          : oidcApi.updateGlobal(toValue(connectionId), data)
      },
      onSuccess: (updatedConnection) => {
        const connId = toValue(connectionId)
        const queryKey = ['oidc-connections', workspaceIdValue]
        
        // Update individual connection cache
        queryClient.setQueryData(['oidc-connections', workspaceIdValue, connId], updatedConnection)
        
        // Optimistically update list cache
        queryClient.setQueryData(queryKey, (old) => {
          if (!Array.isArray(old)) return old
          return old.map(conn => 
            conn.id === connId ? { ...conn, ...updatedConnection } : conn
          )
        })
      },
      ...options
    })
  }

  const remove = (options = {}) => {
    return useMutation({
      mutationFn: (connectionId) => {
        const id = workspaceIdValue.value
        return id
          ? oidcApi.delete(id, connectionId)
          : oidcApi.deleteGlobal(connectionId)
      },
      onSuccess: (_, deletedConnectionId) => {
        const queryKey = ['oidc-connections', workspaceIdValue]
        
        // Remove from individual cache
        queryClient.removeQueries({ queryKey: ['oidc-connections', workspaceIdValue, deletedConnectionId] })
        
        // Remove from list cache
        queryClient.setQueryData(queryKey, (old) => {
          if (!Array.isArray(old)) return old
          return old.filter(conn => conn.id !== deletedConnectionId)
        })
      },
      ...options
    })
  }

  const invalidate = () => {
    queryClient.invalidateQueries({ queryKey: ['oidc-connections', workspaceIdValue] })
  }

  return {
    // Queries
    connections,
    connection,
    
    // Mutations
    create,
    update,
    remove,
    
    // Utilities
    invalidate,
  }
}

