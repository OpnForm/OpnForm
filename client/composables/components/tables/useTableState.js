import { computed } from 'vue'
import { useTableColumnPreferences } from './useTableColumnPreferences'
import debounce from 'debounce'

// Provides a composable for managing the state of a table component, including column visibility, pinning, sizing, and wrapping.
// It synchronizes table state with user preferences (such as column order and visibility) and supports dynamic updates based on form structure and workspace context.

export function useTableState(form, withActions = false) {
  // Internal preferences helper (hidden from consumers)
  const columnPreferences = useTableColumnPreferences(
    computed(() => form.value?.id || form.value?.slug)
  )

  const { getColumnPreference, setColumnPreference } = columnPreferences

  /* -------------------------------------------------------------------------- */
  /*                               Column config                               */
  /* -------------------------------------------------------------------------- */

  // Computed column configurations (base definition for every column)
  const columnConfigurations = computed(() => {
    try {
      if (!form.value?.properties || !Array.isArray(form.value.properties)) return []

      const baseColumns = form.value.properties
        .filter((field) => {
          return field.type && typeof field.type === 'string' && !field.type.startsWith('nf-')
        })
        .map(col => {
          const { columns: matrixColumns, ...rest } = col
          return {
            ...rest,
            ...(col.type === 'matrix' && { matrix_columns: matrixColumns }),
            id: col.id,
            accessorKey: col.id,
            header: col.name,
            isRemoved: false,
            enableResizing: true,
            minSize: 100,
            maxSize: 500,
          }
        })

       // Add removed properties
       if (form.value?.removed_properties) {
         form.value.removed_properties.forEach(property => {
           const { columns: matrixColumns, ...rest } = property
           baseColumns.push({
             ...(property.type === 'matrix' ? { ...rest, matrix_columns: matrixColumns } : { ...rest }),
             id: property.id,
             accessorKey: property.id,
             header: property.name,
             isRemoved: true,
             enableResizing: true,
             minSize: 100,
             maxSize: 500,
           })
         })
       }

       // Add created_at column if not present
       if (!baseColumns.find(property => property.id === 'created_at')) {
         baseColumns.push({
           id: 'created_at',
           accessorKey: 'created_at',
           header: 'Created at',
           type: 'date',
           enableResizing: true,
           minSize: 100,
           maxSize: 500,
         })
       }

       return baseColumns
    } catch (error) {
      console.error('Error in columnConfigurations computed:', error)
      return []
    }
  })

  /* -------------------------------------------------------------------------- */
  /*                       Writable computed bridges (reactive)                 */
  /* -------------------------------------------------------------------------- */

  // Column visibility
  const columnVisibility = computed({
    get() {
      // Establish dependency on the entire preferences object
      const prefs = columnPreferences.preferences.value
      const visibility = {}
      const configCols = columnConfigurations.value || []
      configCols.forEach(col => {
        const pref = prefs.columns[col.id] || {}
        // Use preference if set, otherwise default: visible for regular columns, hidden for removed columns
        const visible = pref.visible !== null && pref.visible !== undefined ? pref.visible : !col.isRemoved
        visibility[col.id] = visible
      })
      return visibility
    },
    set(newVisibility) {
      Object.entries(newVisibility).forEach(([columnId, visible]) => {
        const pref = getColumnPreference(columnId)
        const currentVisible = pref.visible !== null && pref.visible !== undefined 
          ? pref.visible 
          : !columnConfigurations.value.find(c => c.id === columnId)?.isRemoved
        if (currentVisible !== visible) {
          setColumnPreference(columnId, { visible })
        }
      })
    }
  })

  // Column pinning
  const columnPinning = computed({
    get() {
      const prefs = columnPreferences.preferences.value
      const pinning = { left: ['select'], right: ['actions'] }
      const configCols = columnConfigurations.value || []
      configCols.forEach(col => {
        const pref = prefs.columns[col.id] || {}
        // Only allow left pinning for regular columns
        if (pref.pinned === 'left') pinning.left.push(col.id)
        // Actions column is always pinned right, other columns cannot be pinned right
      })
      return pinning
    },
    set(newPinning) {
      const { left = ['select'] } = newPinning || {}
      const configCols = columnConfigurations.value || []

      // First, clear pinning for all applicable columns (except actions)
      configCols.forEach(col => {
        if (['actions', 'select'].includes(col.id)) return
        setColumnPreference(col.id, { pinned: false })
      })

      // Apply new left pinning only
      left.forEach(id => {
        if (['actions','select'].includes(id)) return
        setColumnPreference(id, { pinned: 'left' })
      })
      // Note: actions column pinning is handled automatically in the getter
    }
  })

  // Column sizing (table column resize)
  const columnSizing = computed({
    get() {
      const savedSizing = columnPreferences.preferences.value.globalSizing
      if (savedSizing && typeof savedSizing === 'object' && Object.keys(savedSizing).length > 0) {
        // Ensure all columns have a size, fallback to 200 if missing
        const sizing = {}
        for (const col of tableColumns.value) {
          sizing[col.id] = savedSizing[col.id] ?? 200
        }
        return sizing
      }

      // No saved sizing, use default 200 for all columns
      const defaultSizing = {}
      for (const col of tableColumns.value) {
        if (col.id === 'actions') {
          defaultSizing[col.id] = 80
        } else {
          defaultSizing[col.id] = 200
        }
      }
      return defaultSizing
    },
    set(newSizing) {
      columnPreferences.setColumnSizing(newSizing)
    }
  })

  // Debounced resize handler to avoid excessive localStorage writes
  const debouncedSetColumnSizing = debounce((newSizing) => {
    columnPreferences.setColumnSizing(newSizing)
  }, 50) // 150ms debounce

  // Handle individual column resize - simple preference saving
  const handleColumnResize = (columnId, size) => {
    size = Math.min(Math.max(size, 80), 700)
    const currentSizing = columnSizing.value || {}
    const newSizing = {
      ...currentSizing,
      [columnId]: size
    }
    
    // Update the reactive state immediately for smooth UX
    columnSizing.value = newSizing
    
    // Save to preferences with debounce
    debouncedSetColumnSizing(newSizing)
  }



  // Column wrapping (read-only)
  const columnWrapping = computed(() => {
    const prefs = columnPreferences.preferences.value
    const wrapping = {}
    const configCols = columnConfigurations.value || []
    configCols.forEach(col => {
      const pref = prefs.columns[col.id] || {}
      wrapping[col.id] = pref.wrapped ?? false
    })
    return wrapping
  })

  /* -------------------------------------------------------------------------- */
  /*                              Column ordering                               */
  /* -------------------------------------------------------------------------- */

  // Column order for TanStack Table (array of column IDs)
  const columnOrder = computed({
    get() {
      try {
        // Return all column IDs in the correct order
        const cols = tableColumns.value || []
        return cols.map(col => col.id)
      } catch (error) {
        console.error('Error in columnOrder computed:', error)
        return []
      }
    },
    set(newOrder) {
      try {
        // newOrder is an array of column IDs
        // Filter out 'select' and 'actions' columns as they're managed separately
        const dataColumnIds = newOrder.filter(id => !['select', 'actions'].includes(id))
        
        // Update preferences for each column based on the new order
        dataColumnIds.forEach((id, index) => {
          columnPreferences.setColumnPreference(id, { order: index })
        })
        
        // Give high order numbers to columns not in the new order
        const allColumnIds = columnConfigurations.value.map(c => c.id)
        allColumnIds.forEach(id => {
          if (!dataColumnIds.includes(id)) {
            columnPreferences.setColumnPreference(id, { order: 9999 })
          }
        })
      } catch (error) {
        console.error('Error in columnOrder setter:', error)
      }
    }
  })

  // Helper function to set a specific column to a specific index (used by drag-and-drop)
  const setColumnOrder = (columnId, newIndex) => {
    try {
      // Get current column order
      const currentOrder = columnOrder.value
      
      // Find the column in the current order
      const currentIndex = currentOrder.indexOf(columnId)
      if (currentIndex === -1) return
      
      // Create new order by moving the column
      const newOrder = [...currentOrder]
      newOrder.splice(currentIndex, 1)
      newOrder.splice(newIndex, 0, columnId)
      
      // Update via columnOrder setter (triggers TanStack Table update)
      columnOrder.value = newOrder
    } catch (error) {
      console.error('Error in setColumnOrder:', error)
    }
  }

  // Ordered columns for display (read-only getter)
  const orderedColumns = computed(() => {
    try {
      const prefs = columnPreferences.preferences.value
      const configCols = columnConfigurations.value || []
      const columns = Array.isArray(configCols) ? [...configCols] : []

      // Sort by order preference, then by original position
      columns.sort((a, b) => {
        const prefA = prefs.columns[a.id] || {}
        const prefB = prefs.columns[b.id] || {}

        const orderA = prefA.order ?? 9999
        const orderB = prefB.order ?? 9999

        if (orderA !== orderB) {
          return orderA - orderB
        }

        // Fallback to original order
        return configCols.indexOf(a) - configCols.indexOf(b)
      })

      return columns
    } catch (error) {
      console.error('Error in orderedColumns computed:', error)
      return []
    }
  })

  // Final array of columns to be passed to the table component
  const tableColumns = computed(() => {
    try {
      // Ensure we have a valid array to work with  
      const cols = orderedColumns.value && Array.isArray(orderedColumns.value) ? [...orderedColumns.value] : []

      // Add status column if needed
      if (form.value?.is_pro && (form.value.enable_partial_submissions ?? false)) {
        cols.push({
          id: 'status',
          accessorKey: 'status',
          header: 'Status',
          type: 'status',
          enableColumnFilter: true,
          filterFn: 'equals',
          enableResizing: true,
          minSize: 100,
          maxSize: 500,
        })
      }

      // Add IP address column if needed
      if (form.value?.is_pro && (form.value.enable_ip_tracking ?? false)) {
        cols.push({
          id: 'ip_address',
          accessorKey: 'ip_address',
          header: 'IP Address',
          type: 'ip_address',
          enableResizing: true,
          minSize: 100,
          maxSize: 500,
        })
      }

      // Add actions columns
      if (import.meta.client && withActions) {
        cols.unshift({
          id: 'select',
          accessorKey: 'select',
          header: '',
          enableResizing: false,
          size: 40,
          meta: {
            class: {
              th: 'bg-transparent',
              td: 'backdrop-blur-xs bg-white/70'
            }
          }
        })

        cols.push({
          id: 'actions',
          accessorKey: 'actions',
          header: '',
          enableResizing: false,
          size: 80,
          meta: {
            class: {
              th: 'bg-transparent',
              td: 'backdrop-blur-xs bg-white/70'
            }
          }
        })
      }

      return cols ?? []
    } catch (error) {
      console.error('Error in tableColumns computed:', error)
      return []
    }
  })

  /* -------------------------------------------------------------------------- */
  /*                              Helper functions                              */
  /* -------------------------------------------------------------------------- */

  // Toggle column visibility
  const toggleColumnVisibility = (columnId) => {
    const currentVisibility = columnVisibility.value[columnId]
    columnVisibility.value = {
      ...columnVisibility.value,
      [columnId]: !currentVisibility
    }
  }

  // Toggle column wrapping
  const toggleColumnWrapping = (columnId) => {
    setColumnPreference(columnId, { wrapped: !(columnWrapping.value[columnId] || false) })
  }

  // Toggle column pinning
  const toggleColumnPin = (columnId) => {
    const pref = getColumnPreference(columnId) || {}
    
    if (pref.pinned === 'left') {
      // If currently pinned, just unpin it
      setColumnPreference(columnId, { pinned: false })
    } else {
      // If not currently pinned, first unpin all other columns, then pin this one
      const configCols = columnConfigurations.value || []
      
      // Clear all existing pins
      configCols.forEach(col => {
        if (['actions', 'select'].includes(col.id) && col.id !== columnId) {
          setColumnPreference(col.id, { pinned: false })
        }
      })
      
      // Pin the target column and make it visible
      setColumnPreference(columnId, { pinned: 'left', visible: true })
    }
  }

  /* -------------------------------------------------------------------------- */
  /*                              Public API                                   */
  /* -------------------------------------------------------------------------- */

  return {
    // Columns
    tableColumns,
    orderedColumns,
    setColumnOrder,

    // Preferences (writable computeds)
    columnVisibility,
    columnPinning,
    columnWrapping,
    columnSizing,
    columnOrder, // TanStack Table column order state
    
    // Preference helpers (for components)
    getColumnPreference,
    setColumnPreference,
    resetPreferences: columnPreferences.resetPreferences,
    resetColumn: columnPreferences.resetColumn,
    toggleColumnVisibility: toggleColumnVisibility,
    toggleColumnWrapping,
    toggleColumnPin: toggleColumnPin,
    handleColumnResize,
  }
} 