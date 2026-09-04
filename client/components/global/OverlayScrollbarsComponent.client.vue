<template>
  <div
    ref="host"
    v-bind="attrs"
  >
    <slot />
  </div>
</template>

<script setup>
defineOptions({ inheritAttrs: false })

const attrs = useAttrs()
const props = defineProps({
  defer: { type: Boolean, default: false },
  options: { type: Object, default: () => ({}) },
})
const host = ref(null)
const instance = shallowRef(null)
let cancelInitialization = null

const scrollbarOptions = {
  overflow: { x: 'hidden', y: 'scroll' },
  scrollbars: {
    theme: 'os-theme-dark',
    visibility: 'auto',
    autoHide: 'never',
    autoHideDelay: 800,
    autoHideSuspend: true,
  },
}

const mergedOptions = computed(() => ({ ...scrollbarOptions, ...props.options }))

function initialize() {
  const focusedElement = host.value?.contains(document.activeElement)
    ? document.activeElement
    : null

  Promise.all([
    import('overlayscrollbars/overlayscrollbars.css'),
    import('overlayscrollbars'),
  ]).then(([, { OverlayScrollbars }]) => {
    if (!host.value) return
    instance.value = OverlayScrollbars(host.value, mergedOptions.value)
    focusedElement?.focus?.({ preventScroll: true })
  }).catch((error) => {
    // Native scrolling remains fully functional if the optional enhancement
    // cannot be downloaded.
    console.error('Failed to load OverlayScrollbars:', error)
  })
}

onMounted(() => {
  if (!props.defer) {
    initialize()
    return
  }

  if ('requestIdleCallback' in window) {
    const idleCallbackId = window.requestIdleCallback(initialize)
    cancelInitialization = () => window.cancelIdleCallback(idleCallbackId)
    return
  }

  const timeoutId = window.setTimeout(initialize, 0)
  cancelInitialization = () => window.clearTimeout(timeoutId)
})

watch(mergedOptions, (options) => {
  instance.value?.options(options)
}, { deep: true })

onBeforeUnmount(() => {
  cancelInitialization?.()
  instance.value?.destroy()
})

defineExpose({
  osInstance: () => instance.value,
  getElement: () => host.value,
})
</script>
