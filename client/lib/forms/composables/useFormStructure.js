import { computed, toValue } from 'vue'

/**
 * @fileoverview Composable responsible for analyzing and managing the structural aspects of a form,
 * including page breaks, field grouping, page boundaries, and determining field locations.
 */
export function useFormStructure(formConfig, managerState, formData, fieldState) {
  const form = computed(() => toValue(formConfig) || { properties: [] })

  /**
   * Checks if a field is hidden based on centralized field state service.
   */
  const isFieldHidden = (field) => {
    try {
      return !!fieldState.getState(field).hidden
    } catch (e) {
      console.error("Error checking if field is hidden:", field?.id, e)
      return field?.hidden || false // Fallback
    }
  }

  /**
   * Calculates the groups of fields based on non-hidden page breaks.
   * Classic mode: split on visible page breaks and ignore empty pages (groups containing only page-breaks).
   * @returns {Array<Array<Object>>} Nested array where each inner array represents a page.
   */
  const calculateFieldGroups = () => {
    const properties = form.value.properties || []
    if (properties.length === 0) return [[]]

    const groups = []
    let currentGroup = []
    let hasRenderable = false // any non-page-break field in this group

    const pushGroupIfRenderable = () => {
      if (currentGroup.length === 0) return
      if (hasRenderable) groups.push([...currentGroup])
      currentGroup = []
      hasRenderable = false
    }

    properties.forEach((field, index) => {
      currentGroup.push(field)
      if (field.type !== 'nf-page-break') hasRenderable = true

      // Split only on non-hidden page breaks
      if (field.type === 'nf-page-break' && !isFieldHidden(field)) {
        pushGroupIfRenderable()
      }

      // Last item safety
      if (index === properties.length - 1) {
        pushGroupIfRenderable()
      }
    })

    // Ensure at least one group exists
    if (groups.length === 0) {
      groups.push([])
    }

    return groups
  }

  /**
   * Reactive computed property holding the field groups.
   */
  const fieldGroups = computed(calculateFieldGroups)

  /**
   * Reactive computed property holding the total number of pages.
   */
  const pageCount = computed(() => {
    const groups = fieldGroups.value
    const count = groups ? groups.length : 0
    return count
  })

  /**
   * Calculates the start and end indices for each page.
   * @returns {Array<Object>} Array of boundary objects { start, end }.
   */
    const calculatePageBoundaries = () => {
    const properties = form.value.properties || []
    if (properties.length === 0) {
        return [{ start: 0, end: -1 }] // Empty form
    }

    const boundaries = []
    let startIndex = 0
    let visibleBreakFound = false

    properties.forEach((field, index) => {
        // Check if the field is a page break AND it's not hidden
        if (field.type === 'nf-page-break' && !isFieldHidden(field)) {
            visibleBreakFound = true
            // The page ends *at* the page break field
            boundaries.push({ start: startIndex, end: index })
            // The next page starts *after* the page break field
            startIndex = index + 1
        }
    })

    // If no visible page breaks were found, the entire form is a single page
    if (!visibleBreakFound) {
        return [{ start: 0, end: properties.length - 1 }]
    }

    // Add the boundary for the last page if there are fields after the last visible break
    if (startIndex < properties.length) {
        boundaries.push({ start: startIndex, end: properties.length - 1 })
    }
    // If the last field was a visible page break, startIndex will equal properties.length,
    // and we don't add an extra empty page boundary.

    // Safety check: Ensure at least one boundary exists if properties are not empty
    if (boundaries.length === 0 && properties.length > 0) {
         return [{ start: 0, end: properties.length - 1 }]
    }

    return boundaries
    }


  /**
   * Reactive computed property holding the page boundaries.
   */
  const pageBoundaries = computed(calculatePageBoundaries)

  /**
   * Reactive computed property for the page break field ending the current page.
   */
  const currentPageBreak = computed(() => {
    const groups = fieldGroups.value
    if (!managerState || !groups) return null

    const currentPageIndex = managerState.currentPage
    if (currentPageIndex < 0 || currentPageIndex >= groups.length) return null

    const currentPageFields = groups[currentPageIndex] || []
    if (currentPageFields.length === 0) return null

    const lastField = currentPageFields[currentPageFields.length - 1]
    // It's the current page break only if it's a page break and *not hidden*
    return (lastField && lastField.type === 'nf-page-break' && !isFieldHidden(lastField)) ? lastField : null
  })

  /**
   * Reactive computed property for the page break field ending the previous page.
   */
  const previousPageBreak = computed(() => {
    const groups = fieldGroups.value
    if (!managerState || !groups || managerState.currentPage <= 0) return null

    const previousPageIndex = managerState.currentPage - 1
    if (previousPageIndex < 0 || previousPageIndex >= groups.length) return null

    const previousPageFields = groups[previousPageIndex] || []
    if (previousPageFields.length === 0) return null

    const lastField = previousPageFields[previousPageFields.length - 1]
    // It's the previous page break only if it's a page break and *not hidden*
    return (lastField && lastField.type === 'nf-page-break' && !isFieldHidden(lastField)) ? lastField : null
  })

  /**
   * Reactive computed property indicating if the current page is the last one.
   */
  const isLastPage = computed(() => {
    if (managerState?.currentPage === undefined || managerState?.currentPage === null || pageCount.value === undefined) {
      // console.warn('[useFormStructure] isLastPage: Invalid state, returning default true.');
      return true // Default true for safety in simple forms
    }
    const result = managerState.currentPage === pageCount.value - 1
    return result
  })

  /**
   * Reactive computed property checking if current page has a payment block
   */
  const currentPageHasPaymentBlock = computed(() => {
    if (managerState?.currentPage === undefined || managerState?.currentPage === null) return false
    
    return hasPaymentBlock(managerState.currentPage)
  })

  /**
   * Reactive computed property returning the payment block from the current page, if any
   */
  const currentPagePaymentBlock = computed(() => {
    if (managerState?.currentPage === undefined || managerState?.currentPage === null) return undefined
    
    return getPaymentBlock(managerState.currentPage)
  })

  /**
   * Gets the fields for a specific page index.
   * @param {Number} pageIndex - The index of the page.
   * @returns {Array<Object>} Array of field objects for the page.
   */
  const getPageFields = (pageIndex) => {
    const groups = fieldGroups.value
    if (!groups) {
      console.warn("useFormStructure: getPageFields called but fieldGroups is undefined.")
      return []
    }
    return groups[pageIndex] || []
  }

  /**
   * Determines the page index for a given field index within the form properties array.
   * @param {Number} fieldIndex - The index of the field in the form.properties array.
   * @returns {Number} The page index (0-based).
   */
  const getPageForField = (fieldIndex) => {
    // Basic validation for field index
    if (fieldIndex === null || fieldIndex === undefined || 
        typeof fieldIndex !== 'number' || isNaN(fieldIndex) || fieldIndex < 0) {
      console.warn(`Invalid field index passed to getPageForField: ${fieldIndex}`)
      return 0 // Default to first page for invalid indexes
    }

    const properties = form.value.properties || []
    if (properties.length === 0 || fieldIndex >= properties.length) {
      return 0 // Default to first page
    }

    const boundaries = pageBoundaries.value
    if (!boundaries || boundaries.length === 0) return 0

    // If only one boundary (covers all), it's page 0
    if (boundaries.length === 1 && boundaries[0].start === 0) {
        return 0
    }

    for (let i = 0; i < boundaries.length; i++) {
      const { start, end } = boundaries[i]
      if (fieldIndex >= start && fieldIndex <= end) {
        return i
      }
    }

    // Fallback: Should technically not be reached with correct boundaries
    console.warn(`[useFormStructure] getPageForField: Field index ${fieldIndex} not found within calculated boundaries. Returning last page index.`)
    return Math.max(0, boundaries.length - 1)
  }

  /**
   * Checks if a given page contains a payment block.
   * @param {Number} pageIndex - The page index.
   * @returns {Boolean} True if a payment block exists on the page.
   */
  const hasPaymentBlock = (pageIndex) => {
    return getPageFields(pageIndex).some(field => field?.type === 'payment')
  }

  /**
   * Gets the payment block field object from a given page.
   * @param {Number} pageIndex - The page index.
   * @returns {Object|undefined} The payment field object or undefined if not found.
   */
  const getPaymentBlock = (pageIndex) => {
    return getPageFields(pageIndex).find(field => field?.type === 'payment')
  }

   /**
   * Determines the target index in the flat form.properties array for drag/drop operations.
   * @param {number} currentFieldPageIndex - The relative index of the field within the current page's field list.
   * @param {number} selectedFieldIndex - The original flat index of the field being dragged (often not needed here).
   * @param {number} currentPageIndex - The index of the page where the drop occurs.
   * @returns {number} The absolute index in the form.properties array.
   */
    const getTargetDropIndex = (relativeDropIndex, targetPageIndex) => {
      const groups = fieldGroups.value
      if (!groups) return relativeDropIndex // Fallback

      let precedingFields = 0
      for(let i = 0; i < targetPageIndex; i++) {
          precedingFields += groups[i]?.length || 0
      }
      return precedingFields + relativeDropIndex
  }

  /**
   * Sets the current page to the page containing the specified field.
   * @param {Number} fieldIndex - The index of the field in the form.properties array.
   * @returns {Number} The page index that was set.
   */
  const setPageForField = (fieldIndex) => {
    // Get the page index, with additional validation
    const pageIndex = getPageForField(fieldIndex)
    
    // Ensure we have a valid numeric page index
    if (typeof pageIndex !== 'number' || isNaN(pageIndex) || pageIndex < 0) {
      console.warn('[useFormStructure] setPageForField: Invalid page index', pageIndex)
      return
    }
    
    // Update the manager state with validated page index - DON'T USE toValue HERE
    if (managerState && managerState.currentPage !== undefined) {
      managerState.currentPage = pageIndex
    }
    
    return pageIndex
  }

  /**
   * Determines the correct index to insert a new field.
   * Considers selected field index and current page boundaries.
   * @param {Number|null} selectedFieldIndex - The index of the currently selected field in form.properties.
   * @param {Number} currentPageIndex - The current page index.
   * @param {Number|null} [explicitIndex=null] - An explicitly provided insert index.
   * @param {boolean} [insertOnSamePage=false] - If true, ensures insertion happens on the current page, even before a page break.
   * @returns {Number} The calculated index for insertion.
   */
  const determineInsertIndex = (selectedFieldIndex, currentPageIndex, explicitIndex = null, insertOnSamePage = false) => {
    if (explicitIndex !== null && typeof explicitIndex === 'number') {
      return explicitIndex
    }

    if (selectedFieldIndex !== null && selectedFieldIndex !== undefined && selectedFieldIndex >= 0) {
      return selectedFieldIndex + 1
    }

    const properties = form.value.properties || []
    if (properties.length === 0) {
      return 0
    }

    const boundaries = pageBoundaries.value
    // Use managerState directly without toValue
    const pageIdx = currentPageIndex ?? managerState?.currentPage ?? 0 // Use provided or state page index

    if (!boundaries || boundaries.length === 0 || pageIdx >= boundaries.length || pageIdx < 0) {
      // Fallback to end of the form if boundaries/page index is invalid
      return properties.length
    }

    const currentBoundary = boundaries[pageIdx]
    const endOfPageIdx = currentBoundary.end

    if (insertOnSamePage) {
        const lastField = properties[endOfPageIdx]
        if (lastField?.type === 'nf-page-break' && !isFieldHidden(lastField)) {
            return endOfPageIdx // Insert before the page break
        }
    }
    
    // Insert at the end of the current page (index after the last field of that page)
    return currentBoundary.end + 1
  }

  // --- Exposed API --- 
  return {
    // Reactive Computed Properties
    fieldGroups,
    pageCount,
    pageBoundaries,
    currentPageBreak,
    previousPageBreak,
    isLastPage,
    currentPage: computed(() => managerState?.currentPage ?? 0),
    currentPageHasPaymentBlock,
    currentPagePaymentBlock,

    // Methods
    getPageFields,    // Get fields for a specific page
    getPageForField,  // Find which page a field index belongs to
    hasPaymentBlock,  // Check if a page has a payment block
    getPaymentBlock,  // Get the payment block from a page
    isFieldHidden,    // Check if a specific field is hidden by state
    getTargetDropIndex, // Calculate absolute index for drag/drop
    determineInsertIndex, // Calculate where to insert a new field
    setPageForField // Set the current page to the page containing the specified field
  }
} 