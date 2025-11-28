import { ref, computed, readonly, onBeforeUnmount } from 'vue'
import { useLocalStorage, useEventListener, breakpointsTailwind, useBreakpoints } from '@vueuse/core'

/**
 * Composable for making elements resizable with mouse drag
 * @param {Object} options - Configuration options
 * @param {string} options.storageKey - localStorage key for persisting width
 * @param {number} options.defaultWidth - Default width in pixels
 * @param {number} options.minWidth - Minimum width in pixels
 * @param {number} options.maxWidth - Maximum width in pixels or function that returns max width
 * @param {'left' | 'right'} options.direction - Resize direction (left = drag to resize right, right = drag to resize left)
 * @param {string} options.breakpoint - Minimum breakpoint where resizing is enabled (default: 'lg')
 */
export function useResizable(options = {}) {
  const {
    storageKey,
    defaultWidth = 350,
    minWidth = 315,
    maxWidth = 600,
    direction = 'right',
    breakpoint = 'lg'
  } = options

  // Breakpoint detection
  const breakpoints = useBreakpoints(breakpointsTailwind)
  const isResizableBreakpoint = computed(() => breakpoints.greater(breakpoint).value)

  // Persisted width using VueUse
  const width = storageKey 
    ? useLocalStorage(storageKey, defaultWidth)
    : ref(defaultWidth)

  // Resize state
  const isResizing = ref(false)
  const elementRef = ref(null)
  const handleRef = ref(null)

  // Computed properties
  const isResizable = computed(() => isResizableBreakpoint.value)
  
  const dynamicStyles = computed(() => {
    if (!isResizable.value) return {}
    
    return {
      width: `${width.value}px`,
      minWidth: `${minWidth}px`,
      maxWidth: typeof maxWidth === 'function' ? `${maxWidth()}px` : `${maxWidth}px`
    }
  })

  // Calculate new width based on mouse position and direction
  const calculateNewWidth = (clientX, containerRect) => {
    let newWidth
    
    if (direction === 'left') {
      // Left sidebar: drag right to increase width
      newWidth = clientX - containerRect.left
    } else {
      // Right sidebar: drag left to increase width  
      newWidth = containerRect.right - clientX
    }

    return newWidth
  }

  // Get max width value (handle function or number)
  const getMaxWidth = (containerRect) => {
    const baseMax = typeof maxWidth === 'function' ? maxWidth() : maxWidth
    // Ensure it doesn't exceed 60% of container width
    return Math.min(baseMax, containerRect.width * 0.6)
  }

  // Resize handlers
  const handleMouseMove = (event) => {
    if (!isResizing.value || !elementRef.value) return

    const containerRect = elementRef.value.parentElement?.getBoundingClientRect()
    if (!containerRect) return

    const newWidth = calculateNewWidth(event.clientX, containerRect)
    const maxWidthValue = getMaxWidth(containerRect)
    
    // Apply constraints
    width.value = Math.max(minWidth, Math.min(maxWidthValue, newWidth))
  }

  const handleMouseUp = () => {
    if (!isResizing.value) return
    
    isResizing.value = false
    document.body.style.cursor = ''
    document.body.style.userSelect = ''
  }

  const startResize = (event) => {
    if (!isResizable.value) return
    
    isResizing.value = true
    document.body.style.cursor = 'col-resize'
    document.body.style.userSelect = 'none'
    event.preventDefault()
  }

  // Auto-cleanup event listeners using VueUse
  useEventListener(document, 'mousemove', handleMouseMove, { passive: true })
  useEventListener(document, 'mouseup', handleMouseUp, { passive: true })

  // Internal cleanup on component unmount - handle edge case where component unmounts while resizing
  onBeforeUnmount(() => {
    if (isResizing.value) {
      handleMouseUp()
    }
  })

  return {
    // Refs
    elementRef,
    handleRef,
    
    // Reactive state
    width: readonly(width),
    isResizing: readonly(isResizing),
    isResizable,
    
    // Computed styles
    dynamicStyles,
    
    // Methods
    startResize,
    
    // For advanced usage
    setWidth: (newWidth) => {
      width.value = Math.max(minWidth, Math.min(getMaxWidth({ width: window.innerWidth }), newWidth))
    }
  }
}