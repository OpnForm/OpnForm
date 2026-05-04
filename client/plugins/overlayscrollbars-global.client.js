import 'overlayscrollbars/overlayscrollbars.css'
import { OverlayScrollbars } from 'overlayscrollbars'
import { OverlayScrollbarsComponent } from 'overlayscrollbars-vue'
import { h, mergeProps } from 'vue'

const scrollbarOptions = {
  overflow: { x: 'hidden', y: 'scroll' },
  scrollbars: {
    theme: 'os-theme-light',
    visibility: 'auto',
    autoHide: 'move',
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

let bodyInitialized = false

function initBodyScrollbar () {
  if (bodyInitialized) return
  const existing = OverlayScrollbars(document.body)
  if (OverlayScrollbars.valid(existing)) {
    bodyInitialized = true
    return
  }
  OverlayScrollbars(
    { target: document.body, cancel: { body: null } },
    scrollbarOptions,
  )
  bodyInitialized = true
}

export default defineNuxtPlugin((nuxtApp) => {
  if (useIsIframe()) return

  nuxtApp.vueApp.component('OverlayScrollbarsComponent', OverlayScrollbarsWithDefaults)

  onNuxtReady(() => {
    initBodyScrollbar()
  })
})
