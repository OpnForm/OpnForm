import 'overlayscrollbars/overlayscrollbars.css'
import { OverlayScrollbarsComponent } from 'overlayscrollbars-vue'
import { h, mergeProps } from 'vue'

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

const OverlayScrollbarsWithDefaults = {
  name: 'OverlayScrollbarsComponent',
  inheritAttrs: false,
  setup (_, { attrs, slots, expose }) {
    const inner = ref(null)
    expose({
      osInstance: () => inner.value?.osInstance(),
      getElement: () => inner.value?.getElement(),
    })

    return () => {
      const merged = mergeProps(attrs, {
        options: { ...scrollbarOptions, ...attrs.options },
        ref: inner,
      })
      return h(OverlayScrollbarsComponent, merged, slots)
    }
  },
}

export default defineNuxtPlugin((nuxtApp) => {
  nuxtApp.vueApp.component('OverlayScrollbarsComponent', OverlayScrollbarsWithDefaults)
})
