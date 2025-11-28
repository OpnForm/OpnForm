import { useStorage } from '@vueuse/core'
import { computed, toValue } from 'vue'

export function useTableColumnPreferences(formId) {
  // Generate storage key
  const storageKey = computed(() => {
    if (import.meta.server) return ''
    return `table-prefs-${toValue(formId)}`
  })

  // Simplified preferences structure - flat mapping of columnId -> preferences
  const defaultPreferences = {
    columns: {}, // columnId -> { visible, pinned, wrapped, order, size }
    globalSizing: {}, // TanStack table sizing state
  }

  // Main reactive storage
  const preferences = useStorage(
    storageKey,
    defaultPreferences,
    import.meta.server ? undefined : localStorage,
    {
      mergeDefaults: true,
      serializer: {
        read: (v) => {
          try {
            return JSON.parse(v) || defaultPreferences
          } catch {
            return defaultPreferences
          }
        },
        write: (v) => JSON.stringify(v)
      }
    }
  )

  // Column preference helpers
  const getColumnPreference = (columnId) => {
    if (import.meta.server) {
      return {
        visible: null, // null means use default
        pinned: false,
        wrapped: false,
        order: null,
        size: 200
      }
    }
    
    return preferences.value.columns[columnId] || {
      visible: null,
      pinned: false,
      wrapped: false,
      order: null,
      size: 200
    }
  }

  const setColumnPreference = (columnId, updates) => {
    if (import.meta.server) return
    
    // Create a completely new object to ensure reactivity
    const currentColumns = { ...preferences.value.columns }
    currentColumns[columnId] = {
      ...getColumnPreference(columnId),
      ...updates
    }
    
    preferences.value = {
      ...preferences.value,
      columns: currentColumns
    }
  }

  const setColumnSizing = (newSizing) => {
    if (import.meta.server) return
    preferences.value = {
      ...preferences.value,
      globalSizing: { ...newSizing }
    }
  }

  const resetPreferences = () => {
    if (import.meta.server) return
    preferences.value = { ...defaultPreferences }
  }

  const resetColumn = (columnId) => {
    if (import.meta.server) return
    const newColumns = { ...preferences.value.columns }
    delete newColumns[columnId]
    preferences.value = {
      ...preferences.value,
      columns: newColumns
    }
  }
  
  return {
    preferences: computed(() => preferences.value),
    getColumnPreference,
    setColumnPreference,
    setColumnSizing,
    resetPreferences,
    resetColumn,
  }
} 