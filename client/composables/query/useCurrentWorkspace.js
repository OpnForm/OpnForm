import { computed, watch } from 'vue'


/**
 * Dedicated composable for reactive current workspace state
 * Separates current workspace logic from CRUD operations
 */
export function useCurrentWorkspace() {
  const appStore = useAppStore()
  const route = useRoute()
  const router = useRouter()
  const { list } = useWorkspaces()
  const { isAuthenticated } = useIsAuthenticated()
  
  const workspacesQuery = list({
    enabled: () => isAuthenticated.value
  })
  
  // Watch for workspaces data and apply priority: query param > cookie > first workspace
  watch(
    () => workspacesQuery.data.value,
    (workspaces) => {
      if (!workspaces) {
        return
      }

      // Priority 1: workspace_id query param
      const requestedId = route.query.workspace_id
      if (requestedId) {
        const match = workspaces.find(ws => String(ws.id) === String(requestedId))
        if (match) {
          appStore.setCurrentId(match.id)
          // Clear workspace_id query param after switching to avoid blocking future switches
          const newQuery = { ...route.query }
          delete newQuery.workspace_id
          router.replace({ query: newQuery })
          return
        }
      }

      // Priority 2: existing cookie value (currentId)
      const currentWorkspaceExists = appStore.currentId && workspaces.some(ws => ws.id === appStore.currentId)

      if (workspaces.length === 0) {
        appStore.setCurrentId(null)
      } else if (!currentWorkspaceExists) {
        // Priority 3: first available workspace
        appStore.setCurrentId(workspaces[0].id)
      }
    },
    { immediate: true }
  )

  // Watch for query param changes after initial load
  watch(
    () => route.query.workspace_id,
    (requestedId) => {
      const workspaces = workspacesQuery.data.value
      if (!requestedId || !workspaces) return
      const match = workspaces.find(ws => String(ws.id) === String(requestedId))
      if (match && appStore.currentId !== match.id) {
        appStore.setCurrentId(match.id)
      }
    }
  )
  
  // Reactive current workspace - combines store state with query data
  const current = computed(() => {
    const currentId = appStore.currentId
    const workspaces = workspacesQuery.data.value
    
    if (!currentId || !workspaces) {
      return null
    }
    
    return workspaces.find(workspace => workspace.id === currentId) || null
  })
  
  // Derived state for easier consumption
  const isLoading = computed(() => {
    return workspacesQuery.isLoading.value && !workspacesQuery.data.value
  })
  
  const isError = computed(() => {
    return workspacesQuery.isError.value
  })
  
  const error = computed(() => {
    return workspacesQuery.error.value
  })
  
  // Helper to check if user has a current workspace
  const hasWorkspace = computed(() => {
    return !!current.value
  })
  
  // Helper to get current workspace ID
  const currentId = computed(() => {
    return appStore.currentId
  })
  
  // Helper to switch workspace (delegates to store)
  const switchTo = (workspaceId) => {
    appStore.setCurrentId(workspaceId)
  }
  
  return {
    // Primary state
    current,
    currentId,
    
    // Status indicators  
    isLoading,
    isError,
    error,
    hasWorkspace,
    
    // Actions
    switchTo,
  }
} 