<template>
  <img
    ref="image"
    :src="shouldLoad ? src : undefined"
    :alt="alt"
    width="32"
    height="32"
    decoding="async"
  >
</template>

<script setup>
import { useIntersectionObserver } from '@vueuse/core'

defineProps({
  src: { type: String, required: true },
  alt: { type: String, required: true },
})

const image = ref(null)
const shouldLoad = ref(false)
// Observe both scroll axes and ancestor clipping, without preloading offscreen files.
// Keep the source after the first appearance so scrolling back does not reload it.
const { stop, isSupported } = useIntersectionObserver(image, ([entry]) => {
  if (entry?.isIntersecting) {
    shouldLoad.value = true
    stop()
  }
}, { rootMargin: '0px' })

onMounted(() => {
  if (!isSupported.value) shouldLoad.value = true
})
</script>
