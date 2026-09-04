<template>
  <AsyncOverlayScrollbarsComponent
    ref="inner"
    v-bind="mergedProps"
  >
    <slot />
  </AsyncOverlayScrollbarsComponent>
</template>

<script setup>
import { defineAsyncComponent, mergeProps } from 'vue'

defineOptions({ inheritAttrs: false })

const attrs = useAttrs()
const inner = ref(null)

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

const AsyncOverlayScrollbarsComponent = defineAsyncComponent(() => Promise.all([
  import('overlayscrollbars/overlayscrollbars.css'),
  import('overlayscrollbars-vue'),
]).then(([, module]) => module.OverlayScrollbarsComponent))

const mergedProps = computed(() => mergeProps(
  { defer: true, options: scrollbarOptions },
  attrs,
  attrs.options ? { options: { ...scrollbarOptions, ...attrs.options } } : {},
))

defineExpose({
  osInstance: () => inner.value?.osInstance(),
  getElement: () => inner.value?.getElement(),
})
</script>
