import { useQueryClient, useQuery, useMutation } from '@tanstack/vue-query'
import { authApi } from '~/api/auth'
import { initServiceClients } from '~/composables/useServiceClients.js'

export function useAuth() {
  const nuxtApp = useNuxtApp()
  const queryClient = useQueryClient()
  const { isAuthenticated } = useIsAuthenticated()
  const getAuthFlow = () => import('~/composables/useAuthFlow')
    .then((authFlow) => nuxtApp.runWithContext(() => authFlow.useAuthFlow()))

  // Queries
  const user = (options = {}) => {
    return useQuery({
      queryKey: ['user'],
      queryFn: () => authApi.user.get(options),
      onSuccess: (userData) => {
        initServiceClients(userData)
      },
      enabled: () => isAuthenticated.value,
      ...options
    })
  }

  const updateProfile = (options = {}) => {
    return useMutation({
      mutationFn: (data) => authApi.user.updateProfile(data),
      onSuccess: (updatedUser) => {
        // Optimistically update user cache
        queryClient.setQueryData(['user'], (old) => {
          const newData = old ? { ...old, ...updatedUser } : updatedUser
          // Re-initialize service clients with potentially updated data
          initServiceClients(newData)
          return newData
        })
      },
      ...options
    })
  }

  // Mutations


  const deleteAccount = (options = {}) => {
    return useMutation({
      mutationFn: () => authApi.user.delete(),
      onSuccess: async () => {
        // Clear cached data
        queryClient.clear()
        
        // Handle logout coordination (token clearing + navigation)
        const { handleManualLogout } = await getAuthFlow()
        return handleManualLogout()
      },
      ...options
    })
  }

  const login = (options = {}) => {
    return useMutation({
      mutationFn: (data) => authApi.login(data),
      onSuccess: async (tokenData, variables) => {
        // Cache user data if provided
        if (tokenData.user) {
          queryClient.setQueryData(['user'], tokenData.user)
        } else {
          queryClient.invalidateQueries({ queryKey: ['user'] })
        }
        
        // Invalidate workspaces to refetch with new auth context
        queryClient.invalidateQueries({ queryKey: ['workspaces'] })
        
        // Handle auth flow coordination
        const { handleAuthSuccess } = await getAuthFlow()
        return handleAuthSuccess(tokenData, variables?.source || 'credentials')
      },
      onError: async (error, variables) => {
        const { handleAuthSuccess, handleTwoFactorError } = await getAuthFlow()
        // Check if this is a 2FA requirement (422 with requires_2fa flag)
        const twoFactorData = handleTwoFactorError(error)
        if (twoFactorData) {
          // Handle auth flow coordination (will show 2FA modal)
          return handleAuthSuccess(twoFactorData, variables?.source || 'credentials')
        }
        
        // This is a real error, let it propagate
        throw error
      },
      ...options
    })
  }

  const register = (options = {}) => {
    return useMutation({
      mutationFn: (data) => authApi.register(data),
      onSuccess: async (tokenData, variables) => {
        // Cache user data if provided
        if (tokenData.user) {
          queryClient.setQueryData(['user'], tokenData.user)
        } else {
          queryClient.invalidateQueries({ queryKey: ['user'] })
        }
        
        // Invalidate workspaces to refetch with new auth context
        queryClient.invalidateQueries({ queryKey: ['workspaces'] })
        
        // Handle auth flow coordination (includes AppSumo license handling)
        const { handleAuthSuccess } = await getAuthFlow()
        return handleAuthSuccess(tokenData, variables?.source, true)
      },
      ...options
    })
  }

  const logout = (options = {}) => {
    return useMutation({
      mutationFn: () => authApi.logout(),
      onSuccess: async () => {
        // Clear cached data
        queryClient.clear()
        
        // Handle manual logout coordination (token clearing + navigation)
        const { handleManualLogout } = await getAuthFlow()
        return handleManualLogout()
      },
      onError: async (error) => {
        console.error(error)
        // Even if logout API fails, clear local state
        queryClient.clear()
        
        // Handle manual logout coordination (token clearing + navigation)
        const { handleManualLogout } = await getAuthFlow()
        return handleManualLogout()
      },
      ...options
    })
  }

  const oauthCallback = (options = {}) => {
    return useMutation({
      mutationFn: ({ provider, data }) => authApi.oauth.callback(provider, data),
      onSuccess: async (response) => {
        // Cache user data if provided
        if (response.user) {
          queryClient.setQueryData(['user'], response.user)
        } else {
          queryClient.invalidateQueries({ queryKey: ['user'] })
        }
        
        // Invalidate workspaces to refetch with new auth context
        queryClient.invalidateQueries({ queryKey: ['workspaces'] })
        
        // Handle auth flow coordination (token handling done there)
        const { handleAuthSuccess } = await getAuthFlow()
        return handleAuthSuccess(response, 'oauth', response.new_user)
      },
      ...options
    })
  }

  const invalidateUser = () => {
    return queryClient.invalidateQueries(['user'])
  }

  return {
    // Queries
    user,
    
    // Mutations
    login,
    register,
    updateProfile,
    deleteAccount,
    logout,
    oauthCallback,
    
    // Utilities
    invalidateUser
  }
}
