<template>
  <img
    ref="image"
    :src="imageSrc"
    :alt="alt"
    width="32"
    height="32"
    decoding="async"
    @load="stop"
    @error="handleImageError"
  >
</template>

<script setup>
import { useIntersectionObserver } from '@vueuse/core'

const props = defineProps({
  src: { type: String, required: true },
  alt: { type: String, required: true },
})
const emit = defineEmits(['error'])

const image = ref(null)
const isVisible = ref(false)
const imageSrc = ref()
const fileLinks = inject('submissionFileLinks', null)
let isDisposed = false
onBeforeUnmount(() => { isDisposed = true })
// Observe both scroll axes and ancestor clipping, without preloading offscreen files.
// Keep the source after the first appearance so scrolling back does not reload it.
const { stop, isSupported } = useIntersectionObserver(image, ([entry]) => {
  isVisible.value = entry?.isIntersecting ?? false
}, { rootMargin: '0px' })

onMounted(() => {
  if (!isSupported.value) isVisible.value = true
})

const renewImage = () => {
  imageSrc.value = undefined
  fileLinks.refresh()
    .then(() => nextTick())
    .then(() => {
      // Refreshed keys usually remount this component. Handle an unchanged
      // URL too (e.g. a fast refresh with a skewed browser clock).
      if (!isDisposed && isVisible.value && fileLinks.isFresh(props.src)) {
        imageSrc.value = props.src
      }
    })
    .catch(() => {
      console.warn('Unable to refresh submission file links. Refresh the table to retry.')
    })
}

const handleImageError = (event) => {
  const isSigned = new URL(props.src, window.location.href).searchParams.has('expires')
  // A slow browser clock can miss expiration. Retry with freshly issued links
  // before treating the file as broken; failures of trusted links are final.
  if (fileLinks && isSigned && !fileLinks.isFresh(props.src)) {
    renewImage()
    return
  }
  emit('error', event)
}

watch([isVisible, () => props.src], ([visible, src]) => {
  if (!visible) return

  const expires = new URL(src, window.location.href).searchParams.get('expires')
  if (fileLinks && !fileLinks.isFresh(src) && expires && Number(expires) * 1000 <= Date.now() + 30000) {
    // Refetching replaces the signed URLs in the table. Never request an expired URL.
    renewImage()
    return
  }

  imageSrc.value = src
})
</script>
