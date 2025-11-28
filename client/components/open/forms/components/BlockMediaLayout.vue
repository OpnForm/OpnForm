<template>
  <div class="relative w-full h-full" :style="wrapperStyle">
    <VTransition name="fade">
      <div
        v-if="image && image.url && !isLoaded"
        class="absolute inset-0 bg-neutral-500"
      />
    </VTransition>
    <img
      v-if="image && image.url"
      :src="image.url"
      :alt="(image.alt && image.alt.length > 0) ? image.alt : alt"
      :class="[imgClass, { 'opacity-0': !isLoaded }]"
      :style="imageStyle"
      draggable="false"
      ref="imgEl"
      @dragstart.prevent
      @load="onLoad"
      @error="onError"
    >
  </div>
  
</template>

<script setup>
/*
  BlockMediaLayout
  Props:
    - image: {
        url: string,
        alt?: string,
        layout?: 'between'|'right-small'|'left-small'|'right-split'|'left-split'|'background',
        focal_point?: { x: number, y: number }, // 0..100
        brightness?: number // -100..100
      }
*/

const props = defineProps({
  image: { type: Object, required: false },
  // min-height to reserve space before image loads; accepts number (px) or CSS size string
  fallbackHeight: { type: [String, Number], default: '12rem' },
  // classes applied to the <img> element
  imgClass: { type: String, default: 'w-full h-full object-cover transition-opacity duration-300' },
  alt: { type: String, default: '' }
})

const isLoaded = ref(false)
const imgEl = ref(null)

const onLoad = () => {
  isLoaded.value = true
}

const onError = () => {
  isLoaded.value = false
}

watch(() => props.image?.url, () => {
  isLoaded.value = false
})

onMounted(() => {
  // If SSR rendered and image is already cached/loaded, mark as loaded
  if (imgEl.value && imgEl.value.complete) {
    isLoaded.value = true
  }
})

const imageStyle = computed(() => {
  const x = props.image?.focal_point?.x ?? 50
  const y = props.image?.focal_point?.y ?? 50
  const b = props.image?.brightness ?? 0 // -100..100
  let filter = ''
  if (b > 0) {
    // Positive values: combine brightness (1..2) and contrast (1..0)
    const brightnessScale = Math.max(1, Math.min(2, 1 + (b / 100)))
    const contrastScale = Math.max(0, Math.min(1, 1 - (b / 100)))
    filter = `contrast(${contrastScale}) brightness(${brightnessScale})`
  } else {
    // Negative values: brightness only (0..1)
    const brightnessScale = Math.max(0, Math.min(1, (b + 100) / 100))
    filter = `brightness(${brightnessScale})`
  }
  return {
    objectPosition: `${x}% ${y}%`,
    filter
  }
})

const wrapperStyle = computed(() => {
  const h = props.fallbackHeight
  if (h === null || h === undefined || h === '') return {}
  const value = typeof h === 'number' ? `${h}px` : h
  return { minHeight: value }
})
</script>



