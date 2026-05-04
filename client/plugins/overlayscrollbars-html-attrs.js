/**
 * Adds data-overlayscrollbars-initialize to <html> and <body> at SSR time
 * so the browser reserves space for the overlay scrollbar before the
 * client-side plugin initializes — prevents layout shift / flash of native scrollbar.
 */
export default defineNuxtPlugin(() => {
  useHead({
    htmlAttrs: {
      'data-overlayscrollbars-initialize': '',
    },
    bodyAttrs: {
      'data-overlayscrollbars-initialize': '',
    },
  })
})
