let darkModeNodeParent = import.meta.client ? document.documentElement : null

/**
 * Handle form public pages dark mode and transparent mode
 */
export function handleDarkMode (darkMode, elem = null) {
  if (import.meta.server)
    return

  darkModeNodeParent = elem ?? document.documentElement

  // Dark mode
  if (['dark', 'light'].includes(darkMode))
    return handleDarkModeToggle(darkMode === 'dark')

  // Case auto
  handleDarkModeToggle(
    window.matchMedia('(prefers-color-scheme: dark)').matches
  )

  // Create listener
  window
    .matchMedia('(prefers-color-scheme: dark)')
    .addEventListener('change', handleDarkModeToggle)
}

export function useClassWatcher (elem, className) {
  const hasClass = ref(false)

  const updateClassPresence = () => {
    hasClass.value = elem.value?.classList.contains(className) ?? false
  }

  let observer = null

  const startObserving = () => {
    if (elem.value) {
      updateClassPresence()
      observer = new MutationObserver(updateClassPresence)
      observer.observe(elem.value, { attributes: true, attributeFilter: ['class'] })
    }
  }

  const stopObserving = () => {
    if (observer) {
      observer.disconnect()
      observer = null
    }
  }

  onMounted(() => {
    watch(elem, (newElem, oldElem, onCleanup) => {
      stopObserving()
      startObserving()
      onCleanup(stopObserving)
    }, { immediate: true })
  })

  onUnmounted(() => {
    stopObserving()
  })

  return computed(() => hasClass.value)
}

export function useDarkMode (elem = ref(null)) {
  // Define a computed property to handle the element reference reactively
  const effectiveElem = computed(() => {
    return elem.value || (process.client ? document.documentElement : null)
  })

  // Pass the computed property to useClassWatcher
  return useClassWatcher(effectiveElem, 'dark')
}

export function darkModeEnabled (elem = ref(null)) {
  return useDarkMode(elem)
}

function handleDarkModeToggle (enabled) {
  if (enabled !== false && enabled !== true) {
    // if we received an event
    enabled = enabled.matches
  }
  enabled
    ? darkModeNodeParent.classList.add('dark')
    : darkModeNodeParent.classList.remove('dark')
}

export function disableDarkMode () {
  if (import.meta.server)
    return
  const html = document.documentElement
  html.classList.remove('dark')
  // Remove event listener
  window
    .matchMedia('(prefers-color-scheme: dark)')
    .removeEventListener('change', handleDarkModeToggle)
}

export function handleTransparentMode (transparentModeEnabled) {
  if (import.meta.server)
    return
  if (!useIsIframe() || !transparentModeEnabled)
    return

  const app = document.getElementById('app')
  app.classList.remove('bg-white')
  app.classList.remove('dark:bg-notion-dark')
  app.classList.add('bg-transparent')
  document.body.classList.add('bg-transparent')
}

export function focusOnFirstFormElement () {
  if (import.meta.server)
    return
  for (const ele of document.querySelectorAll(
    'input,button,textarea,[role="button"]'
  )) {
    if (ele.offsetWidth !== 0 || ele.offsetHeight !== 0) {
      ele.focus()
      break
    }
  }
}
