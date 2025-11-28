<template>
  <div class="relative">
    <!-- Top Fade -->
    <VTransition name="fade">
      <div
        v-if="showTopFade"
        :class="[
          'absolute top-0 left-0 right-0 z-10 pointer-events-none',
          topFadeClasses
        ]"
      />
    </VTransition>

    <!-- Left Fade -->
    <VTransition name="fade">
      <div
        v-if="showLeftFade"
        :class="[
          'absolute top-0 left-0 bottom-0 z-10 pointer-events-none',
          leftFadeClasses
        ]"
      />
    </VTransition>

    <!-- Scrollable Content -->
    <div 
      ref="scrollContainer"
      :class="scrollClasses"
    >
      <slot />
    </div>

    <!-- Right Fade -->
    <VTransition name="fade">
      <div
        v-if="showRightFade"
        :class="[
          'absolute top-0 right-0 bottom-0 z-10 pointer-events-none',
          rightFadeClasses
        ]"
      />
    </VTransition>

    <!-- Bottom Fade -->
    <VTransition name="fade">
      <div
        v-if="showBottomFade"
        :class="[
          'absolute bottom-0 left-0 right-0 z-10 pointer-events-none',
          bottomFadeClasses
        ]"
      />
    </VTransition>
  </div>
</template>

<script setup>
import VTransition from '@/components/global/transitions/VTransition.vue'
import { useScroll, useResizeObserver, useMutationObserver } from '@vueuse/core'

const props = defineProps({
  maxHeightClass: {
    type: String,
    default: 'max-h-80'
  },
  maxWidthClass: {
    type: String,
    default: ''
  },
  direction: {
    type: String,
    default: 'vertical', // 'vertical', 'horizontal', 'both'
    validator: (value) => ['vertical', 'horizontal', 'both'].includes(value)
  },
  topFadeHeight: {
    type: String,
    default: 'h-8'
  },
  bottomFadeHeight: {
    type: String,
    default: 'h-8'
  },
  leftFadeWidth: {
    type: String,
    default: 'w-8'
  },
  rightFadeWidth: {
    type: String,
    default: 'w-8'
  },
  fadeClass: {
    type: String,
    default: 'from-white to-transparent'
  },
  scrollTolerance: {
    type: Number,
    default: 3 // pixels of tolerance for scroll end detection
  }
})

// Scroll fade detection
const scrollContainer = ref(null)
const { x: scrollX, y: scrollY } = useScroll(scrollContainer)
const contentHeight = ref(0)
const containerHeight = ref(0)
const contentWidth = ref(0)
const containerWidth = ref(0)

const showTopFade = computed(() => {
  if (!['vertical', 'both'].includes(props.direction)) return false
  // ensure reactivity on scroll
  void scrollY.value
  // ensure reactivity on size changes
  void containerHeight.value
  const element = scrollContainer.value
  const currentTop = element ? element.scrollTop : 0
  return currentTop > props.scrollTolerance
})

const showBottomFade = computed(() => {
  if (!['vertical', 'both'].includes(props.direction)) return false
  // ensure reactivity on scroll
  void scrollY.value
  // ensure reactivity on size/content changes
  void containerHeight.value
  void contentHeight.value
  const element = scrollContainer.value
  if (!element) return false

  const realContainerHeight = element.clientHeight
  const realContentHeight = element.scrollHeight
  const realCurrentScroll = element.scrollTop
  const realMaxScroll = realContentHeight - realContainerHeight

  if (realMaxScroll <= 0) return false

  const effectiveTolerance = Math.max(props.scrollTolerance, 1)
  const distanceFromEnd = realMaxScroll - realCurrentScroll
  const isAtEnd = distanceFromEnd <= effectiveTolerance

  return !isAtEnd
})

const showLeftFade = computed(() => {
  return (props.direction === 'horizontal' || props.direction === 'both') && scrollX.value > props.scrollTolerance
})

const showRightFade = computed(() => {
  if (!['horizontal', 'both'].includes(props.direction)) return false
  if (containerWidth.value === 0 || contentWidth.value === 0) return false
  
  // Get real-time measurements from the actual element for more accuracy
  const element = scrollContainer.value
  if (!element) return false
  // ensure reactivity on scroll
  void scrollX.value
  // ensure reactivity on size/content changes
  void containerWidth.value
  void contentWidth.value
  
  const realContainerWidth = element.clientWidth // excludes scrollbar, includes padding
  const realContentWidth = element.scrollWidth
  const realCurrentScroll = element.scrollLeft
  const realMaxScroll = realContentWidth - realContainerWidth
  
  // Calculate distance from end using real measurements
  const distanceFromEnd = realMaxScroll - realCurrentScroll
  
  // Use a more generous tolerance and also check if we're very close to the max possible scroll
  const effectiveTolerance = Math.max(props.scrollTolerance, 3)
  const isAtEnd = distanceFromEnd <= effectiveTolerance
  
  // Additional check: if scrollLeft + clientWidth >= scrollWidth (standard end detection)
  const standardEndCheck = realCurrentScroll + realContainerWidth >= realContentWidth - 1
  
  const shouldShow = !isAtEnd && !standardEndCheck
  
  return shouldShow
})

const topFadeClasses = computed(() => {
  return `${props.topFadeHeight} bg-gradient-to-b ${props.fadeClass}`
})

const bottomFadeClasses = computed(() => {
  return `${props.bottomFadeHeight} bg-gradient-to-t ${props.fadeClass}`
})

const leftFadeClasses = computed(() => {
  return `${props.leftFadeWidth} bg-gradient-to-r ${props.fadeClass}`
})

const rightFadeClasses = computed(() => {
  return `${props.rightFadeWidth} bg-gradient-to-l ${props.fadeClass}`
})

const scrollClasses = computed(() => {
  const classes = []
  
  if (props.direction === 'vertical') {
    classes.push('overflow-y-auto', 'overflow-x-hidden')
  } else if (props.direction === 'horizontal') {
    classes.push('overflow-x-auto', 'overflow-y-hidden')
  } else if (props.direction === 'both') {
    classes.push('overflow-auto')
  }
  
  if (props.maxHeightClass) classes.push(props.maxHeightClass)
  if (props.maxWidthClass) classes.push(props.maxWidthClass)
  
  return classes
})

// Update content dimensions when container resizes
useResizeObserver(scrollContainer, (entries) => {
  const entry = entries[0]
  containerHeight.value = entry.contentRect.height
  containerWidth.value = entry.contentRect.width
  if (scrollContainer.value) {
    contentHeight.value = scrollContainer.value.scrollHeight
    contentWidth.value = scrollContainer.value.scrollWidth
  }
})

// Force update dimensions - useful for when content changes dynamically
const updateDimensions = () => {
  if (scrollContainer.value) {
    const rect = scrollContainer.value.getBoundingClientRect()
    containerHeight.value = rect.height
    containerWidth.value = rect.width
    contentHeight.value = scrollContainer.value.scrollHeight
    contentWidth.value = scrollContainer.value.scrollWidth
  }
}

// Debug method to check current scroll state
const debugCurrentState = () => {
  if (scrollContainer.value) {
    const element = scrollContainer.value
    const realContainerWidth = element.clientWidth
    const realContentWidth = element.scrollWidth
    const realCurrentScroll = element.scrollLeft
    const realMaxScroll = realContentWidth - realContainerWidth
    const distanceFromEnd = realMaxScroll - realCurrentScroll
    const standardEndCheck = realCurrentScroll + realContainerWidth >= realContentWidth - 1
    
    return {
      realContainerWidth,
      realContentWidth,
      realCurrentScroll,
      realMaxScroll,
      distanceFromEnd,
      tolerance: props.scrollTolerance,
      isAtEnd: distanceFromEnd <= Math.max(props.scrollTolerance, 3),
      standardEndCheck,
      shouldShowFade: showRightFade.value
    }
  }
  return null
}

// Watch for content changes that might affect dimensions
watch([contentWidth, contentHeight], () => {
  nextTick(() => {
    updateDimensions()
  })
})

// Initialize dimensions once mounted
onMounted(() => {
  nextTick(() => updateDimensions())
})

// Observe slot/content mutations to keep dimensions fresh when content changes
useMutationObserver(scrollContainer, () => {
  nextTick(() => updateDimensions())
}, {
  childList: true,
  subtree: true,
  characterData: true
})

// Expose container ref and utility functions for parent components
defineExpose({
  scrollContainer,
  updateDimensions,
  debugCurrentState
})
</script> 